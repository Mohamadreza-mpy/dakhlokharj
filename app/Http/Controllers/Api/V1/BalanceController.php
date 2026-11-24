<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Services\BalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BalanceController extends Controller
{
    public function index(Request $request, Room $room, BalanceService $balanceService): JsonResponse
    {
        $memberId = $request->validate([
            'member_id' => ['nullable', 'integer', 'exists:room_members,id'],
        ], [
            'member_id.exists' => 'عضو انتخاب‌شده وجود ندارد.',
        ])['member_id'] ?? null;

        if ($memberId !== null && ! $room->members()->whereKey($memberId)->exists()) {
            abort(404);
        }

        return response()->json(['data' => $balanceService->forRoom($room, $memberId === null ? null : (int) $memberId)]);
    }

    public function member(Room $room, int $member, BalanceService $balanceService): JsonResponse
    {
        $roomMember = $room->members()->whereKey($member)->firstOrFail();

        return response()->json(['data' => $balanceService->memberSummary($room, $roomMember)]);
    }

    public function ledger(Request $request, Room $room, int $member, BalanceService $balanceService): JsonResponse
    {
        $roomMember = $room->members()->whereKey($member)->firstOrFail();
        $page = max(1, $request->integer('page', 1));
        $perPage = min(max($request->integer('per_page', 50), 1), 100);
        $items = $balanceService->ledger($room, $roomMember);

        return response()->json([
            'data' => array_slice($items, ($page - 1) * $perPage, $perPage),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => count($items),
                'last_page' => max(1, (int) ceil(count($items) / $perPage)),
            ],
        ]);
    }
}
