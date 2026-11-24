<?php

use App\Enums\PurchaseStatus;
use App\Enums\SettlementEntryType;
use App\Models\ConsumptionEntry;
use App\Models\Room;
use App\Models\User;
use App\Services\BalanceService;
use App\Services\ConsumptionService;
use App\Services\PurchaseService;
use App\Services\SettlementService;
use Database\Seeders\DemoRoomSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('registers the 500000 toman inventory purchase and allocates the first two eggs precisely', function () {
    [$owner, $room, $members] = financeFixture();
    $this->actingAs($owner);

    $response = $this->postJson("/api/v1/rooms/{$room->id}/purchases", [
        'request_id' => (string) Str::uuid(),
        'payer_member_id' => $members['وحید']->id,
        'title' => 'تخم‌مرغ',
        'kind' => 'inventory',
        'amount_toman' => 500000,
        'unit_label' => 'عدد',
        'total_quantity_milliunits' => 30000,
        'purchased_at' => '2026-10-11T21:00:00.000Z',
    ])->assertCreated()
        ->assertJsonPath('data.amount_toman', 500000)
        ->assertJsonPath('data.purchased_at', fn (string $purchasedAt): bool => new DateTimeImmutable($purchasedAt) == new DateTimeImmutable('2026-10-11T21:00:00Z'));

    $purchaseId = $response->json('data.id');
    $this->postJson("/api/v1/rooms/{$room->id}/purchases/{$purchaseId}/consumptions", [
        'request_id' => (string) Str::uuid(),
        'consumer_member_id' => $members['محمدرضا']->id,
        'quantity_milliunits' => 2000,
        'consumed_at' => '2026-10-12T14:00:00+03:30',
    ])->assertCreated()
        ->assertJsonPath('data.allocated_amount_toman', 33333)
        ->assertJsonPath('data.remaining_quantity_milliunits', 28000);
});

it('allocates the next egg as 16667 toman using the cumulative remainder', function () {
    [$owner, $room, $members] = financeFixture();
    $purchase = financeInventory($room, $owner, $members['وحید']);

    financeConsume($room, $owner, $purchase, $members['محمدرضا'], 2000);
    $entry = financeConsume($room, $owner, $purchase, $members['امیر'], 1000);

    expect($entry->allocated_amount_toman)->toBe(16667)
        ->and($entry->result_remaining_quantity_milliunits)->toBe(27000);
});

it('allocates the full purchase amount across multiple inventory consumption events', function () {
    [$owner, $room, $members] = financeFixture();
    $purchase = financeInventory($room, $owner, $members['وحید']);

    financeConsume($room, $owner, $purchase, $members['محمدرضا'], 7000);
    financeConsume($room, $owner, $purchase, $members['امیر'], 11000);
    financeConsume($room, $owner, $purchase, $members['مهدی'], 12000);

    expect((int) $purchase->consumptions()->sum('allocated_amount_toman'))->toBe(500000)
        ->and((int) $purchase->consumptions()->sum('quantity_milliunits'))->toBe(30000);
});

it('rejects consumption beyond available stock without changing the ledger', function () {
    [$owner, $room, $members] = financeFixture();
    $purchase = financeInventory($room, $owner, $members['وحید']);
    financeConsume($room, $owner, $purchase, $members['محمدرضا'], 29000);

    expect(fn () => financeConsume($room, $owner, $purchase, $members['امیر'], 2000))
        ->toThrow(ValidationException::class);

    expect($purchase->consumptions()->count())->toBe(1)
        ->and((int) $purchase->consumptions()->sum('quantity_milliunits'))->toBe(29000);
});

it('splits a 30000 toman shared purchase equally among three selected members', function () {
    [$owner, $room, $members] = financeFixture();
    $purchase = financeShared($room, $owner, $members['مهدی'], [$members['محمدرضا'], $members['امیر'], $members['رعنا']]);

    expect($purchase->allocations()->orderBy('room_member_id')->pluck('amount_toman')->map(fn ($amount): int => (int) $amount)->all())
        ->toBe([10000, 10000, 10000])
        ->and(financePair($room, $members['محمدرضا'], $members['مهدی'])['amount_toman'])->toBe(10000);
});

