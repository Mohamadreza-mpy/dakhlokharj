<?php

namespace App\Services;

use App\Enums\SettlementEntryType;
use App\Models\Room;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SettlementService
{
    public function __construct(private readonly BalanceService $balanceService) {}

    public function create(Room $room, User $actor, array $data): Settlement
    {
        $hash = $this->hash($this->normalized($data));

        return DB::transaction(function () use ($room, $actor, $data, $hash): Settlement {
            Room::query()->whereKey($room->id)->lockForUpdate()->firstOrFail();

            $existing = Settlement::query()
                ->where('room_id', $room->id)
                ->where('request_id', $data['request_id'])
                ->first();

            if ($existing !== null) {
                $this->assertSamePayload($existing->payload_hash, $hash);

                return $existing->load(['fromMember:id,display_name', 'toMember:id,display_name']);
            }

            $members = $room->members()
                ->whereIn('id', [(int) $data['from_member_id'], (int) $data['to_member_id']])
                ->get(['id', 'is_active']);

            if ($members->count() !== 2 || (int) $data['from_member_id'] === (int) $data['to_member_id']) {
                throw ValidationException::withMessages([
                    'from_member_id' => ['پرداخت‌کننده و دریافت‌کننده باید دو عضو متفاوت همین اتاق باشند.'],
                ]);
            }

            $available = $this->balanceService->amountOwed(
                $room,
                (int) $data['from_member_id'],
                (int) $data['to_member_id'],
            );

            if ($available < 1 || (int) $data['amount_toman'] > $available) {
                throw ValidationException::withMessages([
                    'amount_toman' => ['مبلغ پرداخت نمی‌تواند از مانده بدهی همین جهت بیشتر باشد.'],
                ]);
            }

            return $room->settlements()->create([
                'from_member_id' => (int) $data['from_member_id'],
                'to_member_id' => (int) $data['to_member_id'],
                'amount_toman' => (int) $data['amount_toman'],
                'entry_type' => SettlementEntryType::Payment,
                'paid_at' => $data['paid_at'],
                'note' => $data['note'] ?? null,
                'created_by_user_id' => $actor->id,
                'request_id' => $data['request_id'],
                'payload_hash' => $hash,
            ])->load(['fromMember:id,display_name', 'toMember:id,display_name']);
        }, attempts: 3);
    }

    public function reverse(Room $room, Settlement $settlement, User $actor, string $requestId, ?string $note = null): Settlement
    {
        return DB::transaction(function () use ($room, $settlement, $actor, $requestId, $note): Settlement {
            Room::query()->whereKey($room->id)->lockForUpdate()->firstOrFail();
            $original = Settlement::query()
                ->where('room_id', $room->id)
                ->whereKey($settlement->id)
                ->lockForUpdate()
                ->firstOrFail();
            $hash = $this->hash(['settlement_id' => $original->id, 'note' => $note, 'operation' => 'reverse']);
            $existing = Settlement::query()
                ->where('room_id', $room->id)
                ->where('request_id', $requestId)
                ->first();

            if ($existing !== null) {
                $this->assertSamePayload($existing->payload_hash, $hash);

                return $existing->load(['fromMember:id,display_name', 'toMember:id,display_name']);
            }

            if ($original->entry_type !== SettlementEntryType::Payment) {
                abort(409, 'فقط پرداخت اصلی قابل برگشت است.');
            }

            if ($original->reversal()->exists()) {
                abort(409, 'این پرداخت قبلاً برگشت خورده است.');
            }

            return $room->settlements()->create([
                'from_member_id' => $original->from_member_id,
                'to_member_id' => $original->to_member_id,
                'amount_toman' => -((int) $original->amount_toman),
                'entry_type' => SettlementEntryType::Reversal,
                'reverses_settlement_id' => $original->id,
                'paid_at' => now(),
                'note' => $note,
                'created_by_user_id' => $actor->id,
                'request_id' => $requestId,
                'payload_hash' => $hash,
            ])->load(['fromMember:id,display_name', 'toMember:id,display_name']);
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
