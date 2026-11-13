<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Journal;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Gestion des utilisateurs et de leurs rôles (routes réservées aux administrateurs). */
class UserController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(User::orderBy('id')->get(['id', 'name', 'email', 'role']));
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(User::ROLES)],
        ]);

        if ($user->role === 'admin' && $data['role'] !== 'admin' && User::where('role', 'admin')->count() <= 1) {
            throw ValidationException::withMessages(['role' => ['Il doit rester au moins un administrateur.']]);
        }

        $ancien = $user->role;

        if ($ancien !== $data['role']) {
            $user->role = $data['role'];
            $user->save();

            Journal::enregistrer($request->user(), 'role', 'users', null, "{$user->name} : {$ancien} → {$user->role}");
        }

        return response()->json($user->only(['id', 'name', 'email', 'role']));
    }
}
