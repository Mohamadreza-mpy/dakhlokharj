<?php

namespace App\Services;

use App\Enums\PurchaseKind;
use App\Enums\PurchaseStatus;
use App\Models\ConsumptionEntry;
use App\Models\Room;
use App\Models\RoomMember;
use App\Models\Settlement;
use App\Models\SharedAllocation;
use Illuminate\Support\Facades\DB;

class BalanceService
{
    /** @return array<int, array<string, int|string|bool>> */
    public function forRoom(Room $room, ?int $memberId = null): array
    {
        $members = $room->members()->orderBy('id')->get(['id', 'display_name', 'is_active']);
        $memberNames = $members->keyBy('id')->map(fn (RoomMember $member): string => $member->display_name)->all();
        $rawObligations = $this->rawObligations($room);
        $payments = $this->payments($room);
        $balances = [];

        for ($firstIndex = 0; $firstIndex < $members->count(); $firstIndex++) {
            for ($secondIndex = $firstIndex + 1; $secondIndex < $members->count(); $secondIndex++) {
                $firstId = (int) $members[$firstIndex]->id;
                $secondId = (int) $members[$secondIndex]->id;
                $firstOwesSecond = $rawObligations[$firstId][$secondId] ?? 0;
                $secondOwesFirst = $rawObligations[$secondId][$firstId] ?? 0;
                $firstPaidSecond = $payments[$firstId][$secondId] ?? 0;
                $secondPaidFirst = $payments[$secondId][$firstId] ?? 0;
                $netFirstOwesSecond = $firstOwesSecond - $secondOwesFirst - $firstPaidSecond + $secondPaidFirst;

                if ($netFirstOwesSecond === 0) {
                    continue;
                }

                $debtorId = $netFirstOwesSecond > 0 ? $firstId : $secondId;
                $creditorId = $netFirstOwesSecond > 0 ? $secondId : $firstId;
                $balances[] = [
                    'debtor_member_id' => $debtorId,
                    'debtor_name' => $memberNames[$debtorId],
                    'creditor_member_id' => $creditorId,
                    'creditor_name' => $memberNames[$creditorId],
                    'amount_toman' => abs($netFirstOwesSecond),
                    'gross_debtor_to_creditor_toman' => $rawObligations[$debtorId][$creditorId] ?? 0,
                    'gross_creditor_to_debtor_toman' => $rawObligations[$creditorId][$debtorId] ?? 0,
                    'net_settlements_effect_toman' => ($payments[$debtorId][$creditorId] ?? 0) - ($payments[$creditorId][$debtorId] ?? 0),
                    'debtor_is_active' => (bool) $members->firstWhere('id', $debtorId)->is_active,
                    'creditor_is_active' => (bool) $members->firstWhere('id', $creditorId)->is_active,
                ];
            }
        }

        if ($memberId !== null) {
            $balances = array_values(array_filter(
                $balances,
                static fn (array $balance): bool => $balance['debtor_member_id'] === $memberId || $balance['creditor_member_id'] === $memberId,
            ));
        }

        usort($balances, static fn (array $left, array $right): int => [$left['debtor_member_id'], $left['creditor_member_id']] <=> [$right['debtor_member_id'], $right['creditor_member_id']]);

        return $balances;
    }

    /** @return array<string, int|array<int, array<string, int|string|bool>>> */
    public function memberSummary(Room $room, RoomMember $member): array
    {
        $balances = $this->forRoom($room, (int) $member->id);
        $owed = array_sum(array_map(
            static fn (array $balance): int => $balance['debtor_member_id'] === $member->id ? (int) $balance['amount_toman'] : 0,
            $balances,
        ));
        $receivable = array_sum(array_map(
            static fn (array $balance): int => $balance['creditor_member_id'] === $member->id ? (int) $balance['amount_toman'] : 0,
            $balances,
        ));

        return [
            'member_id' => (int) $member->id,
            'member_name' => $member->display_name,
            'total_owed_toman' => $owed,
            'total_receivable_toman' => $receivable,
            'net_position_toman' => $receivable - $owed,
            'balances' => $balances,
        ];
    }

    public function amountOwed(Room $room, int $debtorId, int $creditorId): int
    {
        foreach ($this->forRoom($room) as $balance) {
            if ($balance['debtor_member_id'] === $debtorId && $balance['creditor_member_id'] === $creditorId) {
                return (int) $balance['amount_toman'];
            }
        }

        return 0;
    }

