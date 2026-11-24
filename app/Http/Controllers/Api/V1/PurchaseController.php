<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseRequest;
use App\Http\Requests\StorePurchaseVoidRequest;
use App\Http\Resources\PurchaseResource;
use App\Models\Room;
use App\Services\PurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class PurchaseController extends Controller
{
    public function index(Request $request, Room $room): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'kind' => ['nullable', Rule::in(['inventory', 'shared'])],
            'status' => ['nullable', Rule::in(['posted', 'voided'])],
            'payer_member_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], [
            'kind.in' => 'نوع خرید معتبر نیست.',
            'status.in' => 'وضعیت خرید معتبر نیست.',
            'from.date' => 'تاریخ شروع معتبر نیست.',
            'to.date' => 'تاریخ پایان معتبر نیست.',
        ]);

        $purchases = $room->purchases()
            ->with(['payer:id,display_name', 'allocations.roomMember:id,display_name'])
            ->withSum('consumptions as consumed_quantity_milliunits', 'quantity_milliunits')
            ->when(isset($filters['kind']), fn ($query) => $query->where('kind', $filters['kind']))
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['payer_member_id']), fn ($query) => $query->where('payer_member_id', $filters['payer_member_id']))
            ->when(isset($filters['from']), fn ($query) => $query->whereDate('purchased_at', '>=', $filters['from']))
            ->when(isset($filters['to']), fn ($query) => $query->whereDate('purchased_at', '<=', $filters['to']))
            ->orderByDesc('purchased_at')
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 20));

        return PurchaseResource::collection($purchases);
    }

    public function store(StorePurchaseRequest $request, Room $room, PurchaseService $purchaseService): JsonResponse
    {
        $purchase = $purchaseService->create($room, $request->user(), $request->validated());

        return (new PurchaseResource($purchase))->response()->setStatusCode(201);
    }

    public function show(Room $room, int $purchase): PurchaseResource
    {
        $purchase = $room->purchases()
            ->with([
                'payer:id,display_name',
                'allocations.roomMember:id,display_name',
                'consumptions.consumer:id,display_name',
            ])
            ->withSum('consumptions as consumed_quantity_milliunits', 'quantity_milliunits')
            ->whereKey($purchase)
            ->firstOrFail();

        return new PurchaseResource($purchase);
    }

    public function void(StorePurchaseVoidRequest $request, Room $room, int $purchase, PurchaseService $purchaseService): PurchaseResource
    {
        $purchaseModel = $room->purchases()->whereKey($purchase)->firstOrFail();
        $purchase = $purchaseService->void(
            $room,
            $purchaseModel,
            $request->user(),
            $request->validated('request_id'),
            $request->validated('note'),
        );

        return new PurchaseResource($purchase);
    }
}
