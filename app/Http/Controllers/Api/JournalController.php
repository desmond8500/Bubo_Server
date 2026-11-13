<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Journal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Consultation du journal d'activité (réservée aux administrateurs). */
class JournalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Journal::with('user:id,name')->latest('id');

        if ($request->filled('action')) {
            $query->where('action', $request->string('action'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        return response()->json($query->paginate(50));
    }
}