    /** @return array<int, array<string, int|string>> */
    public function ledger(Room $room, RoomMember $member): array
    {
        $events = [];

        $allocations = SharedAllocation::query()
            ->whereHas('purchase', fn ($query) => $query
                ->where('room_id', $room->id)
                ->where('kind', PurchaseKind::Shared)
                ->where('status', PurchaseStatus::Posted))
            ->where(function ($query) use ($member): void {
                $query->where('room_member_id', $member->id)
                    ->orWhereHas('purchase', fn ($purchaseQuery) => $purchaseQuery->where('payer_member_id', $member->id));
            })
            ->with(['roomMember:id,display_name', 'purchase:id,room_id,payer_member_id,title,purchased_at', 'purchase.payer:id,display_name'])
            ->orderBy('id')
            ->get();

        foreach ($allocations as $allocation) {
            $purchase = $allocation->purchase;
            $participantId = (int) $allocation->room_member_id;
            $payerId = (int) $purchase->payer_member_id;

            if ($participantId === $payerId) {
                continue;
            }

            $isDebtor = $member->id === $participantId;
            if (! $isDebtor && $member->id !== $payerId) {
                continue;
            }

            $otherId = $isDebtor ? $payerId : $participantId;
            $amount = (int) $allocation->amount_toman;
            $events[] = [
                'sort_at' => $allocation->created_at->getTimestamp(),
                'sort_id' => 'allocation-'.$allocation->id,
                'occurred_at' => $purchase->purchased_at->toIso8601String(),
                'type' => 'shared',
                'description' => 'سهم از خرید مشترک '.$purchase->title,
                'other_member_id' => $otherId,
                'other_member_name' => $isDebtor ? $purchase->payer->display_name : $allocation->roomMember->display_name,
                'owes_to_counterparty_toman' => $isDebtor ? $amount : 0,
                'receivable_from_counterparty_toman' => $isDebtor ? 0 : $amount,
                'payment_toman' => 0,
                'effect_on_balance_toman' => $isDebtor ? $amount : -$amount,
                'recorded_at' => $allocation->created_at->toIso8601String(),
            ];
        }

        $consumptions = ConsumptionEntry::query()
            ->whereHas('purchase', fn ($query) => $query
                ->where('room_id', $room->id)
                ->where('kind', PurchaseKind::Inventory)
                ->where('status', PurchaseStatus::Posted))
            ->where(function ($query) use ($member): void {
                $query->where('consumer_member_id', $member->id)
                    ->orWhereHas('purchase', fn ($purchaseQuery) => $purchaseQuery->where('payer_member_id', $member->id));
            })
            ->with(['consumer:id,display_name', 'purchase:id,room_id,payer_member_id,title,unit_label,purchased_at', 'purchase.payer:id,display_name'])
            ->orderBy('id')
            ->get();

        foreach ($consumptions as $consumption) {
            $purchase = $consumption->purchase;
            $consumerId = (int) $consumption->consumer_member_id;
            $payerId = (int) $purchase->payer_member_id;

            if ($consumerId === $payerId || ($member->id !== $consumerId && $member->id !== $payerId)) {
                continue;
            }

            $isDebtor = $member->id === $consumerId;
            $amount = (int) $consumption->allocated_amount_toman;
            $quantity = abs((int) $consumption->quantity_milliunits);
            $events[] = [
                'sort_at' => $consumption->created_at->getTimestamp(),
                'sort_id' => 'consumption-'.$consumption->id,
                'occurred_at' => $consumption->consumed_at->toIso8601String(),
                'type' => $consumption->entry_type->value,
                'description' => ($consumption->entry_type->value === 'reversal' ? 'برگشت مصرف ' : 'مصرف ')
                    .$quantity.' هزارم '.$purchase->unit_label.' از '.$purchase->title,
                'other_member_id' => $isDebtor ? $payerId : $consumerId,
                'other_member_name' => $isDebtor ? $purchase->payer->display_name : $consumption->consumer->display_name,
                'owes_to_counterparty_toman' => $isDebtor ? $amount : 0,
                'receivable_from_counterparty_toman' => $isDebtor ? 0 : $amount,
                'payment_toman' => 0,
                'effect_on_balance_toman' => $isDebtor ? $amount : -$amount,
                'recorded_at' => $consumption->created_at->toIso8601String(),
            ];
        }

        $settlements = Settlement::query()
            ->where('room_id', $room->id)
            ->where(function ($query) use ($member): void {
                $query->where('from_member_id', $member->id)->orWhere('to_member_id', $member->id);
            })
            ->with(['fromMember:id,display_name', 'toMember:id,display_name'])
            ->orderBy('id')
            ->get();

        foreach ($settlements as $settlement) {
            $isPayer = $settlement->from_member_id === $member->id;
            $other = $isPayer ? $settlement->toMember : $settlement->fromMember;
            $amount = (int) $settlement->amount_toman;
            $effect = $isPayer ? -$amount : $amount;
            $events[] = [
                'sort_at' => $settlement->created_at->getTimestamp(),
                'sort_id' => 'settlement-'.$settlement->id,
                'occurred_at' => $settlement->paid_at->toIso8601String(),
                'type' => $settlement->entry_type->value,
                'description' => $isPayer
                    ? 'پرداخت به '.$other->display_name
                    : 'دریافت از '.$other->display_name,
                'other_member_id' => (int) $other->id,
                'other_member_name' => $other->display_name,
                'owes_to_counterparty_toman' => 0,
                'receivable_from_counterparty_toman' => 0,
                'payment_toman' => $amount,
                'effect_on_balance_toman' => $effect,
                'recorded_at' => $settlement->created_at->toIso8601String(),
            ];
        }

        usort($events, static fn (array $left, array $right): int => [$left['sort_at'], $left['sort_id']] <=> [$right['sort_at'], $right['sort_id']]);

        $runningBalances = [];
        foreach ($events as &$event) {
            $otherId = (int) $event['other_member_id'];
            $runningBalances[$otherId] = ($runningBalances[$otherId] ?? 0) + (int) $event['effect_on_balance_toman'];
            $event['balance_after_toman'] = $runningBalances[$otherId];
            unset($event['sort_at'], $event['sort_id']);
        }
        unset($event);

        return $events;
    }

