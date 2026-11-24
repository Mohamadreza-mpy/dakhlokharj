<?php

namespace App\Services;

use App\Enums\PurchaseKind;
use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use App\Models\Room;
use App\Models\RoomMember;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    public function __construct(private readonly CostAllocationService $costAllocationService) {}

    public function create(Room $room, User $actor, array $data): Purchase
    {
        $hash = $this->payloadHash($this->normalizedPurchasePayload($data));

        return DB::transaction(function () use ($room, $actor, $data, $hash): Purchase {
            Room::query()->whereKey($room->id)->lockForUpdate()->firstOrFail();

            $existing = Purchase::query()
                ->where('room_id', $room->id)
                ->where('request_id', $data['request_id'])
                ->first();

            if ($existing !== null) {
                $this->assertSamePayload($existing->payload_hash, $hash);

                return $this->loadPurchase($existing);
            }

            $payer = $this->activeMembers($room, [(int) $data['payer_member_id']])->first();

            if ($payer === null) {
                throw ValidationException::withMessages([
                    'payer_member_id' => ['خریدار باید عضو فعال همین اتاق باشد.'],
                ]);
            }

            $kind = PurchaseKind::from($data['kind']);
            $allocationRows = [];

            if ($kind === PurchaseKind::Inventory) {
                $unitLabel = $data['unit_label'];
                $quantity = (int) $data['total_quantity_milliunits'];

                if (in_array($unitLabel, ['عدد', 'بسته'], true) && $quantity % 1000 !== 0) {
                    throw ValidationException::withMessages([
                        'total_quantity_milliunits' => ['برای واحد عدد و بسته، مقدار باید کامل باشد.'],
                    ]);
                }
            } else {
                $allocationRows = $this->sharedAllocations($room, $data);
            }

            $purchase = Purchase::query()->create([
                'room_id' => $room->id,
                'payer_member_id' => $payer->id,
                'title' => $data['title'],
                'kind' => $kind,
                'amount_toman' => (int) $data['amount_toman'],
                'unit_label' => $data['unit_label'] ?? null,
                'total_quantity_milliunits' => $data['total_quantity_milliunits'] ?? null,
                'share_mode' => $kind === PurchaseKind::Shared ? $data['share_mode'] : null,
                'purchased_at' => Date::parse($data['purchased_at'])->setTimezone(config('app.timezone')),
                'note' => $data['note'] ?? null,
                'status' => PurchaseStatus::Posted,
                'created_by_user_id' => $actor->id,
                'request_id' => $data['request_id'],
                'payload_hash' => $hash,
            ]);

            if ($allocationRows !== []) {
                $purchase->allocations()->createMany($allocationRows);
            }

            return $this->loadPurchase($purchase);
        }, attempts: 3);
    }

    public function void(Room $room, Purchase $purchase, User $actor, string $requestId, ?string $note = null): Purchase
    {
        return DB::transaction(function () use ($room, $purchase, $actor, $requestId, $note): Purchase {
            Room::query()->whereKey($room->id)->lockForUpdate()->firstOrFail();

            $lockedPurchase = Purchase::query()
                ->where('room_id', $room->id)
                ->whereKey($purchase->id)
                ->lockForUpdate()
                ->firstOrFail();
            $hash = $this->payloadHash(['purchase_id' => $lockedPurchase->id, 'operation' => 'void', 'note' => $note]);

            if ($lockedPurchase->void_request_id !== null) {
                if ($lockedPurchase->void_request_id !== $requestId) {
                    abort(409, 'این خرید قبلاً ابطال شده است.');
                }

                $this->assertSamePayload((string) $lockedPurchase->void_payload_hash, $hash);

                return $this->loadPurchase($lockedPurchase);
            }

            if ($lockedPurchase->status !== PurchaseStatus::Posted) {
                abort(409, 'این خرید قابل ابطال نیست.');
            }

            if ($lockedPurchase->kind === PurchaseKind::Inventory) {
                $netQuantity = (int) $lockedPurchase->consumptions()->sum('quantity_milliunits');

                if ($netQuantity !== 0) {
                    abort(409, 'ابتدا مصرف‌های این خرید را برگشت بزنید.');
                }
            }

            $lockedPurchase->forceFill([
                'status' => PurchaseStatus::Voided,
                'voided_at' => now(),
                'void_note' => $note,
                'voided_by_user_id' => $actor->id,
                'void_request_id' => $requestId,
                'void_payload_hash' => $hash,
            ])->save();

            return $this->loadPurchase($lockedPurchase->fresh());
        }, attempts: 3);
    }

    private function loadPurchase(Purchase $purchase): Purchase
    {
        return $purchase->load([
            'payer:id,room_id,display_name,is_active',
            'allocations.roomMember:id,room_id,display_name,is_active',
            'consumptions.consumer:id,room_id,display_name,is_active',
        ])->loadSum('consumptions as consumed_quantity_milliunits', 'quantity_milliunits');
    }

    /** @return Collection<int, RoomMember> */
    private function activeMembers(Room $room, array $memberIds): Collection
    {
        return $room->members()
            ->where('is_active', true)
            ->whereIn('id', array_values(array_unique($memberIds)))
            ->orderBy('id')
            ->get();
    }

    /** @return array<int, array{room_member_id: int, amount_toman: int}> */
    private function sharedAllocations(Room $room, array $data): array
    {
        if ($data['share_mode'] === 'equal') {
            $memberIds = array_map('intval', $data['participants']);
            $members = $this->activeMembers($room, $memberIds);

            if ($members->count() !== count($memberIds)) {
                throw ValidationException::withMessages([
                    'participants' => ['همه افراد انتخاب‌شده باید عضو فعال همین اتاق باشند.'],
                ]);
            }

            return $this->costAllocationService->allocateEqually((int) $data['amount_toman'], $memberIds);
        }

        $allocations = collect($data['allocations'])
            ->map(fn (array $allocation): array => [
                'room_member_id' => (int) $allocation['room_member_id'],
                'amount_toman' => (int) $allocation['amount_toman'],
            ])
            ->sortBy('room_member_id')
            ->values();
        $memberIds = $allocations->pluck('room_member_id')->all();
        $members = $this->activeMembers($room, $memberIds);

        if ($members->count() !== count($memberIds)) {
            throw ValidationException::withMessages([
                'allocations' => ['همه افراد انتخاب‌شده باید عضو فعال همین اتاق باشند.'],
            ]);
        }

        if ($allocations->sum('amount_toman') !== (int) $data['amount_toman']) {
            throw ValidationException::withMessages([
                'allocations' => ['جمع سهم‌ها باید دقیقاً برابر مبلغ کل خرید باشد.'],
            ]);
        }

        return $allocations->all();
    }

    private function normalizedPurchasePayload(array $data): array
    {
        foreach (['participants', 'allocations'] as $key) {
            if (! isset($data[$key]) || ! is_array($data[$key])) {
                continue;
            }

            if ($key === 'participants') {
                $data[$key] = array_map('intval', $data[$key]);
                sort($data[$key], SORT_NUMERIC);
            } else {
                $data[$key] = collect($data[$key])->sortBy('room_member_id')->values()->all();
            }
        }

        unset($data['request_id']);

        return $data;
    }

    private function payloadHash(array $payload): string
    {
        ksort($payload);

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function assertSamePayload(string $existingHash, string $payloadHash): void
    {
        if (! hash_equals($existingHash, $payloadHash)) {
            abort(409, 'شناسه درخواست قبلاً با اطلاعات دیگری استفاده شده است.');
        }
    }
}