it('assigns the equal-split remainder to the first member ids deterministically', function () {
    [$owner, $room, $members] = financeFixture();
    $purchase = financeShared($room, $owner, $members['مهدی'], [$members['رعنا'], $members['محمدرضا'], $members['امیر']], 30001);
    $shares = $purchase->allocations()->orderBy('room_member_id')->pluck('amount_toman')->map(fn ($amount): int => (int) $amount)->all();

    expect($shares)->toBe([10001, 10000, 10000])
        ->and(array_sum($shares))->toBe(30001);
});

it('records the buyer share without creating a debt to oneself', function () {
    [$owner, $room, $members] = financeFixture();
    $purchase = financeShared($room, $owner, $members['مهدی'], [$members['مهدی'], $members['وحید']], 40000);

    expect($purchase->allocations()->sum('amount_toman'))->toBe(40000)
        ->and(financePair($room, $members['وحید'], $members['مهدی'])['amount_toman'])->toBe(20000)
        ->and(financePair($room, $members['مهدی'], $members['مهدی']))->toBeNull();
});

it('nets reciprocal obligations so Mahdi owes Vahid only 23333 toman', function () {
    [$owner, $room, $members] = financeFixture();
    $eggs = financeInventory($room, $owner, $members['وحید']);
    financeConsume($room, $owner, $eggs, $members['محمدرضا'], 2000);
    financeConsume($room, $owner, $eggs, $members['امیر'], 1000);
    financeConsume($room, $owner, $eggs, $members['مهدی'], 2000);
    financeShared($room, $owner, $members['مهدی'], [$members['وحید'], $members['حسین'], $members['نیما']]);

    $balances = app(BalanceService::class)->forRoom($room);
    expect(financePair($room, $members['مهدی'], $members['وحید'])['amount_toman'])->toBe(23333)
        ->and(collect($balances)->where('debtor_member_id', $members['وحید']->id)->where('creditor_member_id', $members['مهدی']->id))->toHaveCount(0);
});

it('reverses the net direction when the reciprocal obligation is larger', function () {
    [$owner, $room, $members] = financeFixture();
    $eggs = financeInventory($room, $owner, $members['وحید']);
    financeConsume($room, $owner, $eggs, $members['مهدی'], 2000);
    financeShared($room, $owner, $members['مهدی'], [], 50000, null, [[
        'room_member_id' => $members['وحید']->id,
        'amount_toman' => 50000,
    ]]);

    expect(financePair($room, $members['وحید'], $members['مهدی'])['amount_toman'])->toBe(16667)
        ->and(financePair($room, $members['مهدی'], $members['وحید']))->toBeNull();
});

it('reduces a reciprocal balance after a partial settlement submitted with a date only', function () {
    [$owner, $room, $members] = financeFixture();
    $eggs = financeInventory($room, $owner, $members['وحید']);
    financeConsume($room, $owner, $eggs, $members['مهدی'], 2000);
    financeShared($room, $owner, $members['مهدی'], [], 10000, null, [[
        'room_member_id' => $members['وحید']->id,
        'amount_toman' => 10000,
    ]]);

    $this->actingAs($owner)->postJson("/api/v1/rooms/{$room->id}/settlements", [
        'request_id' => (string) Str::uuid(),
        'from_member_id' => $members['مهدی']->id,
        'to_member_id' => $members['وحید']->id,
        'amount_toman' => 10000,
        'paid_at' => '2026-10-12',
    ])->assertCreated()->assertJsonPath('data.paid_at', fn (string $paidAt): bool => str_starts_with($paidAt, '2026-10-12T00:00:00'));

    expect(financePair($room, $members['مهدی'], $members['وحید'])['amount_toman'])->toBe(13333);
});

