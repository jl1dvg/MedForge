<?php

namespace App\Modules\Usuarios\Http\Controllers;

use App\Modules\Shared\Support\LegacyCurrentUser;
use App\Modules\Shared\Support\LegacyPermissionCatalog;
use App\Modules\Shared\Support\LegacyPermissionResolver;
use App\Modules\Shared\Support\LegacySessionAuth;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RolesUiController
{
    public function index(Request $request): View|RedirectResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return redirect('/auth/login?auth_required=1');
        }

        $rows = DB::table('roles as r')
            ->leftJoin('users as u', 'u.role_id', '=', 'r.id')
            ->groupBy('r.id', 'r.name', 'r.description', 'r.permissions')
            ->orderBy('r.name')
            ->selectRaw('r.id, r.name, r.description, r.permissions, COUNT(u.id) as users_count')
            ->get();

        $roles = $rows->map(static function (object $row): array {
            return [
                'id' => (int) $row->id,
                'name' => (string) ($row->name ?? ''),
                'description' => (string) ($row->description ?? ''),
                'permissions_list' => LegacyPermissionCatalog::normalize($row->permissions ?? []),
                'users_count' => (int) ($row->users_count ?? 0),
            ];
        })->all();

        return view('roles.v2-index', [
            'pageTitle' => 'Roles',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'roles' => $roles,
            'permissionLabels' => LegacyPermissionCatalog::all(),
            'status' => session('status'),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return redirect('/auth/login?auth_required=1');
        }

        return view('roles.v2-form', [
            'pageTitle' => 'Nuevo rol',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'role' => ['id' => null, 'name' => '', 'description' => ''],
            'permissions' => LegacyPermissionCatalog::groups(),
            'selectedPermissions' => [],
            'formAction' => '/roles',
            'status' => session('status'),
            'canAssignSuperuser' => $this->currentUserIsSuperuser($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return redirect('/auth/login?auth_required=1');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('roles', 'name')],
            'description' => ['nullable', 'string'],
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ]);

        $selectedPermissions = $this->filterAssignablePermissions(
            $request,
            LegacyPermissionCatalog::sanitizeSelection((array) ($validated['permissions'] ?? []))
        );

        $id = DB::table('roles')->insertGetId([
            'name' => trim((string) $validated['name']),
            'description' => trim((string) ($validated['description'] ?? '')) ?: null,
            'permissions' => json_encode($selectedPermissions, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect('/roles/' . $id . '/edit')->with('status', 'created');
    }

    public function edit(Request $request, int $id): View|RedirectResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return redirect('/auth/login?auth_required=1');
        }

        $role = $this->findRole($id);
        if ($role === null) {
            return redirect('/roles')->with('status', 'not_found');
        }

        return view('roles.v2-form', [
            'pageTitle' => 'Editar rol',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'role' => $role,
            'permissions' => LegacyPermissionCatalog::groups(),
            'selectedPermissions' => LegacyPermissionCatalog::normalize($role['permissions'] ?? []),
            'formAction' => '/roles/' . $id,
            'status' => session('status'),
            'canAssignSuperuser' => $this->currentUserIsSuperuser($request),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return redirect('/auth/login?auth_required=1');
        }

        $role = $this->findRole($id);
        if ($role === null) {
            return redirect('/roles')->with('status', 'not_found');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('roles', 'name')->ignore($id)],
            'description' => ['nullable', 'string'],
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ]);

        $selectedPermissions = $this->filterAssignablePermissions(
            $request,
            LegacyPermissionCatalog::sanitizeSelection((array) ($validated['permissions'] ?? [])),
            $role['permissions'] ?? []
        );

        DB::table('roles')
            ->where('id', $id)
            ->update([
                'name' => trim((string) $validated['name']),
                'description' => trim((string) ($validated['description'] ?? '')) ?: null,
                'permissions' => json_encode($selectedPermissions, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);

        return redirect('/roles/' . $id . '/edit')->with('status', 'updated');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return redirect('/auth/login?auth_required=1');
        }

        $role = $this->findRole($id);
        if ($role === null) {
            return redirect('/roles')->with('status', 'not_found');
        }

        $usersCount = (int) DB::table('users')->where('role_id', $id)->count();
        if ($usersCount > 0) {
            return redirect('/roles')->with('status', 'role_in_use');
        }

        DB::table('roles')->where('id', $id)->delete();

        return redirect('/roles')->with('status', 'deleted');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findRole(int $id): ?array
    {
        $row = DB::table('roles')->where('id', $id)->first();
        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'name' => (string) ($row->name ?? ''),
            'description' => (string) ($row->description ?? ''),
            'permissions' => $row->permissions ?? '[]',
        ];
    }

    /**
     * @param array<int, string> $selected
     * @return array<int, string>
     */
    private function filterAssignablePermissions(Request $request, array $selected, mixed $existingPermissions = []): array
    {
        if ($this->currentUserIsSuperuser($request)) {
            return $selected;
        }

        $selected = array_values(array_filter(
            $selected,
            static fn (string $permission): bool => $permission !== LegacyPermissionCatalog::SUPERUSER
        ));

        if (LegacyPermissionCatalog::contains($existingPermissions, LegacyPermissionCatalog::SUPERUSER)) {
            $selected[] = LegacyPermissionCatalog::SUPERUSER;
        }

        return array_values(array_unique($selected));
    }

    private function currentUserIsSuperuser(Request $request): bool
    {
        return LegacyPermissionCatalog::contains(
            LegacyPermissionResolver::resolve($request),
            LegacyPermissionCatalog::SUPERUSER
        );
    }
}
