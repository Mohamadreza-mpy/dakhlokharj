<?php

use App\Models\Room;
use App\Models\RoomMember;
use App\Models\User;
use App\Services\BalanceService;
use App\Services\ConsumptionService;
use App\Services\PurchaseService;
use Illuminate\Support\Str;

function financeFixture(): array
{
    $owner = User::factory()->create();
    $room = Room::query()->create([
        'owner_user_id' => $owner->id,
        'name' => 'اتاق تست',
        'currency' => 'TOMAN',
    ]);

    $members = collect(['وحید', 'محمدرضا', 'امیر', 'مهدی', 'رعنا', 'علی', 'رضا', 'حسین', 'نیما'])
        ->mapWithKeys(function (string $name, int $index) use ($room, $owner): array {
            $member = RoomMember::query()->create([
                'room_id' => $room->id,
                'user_id' => $index === 0 ? $owner->id : null,
                'display_name' => $name,
                'role' => $index === 0 ? 'owner' : 'member',
                'is_active' => true,
            ]);

            return [$name => $member];
        });

    return [$owner, $room, $members];
}

function financeInventory(Room $room, User $actor, RoomMember $payer, int $amount = 500000, int $quantity = 30000, string $unit = 'عدد', ?string $requestId = null)
{
    return app(PurchaseService::class)->create($room, $actor, [
        'request_id' => $requestId ?? (string) Str::uuid(),
        'payer_member_id' => $payer->id,
        'title' => 'تخم‌مرغ',
        'kind' => 'inventory',
        'amount_toman' => $amount,
        'unit_label' => $unit,
        'total_quantity_milliunits' => $quantity,
        'purchased_at' => '2026-10-12T12:00:00+03:30',
    ]);
}

function financeConsume(Room $room, User $actor, $purchase, RoomMember $consumer, int $quantity, ?string $requestId = null)
{
    return app(ConsumptionService::class)->create($room, $purchase, $actor, [
        'request_id' => $requestId ?? (string) Str::uuid(),
        'consumer_member_id' => $consumer->id,
        'quantity_milliunits' => $quantity,
        'consumed_at' => '2026-10-12T14:00:00+03:30',
    ]);
}

function financeShared(Room $room, User $actor, RoomMember $payer, array $participants, int $amount = 30000, ?string $requestId = null, ?array $allocations = null)
{
    $data = [
        'request_id' => $requestId ?? (string) Str::uuid(),
        'payer_member_id' => $payer->id,
        'title' => 'خرج مشترک',
        'kind' => 'shared',
        'amount_toman' => $amount,
        'unit_label' => 'کیلوگرم',
        'total_quantity_milliunits' => 1000,
        'share_mode' => $allocations === null ? 'equal' : 'custom',
        'purchased_at' => '2026-10-12T15:00:00+03:30',
    ];

    if ($allocations === null) {
        $data['participants'] = array_map(static fn (RoomMember $member): int => (int) $member->id, $participants);
    } else {
        $data['allocations'] = $allocations;
    }

    return app(PurchaseService::class)->create($room, $actor, $data);
}

function financePair(Room $room, RoomMember $debtor, RoomMember $creditor): ?array
{
    return collect(app(BalanceService::class)->forRoom($room))
        ->first(fn (array $row): bool => $row['debtor_member_id'] === $debtor->id && $row['creditor_member_id'] === $creditor->id);
}