it('settles a balance fully and recalculates it when a new expense is recorded', function () {
    [$owner, $room, $members] = financeFixture();
    $eggs = financeInventory($room, $owner, $members['وحید']);
    financeConsume($room, $owner, $eggs, $members['مهدی'], 2000);

    app(SettlementService::class)->create($room, $owner, [
        'request_id' => (string) Str::uuid(),
        'from_member_id' => $members['مهدی']->id,
        'to_member_id' => $members['وحید']->id,
        'amount_toman' => 33333,
        'paid_at' => '2026-10-12T18:00:00+03:30',
    ]);
    expect(financePair($room, $members['مهدی'], $members['وحید']))->toBeNull();

    financeConsume($room, $owner, $eggs, $members['مهدی'], 1000);
    expect(financePair($room, $members['مهدی'], $members['وحید'])['amount_toman'])->toBe(16667);
});

it('rejects purchase, consumption, and settlement references from another room', function () {
    [$owner, $room, $members] = financeFixture();
    [$otherOwner, $otherRoom, $otherMembers] = financeFixture();
    $purchase = financeInventory($room, $owner, $members['وحید']);

    expect(fn () => app(PurchaseService::class)->create($room, $owner, [
        'request_id' => (string) Str::uuid(), 'payer_member_id' => $otherMembers['وحید']->id,
        'title' => 'غیرمجاز', 'kind' => 'inventory', 'amount_toman' => 1000,
        'unit_label' => 'عدد', 'total_quantity_milliunits' => 1000, 'purchased_at' => now()->toIso8601String(),
    ]))->toThrow(ValidationException::class);
    expect(fn () => financeConsume($room, $owner, $purchase, $otherMembers['امیر'], 1000))->toThrow(ValidationException::class);
    expect(fn () => app(SettlementService::class)->create($room, $owner, [
        'request_id' => (string) Str::uuid(), 'from_member_id' => $otherMembers['امیر']->id,
        'to_member_id' => $members['وحید']->id, 'amount_toman' => 1, 'paid_at' => now()->toIso8601String(),
    ]))->toThrow(ValidationException::class);
});

it('returns the existing financial entry for an identical idempotency key', function () {
    [$owner, $room, $members] = financeFixture();
    $purchase = financeInventory($room, $owner, $members['وحید']);
    $key = (string) Str::uuid();
    $first = financeConsume($room, $owner, $purchase, $members['امیر'], 1000, $key);
    $second = financeConsume($room, $owner, $purchase, $members['امیر'], 1000, $key);

    expect($second->id)->toBe($first->id)
        ->and($purchase->consumptions()->count())->toBe(1);
});

it('rejects reuse of an idempotency key with a different payload', function () {
    [$owner, $room, $members] = financeFixture();
    $purchase = financeInventory($room, $owner, $members['وحید']);
    $key = (string) Str::uuid();
    financeConsume($room, $owner, $purchase, $members['امیر'], 1000, $key);

    expect(fn () => financeConsume($room, $owner, $purchase, $members['امیر'], 2000, $key))
        ->toThrow(HttpException::class);
    expect($purchase->consumptions()->count())->toBe(1);
});

it('allows only one sequential consumption of the last inventory unit', function () {
    [$owner, $room, $members] = financeFixture();
    $purchase = financeInventory($room, $owner, $members['وحید'], 10000, 1000);
    financeConsume($room, $owner, $purchase, $members['امیر'], 1000);

    expect(fn () => financeConsume($room, $owner, $purchase, $members['مهدی'], 1000))
        ->toThrow(ValidationException::class);
    expect((int) $purchase->consumptions()->sum('quantity_milliunits'))->toBe(1000);
});

