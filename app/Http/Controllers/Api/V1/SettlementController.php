<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReverseFinancialEntryRequest;
use App\Http\Requests\StoreSettlementRequest;
use App\Http\Resources\SettlementResource;
use App\Models\Room;
use App\Services\SettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SettlementController extends Controller
{
    public function index(Request $request, Room $room): AnonymousResourceCollection
    {
        $settlements = $room->settlements()
            ->with(['fromMember:id,display_name', 'toMember:id,display_name'])
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return SettlementResource::collection($settlements);
    }

    public function store(StoreSettlementRequest $request, Room $room, SettlementService $service): JsonResponse
    {
        $settlement = $service->create($room, $request->user(), $request->validated());

        return (new SettlementResource($settlement))->response()->setStatusCode(201);
    }

    public function reverse(ReverseFinancialEntryRequest $request, Room $room, int $settlement, SettlementService $service): SettlementResource
    {
        $original = $room->settlements()->whereKey($settlement)->firstOrFail();
        $reversal = $service->reverse(
            $room,
            $original,
            $request->user(),
            $request->validated('request_id'),
            $request->validated('note'),
        );

        return new SettlementResource($reversal);
    }
}
