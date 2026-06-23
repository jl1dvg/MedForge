<?php

namespace App\Modules\Reporting\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImagenesDefaultFirmante
{
    private const DEFAULT_NAME = 'JORGE LUIS DE VERA GUTIERREZ';

    /**
     * @return array{nombres:string,apellido1:string,apellido2:string,documento:string,registro:string,firma:string,signature_path:string}
     */
    public static function resolve(?int $firmanteId = null): array
    {
        $user = $firmanteId !== null && $firmanteId > 0
            ? self::findUserById($firmanteId)
            : null;

        if ($user === null) {
            $user = self::findDefaultUser();
        }

        if ($user === null) {
            return self::emptyFirmante();
        }

        return self::formatUser($user);
    }

    public static function defaultUserId(): ?int
    {
        $user = self::findDefaultUser();
        if ($user === null || !isset($user['id']) || !is_numeric($user['id'])) {
            return null;
        }

        return (int) $user['id'];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function findUserById(int $userId): ?array
    {
        $row = DB::table('users')->where('id', $userId)->first();

        return is_object($row) ? (array) $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function findDefaultUser(): ?array
    {
        if (!Schema::hasTable('users')) {
            return null;
        }

        $columns = self::existingUserColumns(['name', 'nombre', 'full_name', 'first_name', 'middle_name', 'last_name', 'second_last_name', 'email']);
        $query = DB::table('users');

        if ($columns !== []) {
            $query->where(function ($nested) use ($columns): void {
                foreach ($columns as $column) {
                    $nested->orWhere($column, 'like', '%Jorge%')
                        ->orWhere($column, 'like', '%Vera%');
                }
            });
        }

        $rows = $query->limit(50)->get();
        foreach ($rows as $row) {
            $user = (array) $row;
            if (self::isDefaultUser($user)) {
                return $user;
            }
        }

        $row = DB::table('users')->where('id', 1)->first();
        $user = is_object($row) ? (array) $row : null;

        return $user !== null && self::isDefaultUser($user) ? $user : null;
    }

    /**
     * @param array<int, string> $candidates
     * @return array<int, string>
     */
    private static function existingUserColumns(array $candidates): array
    {
        return array_values(array_filter($candidates, static function (string $column): bool {
            return Schema::hasColumn('users', $column);
        }));
    }

    /**
     * @param array<string, mixed> $user
     */
    private static function isDefaultUser(array $user): bool
    {
        $haystack = trim(implode(' ', array_filter([
            $user['name'] ?? null,
            $user['nombre'] ?? null,
            $user['full_name'] ?? null,
            $user['first_name'] ?? null,
            $user['middle_name'] ?? null,
            $user['last_name'] ?? null,
            $user['second_last_name'] ?? null,
            $user['email'] ?? null,
        ], static fn ($value): bool => trim((string) $value) !== '')));

        $normalized = self::normalize($haystack);

        return str_contains($normalized, 'JORGE')
            && str_contains($normalized, 'LUIS')
            && str_contains($normalized, 'VERA')
            && str_contains($normalized, 'GUTIERREZ');
    }

    /**
     * @param array<string, mixed> $user
     * @return array{nombres:string,apellido1:string,apellido2:string,documento:string,registro:string,firma:string,signature_path:string}
     */
    private static function formatUser(array $user): array
    {
        $nombres = trim((string) ($user['first_name'] ?? ''));
        $segundoNombre = trim((string) ($user['middle_name'] ?? ''));
        if ($segundoNombre !== '') {
            $nombres = trim($nombres . ' ' . $segundoNombre);
        }

        $apellido1 = trim((string) ($user['last_name'] ?? ''));
        $apellido2 = trim((string) ($user['second_last_name'] ?? ''));

        if ($nombres === '' && $apellido1 === '' && $apellido2 === '') {
            [$nombres, $apellido1, $apellido2] = self::splitDisplayName((string) ($user['full_name'] ?? $user['nombre'] ?? $user['name'] ?? ''));
        }

        return [
            'nombres' => $nombres,
            'apellido1' => $apellido1,
            'apellido2' => $apellido2,
            'documento' => trim((string) ($user['cedula'] ?? '')),
            'registro' => trim((string) ($user['registro'] ?? '')),
            'firma' => trim((string) ($user['firma'] ?? '')),
            'signature_path' => trim((string) ($user['signature_path'] ?? '')),
        ];
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    private static function splitDisplayName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $parts = array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
        $count = count($parts);

        if ($count >= 5 && self::normalize($name) === self::DEFAULT_NAME) {
            return [
                implode(' ', array_slice($parts, 0, 2)),
                implode(' ', array_slice($parts, 2, 2)),
                implode(' ', array_slice($parts, 4)),
            ];
        }

        if ($count >= 4) {
            return [
                implode(' ', array_slice($parts, 0, $count - 2)),
                $parts[$count - 2],
                $parts[$count - 1],
            ];
        }

        return [trim($name), '', ''];
    }

    private static function normalize(string $value): string
    {
        $value = trim($value);
        $value = strtr($value, [
            'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ä' => 'A', 'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I', 'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Ö' => 'O', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U', 'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'Ñ' => 'N', 'ñ' => 'n',
        ]);
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($converted) && $converted !== '') {
                $value = $converted;
            }
        }

        $value = strtoupper($value);
        $value = preg_replace('/[^A-Z0-9]+/', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    /**
     * @return array{nombres:string,apellido1:string,apellido2:string,documento:string,registro:string,firma:string,signature_path:string}
     */
    private static function emptyFirmante(): array
    {
        return [
            'nombres' => '',
            'apellido1' => '',
            'apellido2' => '',
            'documento' => '',
            'registro' => '',
            'firma' => '',
            'signature_path' => '',
        ];
    }
}