it('reverses a consumption with exact negative values and preserves both records', function () {
    [$owner, $room, $members] = financeFixture();
    $purchase = financeInventory($room, $owner, $members['وحید']);
    $original = financeConsume($room, $owner, $purchase, $members['محمدرضا'], 2000);
    $reversal = app(ConsumptionService::class)->reverse($room, $original, $owner, (string) Str::uuid(), 'اشتباه ثبت شد');

    expect($reversal->quantity_milliunits)->toBe(-2000)
        ->and($reversal->allocated_amount_toman)->toBe(-33333)
        ->and((int) $purchase->consumptions()->sum('quantity_milliunits'))->toBe(0)
        ->and($purchase->consumptions()->count())->toBe(2)
        ->and(financePair($room, $members['محمدرضا'], $members['وحید']))->toBeNull();
});

it('reverses a payment by adding an opposite audit row and restoring the account', function () {
    [$owner, $room, $members] = financeFixture();
    $purchase = financeInventory($room, $owner, $members['وحید']);
    financeConsume($room, $owner, $purchase, $members['مهدی'], 2000);
    $payment = app(SettlementService::class)->create($room, $owner, [
        'request_id' => (string) Str::uuid(), 'from_member_id' => $members['مهدی']->id,
        'to_member_id' => $members['وحید']->id, 'amount_toman' => 10000, 'paid_at' => now()->toIso8601String(),
    ]);
    $reversal = app(SettlementService::class)->reverse($room, $payment, $owner, (string) Str::uuid());

    expect($reversal->amount_toman)->toBe(-10000)
        ->and($reversal->entry_type)->toBe(SettlementEntryType::Reversal)
        ->and($payment->fresh())->not->toBeNull()
        ->and(financePair($room, $members['مهدی'], $members['وحید'])['amount_toman'])->toBe(33333);
});

it('voids a shared purchase without deleting its allocation audit rows', function () {
    [$owner, $room, $members] = financeFixture();
    $purchase = financeShared($room, $owner, $members['مهدی'], [$members['محمدرضا'], $members['امیر'], $members['رعنا']]);
    $allocationsBefore = $purchase->allocations()->count();
    app(PurchaseService::class)->void($room, $purchase, $owner, (string) Str::uuid(), 'خرید اشتباه');

    expect($purchase->fresh()->status)->toBe(PurchaseStatus::Voided)
        ->and($purchase->allocations()->count())->toBe($allocationsBefore)
        ->and(financePair($room, $members['محمدرضا'], $members['مهدی']))->toBeNull();
});

it('keeps existing balances for members who become inactive', function () {
    [$owner, $room, $members] = financeFixture();
    $purchase = financeInventory($room, $owner, $members['وحید']);
    financeConsume($room, $owner, $purchase, $members['امیر'], 1000);
    $members['امیر']->update(['is_active' => false]);

    expect(financePair($room, $members['امیر'], $members['وحید'])['amount_toman'])->toBe(16666)
        ->and($members['امیر']->fresh()->is_active)->toBeFalse();
});

it('does not create debt for an inventory purchase before any consumption', function () {
    [$owner, $room, $members] = financeFixture();
    financeInventory($room, $owner, $members['وحید']);

    expect(app(BalanceService::class)->forRoom($room))->toBe([]);
});

it('keeps pairwise balances separate in a three-member obligation chain', function () {
    [$owner, $room, $members] = financeFixture();
    financeShared($room, $owner, $members['مهدی'], [$members['وحید']], 12000);
    financeShared($room, $owner, $members['علی'], [$members['مهدی']], 8000);

    $balances = app(BalanceService::class)->forRoom($room);
    expect(financePair($room, $members['وحید'], $members['مهدی'])['amount_toman'])->toBe(12000)
        ->and(financePair($room, $members['مهدی'], $members['علی'])['amount_toman'])->toBe(8000)
        ->and(count($balances))->toBe(2);
});

it('serializes Toman fields in API responses as integers', function () {
    [$owner, $room, $members] = financeFixture();
    $purchase = financeInventory($room, $owner, $members['وحید']);
    $this->actingAs($owner)
        ->getJson("/api/v1/rooms/{$room->id}/purchases/{$purchase->id}")
        ->assertOk()
        ->assertJsonPath('data.amount_toman', 500000);

    expect($purchase->amount_toman)->toBeInt();
});