    /** @return array<int, array<int, int>> */
    private function rawObligations(Room $room): array
    {
        $obligations = [];
        $shared = DB::table('shared_allocations as allocations')
            ->join('purchases', 'purchases.id', '=', 'allocations.purchase_id')
            ->where('purchases.room_id', $room->id)
            ->where('purchases.kind', PurchaseKind::Shared->value)
            ->where('purchases.status', PurchaseStatus::Posted->value)
            ->select('allocations.room_member_id as debtor_id', 'purchases.payer_member_id as creditor_id')
            ->selectRaw('SUM(allocations.amount_toman) as amount_toman')
            ->groupBy('allocations.room_member_id', 'purchases.payer_member_id')
            ->get();

        foreach ($shared as $row) {
            $this->addObligation($obligations, (int) $row->debtor_id, (int) $row->creditor_id, (int) $row->amount_toman);
        }

        $consumptions = DB::table('consumption_entries as entries')
            ->join('purchases', 'purchases.id', '=', 'entries.purchase_id')
            ->where('entries.room_id', $room->id)
            ->where('purchases.kind', PurchaseKind::Inventory->value)
            ->where('purchases.status', PurchaseStatus::Posted->value)
            ->select('entries.consumer_member_id as debtor_id', 'purchases.payer_member_id as creditor_id')
            ->selectRaw('SUM(entries.allocated_amount_toman) as amount_toman')
            ->groupBy('entries.consumer_member_id', 'purchases.payer_member_id')
            ->get();

        foreach ($consumptions as $row) {
            $this->addObligation($obligations, (int) $row->debtor_id, (int) $row->creditor_id, (int) $row->amount_toman);
        }

        return $obligations;
    }

    /** @return array<int, array<int, int>> */
    private function payments(Room $room): array
    {
        $payments = [];

        foreach ($room->settlements()->get(['from_member_id', 'to_member_id', 'amount_toman']) as $settlement) {
            $fromId = (int) $settlement->from_member_id;
            $toId = (int) $settlement->to_member_id;
            $payments[$fromId][$toId] = ($payments[$fromId][$toId] ?? 0) + (int) $settlement->amount_toman;
        }

        return $payments;
    }

    /** @param array<int, array<int, int>> $obligations */
    private function addObligation(array &$obligations, int $debtorId, int $creditorId, int $amount): void
    {
        if ($debtorId === $creditorId || $amount === 0) {
            return;
        }

        $obligations[$debtorId][$creditorId] = ($obligations[$debtorId][$creditorId] ?? 0) + $amount;
    }
}
