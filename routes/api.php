<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SyncController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:10,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    foreach (SyncController::MODELS as $uri => $model) {
        Route::get("/{$uri}", fn (Request $request) => $model::where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->get());
    }

    Route::post('/sync', SyncController::class);
});
