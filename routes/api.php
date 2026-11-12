<?php

use App\Http\Controllers\Api\SyncController;
use App\Models\Article;
use App\Models\Fournisseur;
use App\Models\Marque;
use Illuminate\Support\Facades\Route;

Route::get('/marques', fn () => Marque::orderBy('nom')->get());
Route::get('/fournisseurs', fn () => Fournisseur::orderBy('nom')->get());
Route::get('/articles', fn () => Article::orderByDesc('depense_le')->orderByDesc('id')->get());

Route::post('/sync', SyncController::class);
