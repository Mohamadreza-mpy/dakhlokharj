<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:3,1')->name('register');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::view('/', 'app')->name('home');
Route::view('/{path?}', 'app')
    ->where('path', '^(?!api(?:/|$)|sanctum(?:/|$)).*$')
    ->name('spa');
