<?php

namespace App\Services;

use App\Enums\ConsumptionEntryType;
use App\Enums\PurchaseKind;
use App\Enums\PurchaseStatus;
use App\Models\ConsumptionEntry;
use App\Models\Purchase;
use App\Models\Room;
use App\Models\RoomMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConsumptionService
{
    public function __construct(private readonly CostAllocationService $costAllocationService) {}

    public function create(Room $room, Purchase $purchase, User $actor, array $data): ConsumptionEntry
    {
        $hash = $this->hash($this->normalized($data + ['purchase_id' => $purchase->id]));

        return DB::transaction(function () use ($room, $purchase, $actor, $data, $hash): ConsumptionEntry {
            Room::query()->whereKey($room->id)->lockForUpdate()->firstOrFail();

            $existing = ConsumptionEntry::query()
                ->where('room_id', $room->id)
                ->where('request_id', $data['request_id'])
                ->first();

            if ($existing !== null) {
                $this->assertSamePayload($existing->payload_hash, $hash);

                return $existing->load(['consumer:id,display_name', 'purchase:id,title,unit_label,total_quantity_milliunits']);
            }

            $lockedPurchase = Purchase::query()
                ->where('room_id', $room->id)
                ->whereKey($purchase->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPurchase->kind !== PurchaseKind::Inventory || $lockedPurchase->status !== PurchaseStatus::Posted) {
                abort(409, 'برای این خرید امکان ثبت مصرف وجود ندارد.');
            }

            $consumer = RoomMember::query()
                ->where('room_id', $room->id)
                ->where('is_active', true)
                ->whereKey($data['consumer_member_id'])
                ->first();

            if ($consumer === null) {
                throw ValidationException::withMessages([
                    'consumer_member_id' => ['مصرف‌کننده باید عضو فعال همین اتاق باشد.'],
                ]);
            }

            $previousQuantity = (int) $lockedPurchase->consumptions()->sum('quantity_milliunits');
            $previousAmount = (int) $lockedPurchase->consumptions()->sum('allocated_amount_toman');
            $newQuantity = (int) $data['quantity_milliunits'];
            $remainingBefore = (int) $lockedPurchase->total_quantity_milliunits - $previousQuantity;

            if (in_array($lockedPurchase->unit_label, ['عدد', 'بسته'], true) && $newQuantity % 1000 !== 0) {
                throw ValidationException::withMessages([
                    'quantity_milliunits' => ['برای واحد عدد و بسته، مقدار مصرف باید کامل باشد.'],
                ]);
            }

            if ($newQuantity > $remainingBefore) {
                throw ValidationException::withMessages([
                    'quantity_milliunits' => ['مقدار مصرف از موجودی باقی‌مانده بیشتر است.'],
                ]);
            }

            $allocatedAmount = $this->costAllocationService->allocateConsumption(
                $lockedPurchase,
                $previousQuantity,
                $previousAmount,
                $newQuantity,
            );
            $remainingAfter = $remainingBefore - $newQuantity;

            return $lockedPurchase->consumptions()->create([
                'room_id' => $room->id,
                'consumer_member_id' => $consumer->id,
                'quantity_milliunits' => $newQuantity,
                'allocated_amount_toman' => $allocatedAmount,
                'entry_type' => ConsumptionEntryType::Consume,
                'consumed_at' => $data['consumed_at'],
                'note' => $data['note'] ?? null,
                'created_by_user_id' => $actor->id,
                'request_id' => $data['request_id'],
                'payload_hash' => $hash,
                'result_remaining_quantity_milliunits' => $remainingAfter,
            ])->load(['consumer:id,display_name', 'purchase:id,title,unit_label,total_quantity_milliunits']);
        }, attempts: 3);
    }

    public function reverse(Room $room, ConsumptionEntry $entry, User $actor, string $requestId, ?string $note = null): ConsumptionEntry
    {
        return DB::transaction(function () use ($room, $entry, $actor, $requestId, $note): ConsumptionEntry {
            Room::query()->whereKey($room->id)->lockForUpdate()->firstOrFail();
            $original = ConsumptionEntry::query()
                ->where('room_id', $room->id)
                ->whereKey($entry->id)
                ->lockForUpdate()
                ->firstOrFail();
            $hash = $this->hash(['entry_id' => $original->id, 'note' => $note, 'operation' => 'reverse']);

            $existingRequest = ConsumptionEntry::query()
                ->where('room_id', $room->id)
                ->where('request_id', $requestId)
                ->first();

            if ($existingRequest !== null) {
                $this->assertSamePayload($existingRequest->payload_hash, $hash);

                return $existingRequest->load(['consumer:id,display_name', 'purchase:id,title,unit_label,total_quantity_milliunits']);
            }

            if ($original->entry_type !== ConsumptionEntryType::Consume) {
                abort(409, 'فقط مصرف اصلی قابل برگشت است.');
            }

            if ($original->reversal()->exists()) {
                abort(409, 'این مصرف قبلاً برگشت خورده است.');
            }

            $lockedPurchase = Purchase::query()
                ->where('room_id', $room->id)
                ->whereKey($original->purchase_id)
                ->lockForUpdate()
                ->firstOrFail();
            $netQuantity = (int) $lockedPurchase->consumptions()->sum('quantity_milliunits') - (int) $original->quantity_milliunits;
            $remainingAfter = (int) $lockedPurchase->total_quantity_milliunits - $netQuantity;

            return $lockedPurchase->consumptions()->create([
                'room_id' => $room->id,
                'consumer_member_id' => $original->consumer_member_id,
                'quantity_milliunits' => -((int) $original->quantity_milliunits),
                'allocated_amount_toman' => -((int) $original->allocated_amount_toman),
                'entry_type' => ConsumptionEntryType::Reversal,
                'reverses_entry_id' => $original->id,
                'consumed_at' => now(),
                'note' => $note,
                'created_by_user_id' => $actor->id,
                'request_id' => $requestId,
                'payload_hash' => $hash,
                'result_remaining_quantity_milliunits' => $remainingAfter,
            ])->load(['consumer:id,display_name', 'purchase:id,title,unit_label,total_quantity_milliunits']);
        }, attempts: 3);
    }

    private function normalized(array $data): array
    {
        unset($data['request_id']);
        ksort($data);

        return $data;
    }

    private function hash(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function assertSamePayload(string $existingHash, string $payloadHash): void
    {
        if (! hash_equals($existingHash, $payloadHash)) {
            abort(409, 'شناسه درخواست قبلاً با اطلاعات دیگری استفاده شده است.');
        }
    }
}
