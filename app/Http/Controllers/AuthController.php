<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $ip = $request->ip();
        $limit = (int) config('arcade.max_signups_per_ip_per_day');

        if ($limit > 0 && User::where('registration_ip', $ip)->where('created_at', '>=', now()->subDay())->count() >= $limit) {
            throw ValidationException::withMessages([
                'email' => "Trop d'inscriptions depuis cette connexion aujourd'hui. Réessaie demain.",
            ]);
        }

        $user = new User($data);                       // role = 'player' par défaut (colonne DB)
        $user->forceFill(['registration_ip' => $ip])->save();
        $user->refresh();

        return response()->json([
            'token' => $this->issueToken($user),
            'user' => $this->userPayload($user),
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Identifiants incorrects.']);
        }

        return response()->json([
            'token' => $this->issueToken($user),
            'user' => $this->userPayload($user),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }

    /** Change le mot de passe et déconnecte tous les AUTRES appareils. */
    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'different:current_password'],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Mot de passe actuel incorrect.']);
        }

        $user->update(['password' => $data['password']]);

        $tokens = $user->tokens();
        $current = $user->currentAccessToken();
        if ($current instanceof PersonalAccessToken) {
            $tokens->where('id', '!=', $current->id);
        }
        $tokens->delete();

        return response()->json(['message' => 'Mot de passe modifié.']);
    }

    private function issueToken(User $user): string
    {
        return $user->createToken(
            'arcade-front',
            ['*'],
            now()->addDays((int) config('arcade.token_days'))
        )->plainTextToken;
    }

    private function userPayload(User $user): array
    {
        return $user->only(['id', 'name', 'email', 'role']);
    }
}
