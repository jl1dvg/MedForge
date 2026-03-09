<?php

namespace App\Modules\Shared\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LegacyPermissionResolver
{
    private const ATTR_RESOLVED_PERMISSIONS = '_legacy_resolved_permissions';

    /**
     * @return array<int, string>
     */
    public static function resolve(Request $request): array
    {
        $cached = $request->attributes->get(self::ATTR_RESOLVED_PERMISSIONS);
        if (is_array($cached)) {
            return $cached;
        }

        $session = LegacySessionAuth::readSession($request);
        $permissions = LegacyPermissionCatalog::normalize($session['permisos'] ?? []);
        $userId = LegacySessionAuth::userId($request);

        if ($userId !== null) {
            try {
                $row = DB::table('users as u')
                    ->leftJoin('roles as r', 'r.id', '=', 'u.role_id')
                    ->select(['u.permisos as user_permissions', 'r.permissions as role_permissions'])
                    ->where('u.id', $userId)
                    ->first();

                if ($row !== null) {
                    $permissions = LegacyPermissionCatalog::merge(
                        $permissions,
                        $row->user_permissions ?? [],
                        $row->role_permissions ?? []
                    );
                }
            } catch (\Throwable) {
                // Fallback a permisos de sesión si no hay conexión o falla la consulta.
            }
        }

        $request->attributes->set(self::ATTR_RESOLVED_PERMISSIONS, $permissions);

        return $permissions;
    }

    /**
     * @param array<int, string> $requiredPermissions
     */
    public static function canAny(Request $request, array $requiredPermissions): bool
    {
        return LegacyPermissionCatalog::containsAny(self::resolve($request), $requiredPermissions);
    }

    public static function can(Request $request, string $requiredPermission): bool
    {
        return LegacyPermissionCatalog::contains(self::resolve($request), $requiredPermission);
    }
}
