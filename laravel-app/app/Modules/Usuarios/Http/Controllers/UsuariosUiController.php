<?php

namespace App\Modules\Usuarios\Http\Controllers;

use App\Modules\Shared\Support\LegacyCurrentUser;
use App\Modules\Shared\Support\LegacyPermissionCatalog;
use App\Modules\Shared\Support\LegacySessionAuth;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UsuariosUiController
{
    public function index(Request $request): View|RedirectResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return redirect('/auth/login?auth_required=1');
        }

        $rows = DB::table('users as u')
            ->leftJoin('roles as r', 'r.id', '=', 'u.role_id')
            ->select([
                'u.id',
                'u.username',
                'u.nombre',
                'u.email',
                'u.role_id',
                'u.permisos as user_permissions',
                'r.name as role_name',
                'r.permissions as role_permissions',
            ])
            ->orderBy('u.username')
            ->get();

        $users = $rows->map(static function (object $row): array {
            return [
                'id' => (int) $row->id,
                'username' => (string) ($row->username ?? ''),
                'nombre' => (string) ($row->nombre ?? ''),
                'email' => (string) ($row->email ?? ''),
                'role_id' => $row->role_id !== null ? (int) $row->role_id : null,
                'role_name' => (string) ($row->role_name ?? 'Sin rol'),
                'user_permissions' => LegacyPermissionCatalog::normalize($row->user_permissions ?? []),
                'effective_permissions' => LegacyPermissionCatalog::merge(
                    $row->user_permissions ?? [],
                    $row->role_permissions ?? []
                ),
            ];
        })->all();

        return view('usuarios.v2-index', [
            'pageTitle' => 'Usuarios',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'users' => $users,
            'permissionLabels' => LegacyPermissionCatalog::all(),
            'status' => session('status'),
        ]);
    }

    public function edit(Request $request, int $id): View|RedirectResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return redirect('/auth/login?auth_required=1');
        }

        $user = $this->findUser($id);
        if ($user === null) {
            return redirect('/v2/usuarios')->with('status', 'not_found');
        }

        $roles = DB::table('roles')->select(['id', 'name'])->orderBy('name')->get()->all();

        return view('usuarios.v2-edit', [
            'pageTitle' => 'Editar usuario',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'user' => $user,
            'roles' => $roles,
            'permissions' => LegacyPermissionCatalog::groups(),
            'permissionProfiles' => config('permission_profiles', []),
            'selectedPermissions' => LegacyPermissionCatalog::normalize($user['user_permissions'] ?? []),
            'status' => session('status'),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return redirect('/auth/login?auth_required=1');
        }

        $user = $this->findUser($id);
        if ($user === null) {
            return redirect('/v2/usuarios')->with('status', 'not_found');
        }

        $validated = $request->validate([
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')],
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ]);

        $selectedPermissions = LegacyPermissionCatalog::sanitizeSelection((array) ($validated['permissions'] ?? []));
        $payload = [
            'role_id' => isset($validated['role_id']) ? (int) $validated['role_id'] : null,
            'permisos' => json_encode($selectedPermissions, JSON_UNESCAPED_UNICODE),
        ];

        DB::table('users')->where('id', $id)->update($payload);

        if (LegacySessionAuth::userId($request) === $id) {
            $this->syncLegacySessionPermissions($request, $selectedPermissions);
        }

        return redirect('/v2/usuarios/' . $id . '/edit')->with('status', 'updated');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findUser(int $id): ?array
    {
        $row = DB::table('users as u')
            ->leftJoin('roles as r', 'r.id', '=', 'u.role_id')
            ->select([
                'u.id',
                'u.username',
                'u.nombre',
                'u.email',
                'u.role_id',
                'u.permisos as user_permissions',
                'r.name as role_name',
                'r.permissions as role_permissions',
            ])
            ->where('u.id', $id)
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'username' => (string) ($row->username ?? ''),
            'nombre' => (string) ($row->nombre ?? ''),
            'email' => (string) ($row->email ?? ''),
            'role_id' => $row->role_id !== null ? (int) $row->role_id : null,
            'role_name' => (string) ($row->role_name ?? 'Sin rol'),
            'user_permissions' => LegacyPermissionCatalog::normalize($row->user_permissions ?? []),
            'effective_permissions' => LegacyPermissionCatalog::merge(
                $row->user_permissions ?? [],
                $row->role_permissions ?? []
            ),
        ];
    }

    /**
     * @param array<int, string> $permissions
     */
    private function syncLegacySessionPermissions(Request $request, array $permissions): void
    {
        $sessionId = LegacySessionAuth::sessionId($request);
        if ($sessionId === '') {
            return;
        }

        $originalName = session_name();
        $originalId = session_id();
        $wasActive = session_status() === PHP_SESSION_ACTIVE;

        if ($wasActive) {
            @session_write_close();
        }

        session_name('PHPSESSID');
        session_id($sessionId);

        if (@session_start()) {
            $_SESSION['permisos'] = LegacyPermissionCatalog::normalize($permissions);
            @session_write_close();
        }

        if ($originalName !== '') {
            @session_name($originalName);
        }

        if ($originalId !== '') {
            @session_id($originalId);
        }
    }
}
