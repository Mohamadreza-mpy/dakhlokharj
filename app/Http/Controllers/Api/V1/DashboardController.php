<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Services\RoomDashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function show(Room $room, RoomDashboardService $dashboard): JsonResponse
    {
        return response()->json(['data' => $dashboard->summary($room)]);
    }
}
