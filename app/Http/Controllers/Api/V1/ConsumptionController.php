<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PurchaseKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReverseFinancialEntryRequest;
use App\Http\Requests\StoreConsumptionRequest;
use App\Models\ConsumptionEntry;
use App\Models\Room;
use App\Services\ConsumptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConsumptionController extends Controller
{
    public function index(Request $request, Room $room, int $purchase): JsonResponse
    {
        $purchaseModel = $room->purchases()->whereKey($purchase)->firstOrFail();
        abort_unless($purchaseModel->kind === PurchaseKind::Inventory, 404);
        $filters = $request->validate([
            'consumer_member_id' => [
                'nullable',
                'integer',
                Rule::exists('room_members', 'id')->where('room_id', $room->id),
            ],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], [
            'consumer_member_id.exists' => 'مصرف‌کننده باید عضو همین اتاق باشد.',
            'per_page.integer' => 'تعداد ردیف در صفحه معتبر نیست.',
            'per_page.min' => 'تعداد ردیف در صفحه باید حداقل یک باشد.',
            'per_page.max' => 'تعداد ردیف در صفحه نمی‌تواند از ۱۰۰ بیشتر باشد.',
        ]);

        $entries = $purchaseModel->consumptions()
            ->with('consumer:id,display_name')
            ->when(isset($filters['consumer_member_id']), fn ($query) => $query->where('consumer_member_id', $filters['consumer_member_id']))
            ->orderByDesc('consumed_at')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 20));

        $entries->through(fn (ConsumptionEntry $entry): array => [
            ...$this->present($entry),
            'created_at' => $entry->created_at->toIso8601String(),
        ]);

        return response()->json($entries);
    }

    public function store(StoreConsumptionRequest $request, Room $room, int $purchase, ConsumptionService $service): JsonResponse
    {
        $purchaseModel = $room->purchases()->whereKey($purchase)->firstOrFail();
        $entry = $service->create($room, $purchaseModel, $request->user(), $request->validated());

        return response()->json(['data' => $this->present($entry)], 201);
    }

    public function reverse(ReverseFinancialEntryRequest $request, Room $room, int $consumption, ConsumptionService $service): JsonResponse
    {
        $entry = ConsumptionEntry::query()
            ->where('room_id', $room->id)
            ->whereKey($consumption)
            ->firstOrFail();
        $reversal = $service->reverse(
            $room,
            $entry,
            $request->user(),
            $request->validated('request_id'),
            $request->validated('note'),
        );

        return response()->json(['data' => $this->present($reversal)]);
    }

    /** @return array<string, mixed> */
    private function present(ConsumptionEntry $entry): array
    {
        return [
            'id' => (int) $entry->id,
            'purchase_id' => (int) $entry->purchase_id,
            'consumer_member_id' => (int) $entry->consumer_member_id,
            'consumer_name' => $entry->consumer->display_name,
            'quantity_milliunits' => (int) $entry->quantity_milliunits,
            'allocated_amount_toman' => (int) $entry->allocated_amount_toman,
            'entry_type' => $entry->entry_type->value,
            'reverses_entry_id' => $entry->reverses_entry_id,
            'remaining_quantity_milliunits' => (int) ($entry->result_remaining_quantity_milliunits ?? 0),
            'consumed_at' => $entry->consumed_at->toIso8601String(),
            'note' => $entry->note,
        ];
    }
}
