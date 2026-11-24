<?php

namespace App\Services;

use App\Enums\PurchaseKind;
use App\Enums\PurchaseStatus;
use App\Enums\SettlementEntryType;
use App\Models\ConsumptionEntry;
use App\Models\Purchase;
use App\Models\Room;
use App\Models\Settlement;
use Illuminate\Support\Facades\DB;

class RoomDashboardService
{
    public function __construct(private readonly BalanceService $balanceService) {}

    /** @return array<string, mixed> */
    public function summary(Room $room): array
    {
        $postedPurchases = $room->purchases()->where('status', PurchaseStatus::Posted);
        $purchaseTotal = (int) (clone $postedPurchases)->sum('amount_toman');
        $sharedTotal = (int) DB::table('shared_allocations as allocations')
            ->join('purchases', 'purchases.id', '=', 'allocations.purchase_id')
            ->where('purchases.room_id', $room->id)
            ->where('purchases.kind', PurchaseKind::Shared->value)
            ->where('purchases.status', PurchaseStatus::Posted->value)
            ->sum('allocations.amount_toman');
        $consumedTotal = (int) DB::table('consumption_entries as entries')
            ->join('purchases', 'purchases.id', '=', 'entries.purchase_id')
            ->where('entries.room_id', $room->id)
            ->where('purchases.kind', PurchaseKind::Inventory->value)
            ->where('purchases.status', PurchaseStatus::Posted->value)
            ->sum('entries.allocated_amount_toman');
        $settlementQuery = $room->settlements();
        $paymentsCount = (clone $settlementQuery)->where('entry_type', SettlementEntryType::Payment)->count();
        $netPaid = (int) (clone $settlementQuery)->sum('amount_toman');
        $balances = $this->balanceService->forRoom($room);
        $inventory = $this->inventory($room);

        return [
            'room' => $room->only(['id', 'name', 'currency', 'description']),
            'stats' => [
                'purchase_total_toman' => $purchaseTotal,
                'attributed_expense_toman' => $sharedTotal + $consumedTotal,
                'settlements_count' => $paymentsCount,
                'net_settled_toman' => $netPaid,
                'open_accounts_count' => count($balances),
            ],
            'open_balances' => $balances,
            'inventory' => $inventory,
            'recent_activities' => $this->recentActivities($room),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function inventory(Room $room): array
    {
        $consumed = DB::table('consumption_entries')
            ->select('purchase_id')
            ->selectRaw('SUM(quantity_milliunits) as consumed_quantity_milliunits')
            ->groupBy('purchase_id');

        return Purchase::query()
            ->where('room_id', $room->id)
            ->where('kind', PurchaseKind::Inventory)
            ->where('status', PurchaseStatus::Posted)
            ->leftJoinSub($consumed, 'consumed', 'consumed.purchase_id', '=', 'purchases.id')
            ->with('payer:id,display_name')
            ->orderBy('purchased_at')
            ->orderBy('id')
            ->get([
                'purchases.id',
                'purchases.title',
                'purchases.payer_member_id',
                'purchases.unit_label',
                'purchases.total_quantity_milliunits',
                'purchases.amount_toman',
                'purchases.purchased_at',
                DB::raw('COALESCE(consumed.consumed_quantity_milliunits, 0) as consumed_quantity_milliunits'),
            ])
            ->map(function (Purchase $purchase): array {
                $remaining = (int) $purchase->total_quantity_milliunits - (int) $purchase->consumed_quantity_milliunits;

                return [
                    'purchase_id' => (int) $purchase->id,
                    'title' => $purchase->title,
                    'payer_member_id' => (int) $purchase->payer_member_id,
                    'payer_name' => $purchase->payer->display_name,
                    'unit_label' => $purchase->unit_label,
                    'amount_toman' => (int) $purchase->amount_toman,
                    'total_quantity_milliunits' => (int) $purchase->total_quantity_milliunits,
                    'remaining_quantity_milliunits' => $remaining,
                    'purchased_at' => $purchase->purchased_at->toIso8601String(),
                ];
            })
            ->filter(static fn (array $purchase): bool => $purchase['remaining_quantity_milliunits'] > 0)
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function recentActivities(Room $room): array
    {
        $activities = [];

        foreach ($room->purchases()->with('payer:id,display_name')->latest('created_at')->latest('id')->limit(8)->get() as $purchase) {
            $activities[] = [
                'id' => 'purchase-'.$purchase->id,
                'type' => $purchase->status->value === 'voided' ? 'purchase_voided' : 'purchase',
                'description' => $purchase->title.' توسط '.$purchase->payer->display_name,
                'amount_toman' => (int) $purchase->amount_toman,
                'created_at' => $purchase->created_at->toIso8601String(),
            ];
        }

        foreach (ConsumptionEntry::query()
            ->where('room_id', $room->id)
            ->with(['consumer:id,display_name', 'purchase:id,title'])
            ->latest('created_at')
            ->latest('id')
            ->limit(8)
            ->get() as $entry) {
            $activities[] = [
                'id' => 'consumption-'.$entry->id,
                'type' => $entry->entry_type->value,
                'description' => $entry->consumer->display_name.' از '.$entry->purchase->title.' مصرف کرد',
                'amount_toman' => (int) $entry->allocated_amount_toman,
                'created_at' => $entry->created_at->toIso8601String(),
            ];
        }

        foreach (Settlement::query()
            ->where('room_id', $room->id)
            ->with(['fromMember:id,display_name', 'toMember:id,display_name'])
            ->latest('created_at')
            ->latest('id')
            ->limit(8)
            ->get() as $settlement) {
            $activities[] = [
                'id' => 'settlement-'.$settlement->id,
                'type' => $settlement->entry_type->value,
                'description' => $settlement->fromMember->display_name.' به '.$settlement->toMember->display_name.' پرداخت کرد',
                'amount_toman' => (int) $settlement->amount_toman,
                'created_at' => $settlement->created_at->toIso8601String(),
            ];
        }

        usort($activities, static fn (array $left, array $right): int => [$right['created_at'], $right['id']] <=> [$left['created_at'], $left['id']]);

        return array_slice($activities, 0, 12);
    }
}
