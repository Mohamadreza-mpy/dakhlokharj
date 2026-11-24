<?php

use App\Http\Controllers\Api\V1\BalanceController;
use App\Http\Controllers\Api\V1\ConsumptionController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\PurchaseController;
use App\Http\Controllers\Api\V1\RoomController;
use App\Http\Controllers\Api\V1\RoomMemberController;
use App\Http\Controllers\Api\V1\SettlementController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', function (Request $request) {
        return response()->json(['data' => ['user' => $request->user()]]);
    });

    Route::prefix('v1')->group(function (): void {
        Route::get('/rooms', [RoomController::class, 'index']);
        Route::post('/rooms', [RoomController::class, 'store']);

        Route::middleware('can:view,room')->prefix('rooms/{room}')->group(function (): void {
            Route::get('/', [RoomController::class, 'show']);
            Route::get('/members', [RoomMemberController::class, 'index']);
            Route::post('/members', [RoomMemberController::class, 'store']);
            Route::patch('/members/{member}', [RoomMemberController::class, 'update']);
            Route::get('/purchases', [PurchaseController::class, 'index']);
            Route::post('/purchases', [PurchaseController::class, 'store']);
            Route::get('/purchases/{purchase}', [PurchaseController::class, 'show']);
            Route::post('/purchases/{purchase}/void', [PurchaseController::class, 'void']);
            Route::get('/purchases/{purchase}/consumptions', [ConsumptionController::class, 'index']);
            Route::post('/purchases/{purchase}/consumptions', [ConsumptionController::class, 'store']);
            Route::post('/consumptions/{consumption}/reverse', [ConsumptionController::class, 'reverse']);
            Route::get('/balances', [BalanceController::class, 'index']);
            Route::get('/members/{member}/balance', [BalanceController::class, 'member']);
            Route::get('/members/{member}/ledger', [BalanceController::class, 'ledger']);
            Route::get('/settlements', [SettlementController::class, 'index']);
            Route::post('/settlements', [SettlementController::class, 'store']);
            Route::post('/settlements/{settlement}/reverse', [SettlementController::class, 'reverse']);
            Route::get('/dashboard', [DashboardController::class, 'show']);
        });
    });
});
