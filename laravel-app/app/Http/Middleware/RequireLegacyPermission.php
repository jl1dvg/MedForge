<?php

namespace App\Http\Middleware;

use App\Modules\Shared\Support\LegacyPermissionResolver;
use App\Modules\Shared\Support\LegacySessionAuth;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireLegacyPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response|RedirectResponse
    {
        LegacySessionAuth::bootstrapLaravelAuth($request);

        if (!LegacySessionAuth::isAuthenticated($request)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Sesión expirada'], 401);
            }

            return redirect('/auth/login?auth_required=1');
        }

        $required = $this->normalizeRequired($permissions);
        if ($required === [] || LegacyPermissionResolver::canAny($request, $required)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Acceso denegado',
                'required_permissions' => $required,
            ], 403);
        }

        abort(403, 'Acceso denegado');
    }

    /**
     * @param array<int, string> $permissions
     * @return array<int, string>
     */
    private function normalizeRequired(array $permissions): array
    {
        $required = [];

        foreach ($permissions as $chunk) {
            foreach (explode(',', $chunk) as $permission) {
                $permission = trim($permission);
                if ($permission === '' || in_array($permission, $required, true)) {
                    continue;
                }
                $required[] = $permission;
            }
        }

        return $required;
    }
}