it('denies guests and non-owners access to a room', function () {
    [$owner, $room] = financeFixture();
    $this->getJson("/api/v1/rooms/{$room->id}/dashboard")->assertUnauthorized();
    $this->actingAs(User::factory()->create())
        ->getJson("/api/v1/rooms/{$room->id}/dashboard")
        ->assertForbidden();
});

it('splits a 30000 toman expense among four participants at 7500 each', function () {
    [$owner, $room, $members] = financeFixture();
    $purchase = financeShared($room, $owner, $members['مهدی'], [$members['وحید'], $members['محمدرضا'], $members['امیر'], $members['رعنا']]);

    expect($purchase->allocations()->pluck('amount_toman')->map(fn ($amount): int => (int) $amount)->all())
        ->toBe([7500, 7500, 7500, 7500]);
});

it('validates that manual shares sum to the full purchase amount', function () {
    [$owner, $room, $members] = financeFixture();

    expect(fn () => financeShared($room, $owner, $members['مهدی'], [], 30000, null, [
        ['room_member_id' => $members['وحید']->id, 'amount_toman' => 10000],
        ['room_member_id' => $members['محمدرضا']->id, 'amount_toman' => 10000],
    ]))->toThrow(ValidationException::class);
});

it('shows the exact persisted reversal and settlement events in a member ledger', function () {
    [$owner, $room, $members] = financeFixture();
    $purchase = financeInventory($room, $owner, $members['وحید']);
    $entry = financeConsume($room, $owner, $purchase, $members['امیر'], 1000);
    app(ConsumptionService::class)->reverse($room, $entry, $owner, (string) Str::uuid());

    $ledger = app(BalanceService::class)->ledger($room, $members['امیر']);
    expect($ledger)->toHaveCount(2)
        ->and($ledger[0]['effect_on_balance_toman'])->toBe(16666)
        ->and($ledger[1]['effect_on_balance_toman'])->toBe(-16666)
        ->and($ledger[1]['balance_after_toman'])->toBe(0);
});

