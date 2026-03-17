<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Models\User;
use App\Modules\Shared\Support\LegacyPermissionCatalog;
use App\Modules\Shared\Support\LegacySessionAuth;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginController
{
    public function show(Request $request): View|RedirectResponse
    {
        if (Auth::check() || LegacySessionAuth::bootstrapLaravelAuth($request)) {
            return redirect()->intended('/dashboard');
        }

        return view('auth.login', [
            'pageTitle' => 'Iniciar sesión',
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        if (Auth::check() || LegacySessionAuth::bootstrapLaravelAuth($request)) {
            return redirect()->intended('/dashboard');
        }

        $validated = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $identifier = trim((string) $validated['username']);
        $password = (string) $validated['password'];
        $remember = !empty($validated['remember']);

        $user = User::query()
            ->with('role')
            ->where(static function ($query) use ($identifier): void {
                $query->where('username', $identifier)
                    ->orWhere('email', $identifier);
            })
            ->first();

        if (!$user instanceof User || !Hash::check($password, (string) $user->password)) {
            return back()
                ->withErrors([
                    'username' => 'Credenciales incorrectas. Verifica tu usuario o contraseña.',
                ])
                ->withInput($request->only('username'));
        }

        Auth::login($user, $remember);
        $request->session()->regenerate();

        $permissions = LegacyPermissionCatalog::merge(
            $user->permisos ?? [],
            $user->role?->permissions ?? []
        );

        LegacySessionAuth::writeCompatibilitySession([
            'user_id' => (int) $user->id,
            'permisos' => $permissions,
            'role_id' => $user->role_id !== null ? (int) $user->role_id : null,
            'session_active' => true,
            'session_start_time' => time(),
            'last_activity_time' => time(),
            'username' => (string) ($user->username ?? $identifier),
            'sigcenter_password' => $password,
        ], LegacySessionAuth::sessionId($request) ?: null);

        return redirect()->intended('/dashboard');
    }
}
