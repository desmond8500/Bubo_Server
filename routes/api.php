<?php

use App\Http\Controllers\Api\SyncController;
use Illuminate\Support\Facades\Route;

foreach (SyncController::MODELS as $uri => $model) {
    Route::get("/{$uri}", fn () => $model::orderByDesc('id')->get());
}

Route::post('/sync', SyncController::class);
