<?php

namespace App\Modules\Shared\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LegacyCurrentUser
{
    /**
     * @return array<string, mixed>
     */
    public static function resolve(Request $request): array
    {
        $userId = LegacySessionAuth::userId($request);
        if ($userId === null) {
            return [
                'id' => null,
                'display_name' => 'Usuario',
                'role_name' => 'Usuario',
                'profile_photo_url' => null,
            ];
        }

        $row = DB::table('users as u')
            ->leftJoin('roles as r', 'r.id', '=', 'u.role_id')
            ->select(['u.id', 'u.username', 'u.nombre', 'u.email', 'u.profile_photo', 'r.name as role_name'])
            ->where('u.id', $userId)
            ->first();

        $displayName = trim((string) ($row->nombre ?? $row->username ?? 'Usuario'));
        if ($displayName === '') {
            $displayName = 'Usuario';
        }

        $profilePhoto = trim((string) ($row->profile_photo ?? ''));
        $profilePhotoUrl = $profilePhoto !== '' ? '/' . ltrim($profilePhoto, '/') : null;

        return [
            'id' => (int) ($row->id ?? $userId),
            'display_name' => $displayName,
            'role_name' => (string) ($row->role_name ?? 'Usuario'),
            'profile_photo_url' => $profilePhotoUrl,
        ];
    }
}

