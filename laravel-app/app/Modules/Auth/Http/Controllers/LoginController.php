<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Models\User;
use App\Modules\Shared\Support\LegacyPermissionCatalog;
use App\Modules\Shared\Support\LegacySessionAuth;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginController
{
    public function show(Request $request): View|RedirectResponse|Response
    {
        if ($request->query('expired') && Auth::check()) {
            // A 419 redirected an authenticated user here. Log out so they
            // see the "session expired" message and re-authenticate with a
            // fresh session (including a new CSRF token).
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if (Auth::check()) {
            return redirect()->intended('/v2/dashboard');
        }

        return response()
            ->view('auth.login', ['pageTitle' => 'Iniciar sesión'])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function login(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->intended('/v2/dashboard');
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

        if ($this->shouldWriteLegacyCompatibilitySession()) {
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
        }

        $request->session()->flash('post_login_feedback_prompt', true);

        return redirect()->intended('/v2/dashboard');
    }

    private function shouldWriteLegacyCompatibilitySession(): bool
    {
        return (bool) config('auth_migration.write_legacy_compat_session', true);
    }
}