it('filters purchase history by payer, type, and date range', function () {
    [$owner, $room, $members] = financeFixture();
    financeInventory($room, $owner, $members['وحید']);
    financeShared($room, $owner, $members['مهدی'], [$members['امیر']]);
    $this->actingAs($owner)
        ->getJson("/api/v1/rooms/{$room->id}/purchases?kind=shared&payer_member_id={$members['مهدی']->id}&from=2026-10-12&to=2026-10-12")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters inventory history by consumer while keeping entries scoped to the room', function () {
    [$owner, $room, $members] = financeFixture();
    $purchase = financeInventory($room, $owner, $members['وحید']);
    financeConsume($room, $owner, $purchase, $members['امیر'], 1000);
    financeConsume($room, $owner, $purchase, $members['مهدی'], 1000);
    $this->actingAs($owner)
        ->getJson("/api/v1/rooms/{$room->id}/purchases/{$purchase->id}/consumptions?consumer_member_id={$members['امیر']->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.consumer_member_id', $members['امیر']->id);
});

it('allows an inactive member with an old balance to settle that balance', function () {
    [$owner, $room, $members] = financeFixture();
    $purchase = financeInventory($room, $owner, $members['وحید']);
    financeConsume($room, $owner, $purchase, $members['امیر'], 1000);
    $members['امیر']->update(['is_active' => false]);
    $settlement = app(SettlementService::class)->create($room, $owner, [
        'request_id' => (string) Str::uuid(), 'from_member_id' => $members['امیر']->id,
        'to_member_id' => $members['وحید']->id, 'amount_toman' => 16666, 'paid_at' => now()->toIso8601String(),
    ]);

    expect($settlement->amount_toman)->toBe(16666)
        ->and(financePair($room, $members['امیر'], $members['وحید']))->toBeNull();
});

it('rejects a fractional consumption quantity for countable inventory units', function () {
    [$owner, $room, $members] = financeFixture();
    $purchase = financeInventory($room, $owner, $members['وحید']);

    expect(fn () => financeConsume($room, $owner, $purchase, $members['امیر'], 500))
        ->toThrow(ValidationException::class);
    expect(ConsumptionEntry::query()->count())->toBe(0);
});

it('keeps the orange purchase descriptive quantity without applying it as inventory', function () {
    [$owner, $room, $members] = financeFixture();
    $purchase = financeShared($room, $owner, $members['مهدی'], [$members['محمدرضا'], $members['امیر'], $members['رعنا']]);

    expect($purchase->total_quantity_milliunits)->toBe(1000)
        ->and($purchase->kind->value)->toBe('shared')
        ->and($purchase->consumptions()->count())->toBe(0);
});

it('seeds the nine-member demo room and remains idempotent on a second run', function () {
    $this->seed(DemoRoomSeeder::class);
    $this->seed(DemoRoomSeeder::class);

    $owner = User::query()->where('email', 'owner@example.com')->firstOrFail();
    $room = Room::query()->where('owner_user_id', $owner->id)->where('name', 'اتاق ما')->firstOrFail();
    $members = $room->members()->get()->keyBy('display_name');

    expect($members)->toHaveCount(9)
        ->and($room->purchases()->count())->toBe(3)
        ->and(ConsumptionEntry::query()->where('room_id', $room->id)->count())->toBe(3)
        ->and(financePair($room, $members['مهدی'], $members['وحید'])['amount_toman'])->toBe(23333)
        ->and(Hash::check('password', $owner->password))->toBeTrue();
});

it('registers an owner, starts a session, and supports logout', function () {
    $this->postJson('/register', [
        'name' => 'مدیر تازه',
        'email' => 'new-owner@example.com',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
    ])->assertCreated()->assertJsonPath('data.user.email', 'new-owner@example.com');

    $this->assertAuthenticated();
    $this->withHeader('Origin', 'http://localhost:8000')->getJson('/api/user')->assertOk()->assertJsonPath('data.user.name', 'مدیر تازه');
    $this->postJson('/logout')->assertOk();
    $this->assertGuest('web');
    Auth::forgetGuards(); // A production HTTP request gets a fresh Sanctum request guard.
    $this->withHeader('Origin', 'http://localhost:8000')->getJson('/api/user')->assertUnauthorized();
});

it('authenticates an owner with the correct password and rejects an incorrect password', function () {
    $owner = User::factory()->create(['email' => 'owner-login@example.com', 'password' => 'secret-password']);
    $this->postJson('/login', ['email' => $owner->email, 'password' => 'wrong-password'])->assertUnprocessable();
    $this->postJson('/login', ['email' => $owner->email, 'password' => 'secret-password'])->assertOk();
    $this->assertAuthenticatedAs($owner);
});

it('creates an owner room and its representative room member atomically', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner)->postJson('/api/v1/rooms', [
        'name' => 'اتاق آزمایشی',
        'description' => 'توضیح اتاق',
    ])->assertCreated()->assertJsonPath('data.currency', 'TOMAN')->assertJsonPath('data.members_count', 1);

    $this->assertDatabaseHas('rooms', ['owner_user_id' => $owner->id, 'name' => 'اتاق آزمایشی']);
    $this->assertDatabaseHas('room_members', ['user_id' => $owner->id, 'role' => 'owner', 'display_name' => $owner->name]);
});

it('adds and deactivates a room member without deleting the member record', function () {
    [$owner, $room] = financeFixture();
    $response = $this->actingAs($owner)->postJson("/api/v1/rooms/{$room->id}/members", ['display_name' => 'عضو تازه'])
        ->assertCreated()->assertJsonPath('data.display_name', 'عضو تازه');

    $memberId = $response->json('data.id');
    $this->patchJson("/api/v1/rooms/{$room->id}/members/{$memberId}", ['is_active' => false])
        ->assertOk()->assertJsonPath('data.is_active', false);
    $this->assertDatabaseHas('room_members', ['id' => $memberId, 'is_active' => false]);
});
