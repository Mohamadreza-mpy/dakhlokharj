<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoomRequest;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoomController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rooms = $request->user()->rooms()
            ->withCount(['members', 'purchases'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return response()->json($rooms);
    }

    public function store(StoreRoomRequest $request): JsonResponse
    {
        $room = DB::transaction(function () use ($request): Room {
            $room = $request->user()->rooms()->create([
                ...$request->validated(),
                'currency' => 'TOMAN',
            ]);
            $room->members()->create([
                'user_id' => $request->user()->id,
                'display_name' => $request->user()->name,
                'role' => 'owner',
                'is_active' => true,
            ]);

            return $room;
        });

        return response()->json(['data' => $room->loadCount('members')], 201);
    }

    public function show(Room $room): JsonResponse
    {
        return response()->json(['data' => $room->loadCount(['members', 'purchases'])]);
    }
}
