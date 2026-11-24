<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoomMemberRequest;
use App\Http\Requests\UpdateRoomMemberRequest;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomMemberController extends Controller
{
    public function index(Request $request, Room $room): JsonResponse
    {
        $query = $room->members()->orderBy('id');

        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        return response()->json($query->paginate(min($request->integer('per_page', 50), 100)));
    }

    public function store(StoreRoomMemberRequest $request, Room $room): JsonResponse
    {
        $member = $room->members()->create([
            'display_name' => $request->validated('display_name'),
            'user_id' => $request->validated('user_id'),
            'role' => 'member',
            'is_active' => true,
        ]);

        return response()->json(['data' => $member], 201);
    }

    public function update(UpdateRoomMemberRequest $request, Room $room, int $member): JsonResponse
    {
        $roomMember = $room->members()->whereKey($member)->firstOrFail();
        $roomMember->fill($request->validated())->save();

        return response()->json(['data' => $roomMember->fresh()]);
    }
}
