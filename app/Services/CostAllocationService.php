<?php

namespace App\Services;

use App\Models\Purchase;
use Illuminate\Validation\ValidationException;

class CostAllocationService
{
    /**
     * @param  array<int, int>  $memberIds
     * @return array<int, array{room_member_id: int, amount_toman: int}>
     */
    public function allocateEqually(int $amountToman, array $memberIds): array
    {
        $memberIds = array_values(array_unique(array_map('intval', $memberIds)));
        sort($memberIds, SORT_NUMERIC);

        if ($amountToman < 1 || $memberIds === []) {
            throw ValidationException::withMessages([
                'participants' => ['برای تقسیم هزینه، حداقل یک عضو و مبلغ مثبت لازم است.'],
            ]);
        }

        $baseAmount = intdiv($amountToman, count($memberIds));
        $remainder = $amountToman % count($memberIds);

        return array_map(
            static fn (int $memberId, int $index): array => [
                'room_member_id' => $memberId,
                'amount_toman' => $baseAmount + ($index < $remainder ? 1 : 0),
            ],
            $memberIds,
            array_keys($memberIds),
        );
    }

    public function allocateConsumption(Purchase $purchase, int $previousQuantity, int $previousAmount, int $newQuantity): int
    {
        $totalQuantity = (int) $purchase->total_quantity_milliunits;
        $cumulativeQuantity = $previousQuantity + $newQuantity;

        if ($totalQuantity < 1 || $cumulativeQuantity > $totalQuantity) {
            throw ValidationException::withMessages([
                'quantity_milliunits' => ['مقدار مصرف از موجودی باقی‌مانده بیشتر است.'],
            ]);
        }

        $cumulativeAmount = intdiv((int) $purchase->amount_toman * $cumulativeQuantity, $totalQuantity);

        return $cumulativeAmount - $previousAmount;
    }
}
