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
        if (Auth::check()) {
            return redirect()->intended('/v2/solicitudes');
        }

        return view('auth.login', [
            'pageTitle' => 'Iniciar sesión',
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->intended('/v2/solicitudes');
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

        return redirect()->intended('/v2/solicitudes');
    }

    private function shouldWriteLegacyCompatibilitySession(): bool
    {
        return filter_var((string) env('AUTH_WRITE_LEGACY_COMPAT_SESSION', '1'), FILTER_VALIDATE_BOOL);
    }
}
