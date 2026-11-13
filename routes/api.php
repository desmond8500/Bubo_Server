<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\JournalController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:10,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Données communes : visibles par tous. Les autres sont limitées à leur propriétaire.
    foreach (SyncController::MODELS as $uri => $model) {
        $shared = in_array($uri, SyncController::SHARED, true);

        Route::get("/{$uri}", fn (Request $request) => $shared
            ? $model::with('auteur:id,name')->orderByDesc('id')->get()
            : $model::where('user_id', $request->user()->id)->orderByDesc('id')->get());
    }

    Route::post('/sync', SyncController::class);

    Route::middleware('role:admin')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::patch('/users/{user}', [UserController::class, 'update']);
        Route::get('/journal', [JournalController::class, 'index']);
    });
});
