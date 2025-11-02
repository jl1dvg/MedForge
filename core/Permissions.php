<?php

namespace Core;

class Permissions
{
    public const SUPERUSER = 'superuser';

    /**
     * Normaliza cualquier representación de permisos a un arreglo de strings.
     */
    public static function normalize(mixed $value): array
    {
        if ($value instanceof \Traversable) {
            $value = iterator_to_array($value);
        }

        if (is_array($value)) {
            return array_values(array_unique(array_filter(array_map(static fn($item) => is_string($item) ? trim($item) : '', $value), static fn($item) => $item !== '')));
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return self::normalize($decoded);
            }

            return [$value];
        }

        return [];
    }

    public static function contains(mixed $value, string $permission): bool
    {
        $normalized = self::normalize($value);
        if (in_array(self::SUPERUSER, $normalized, true)) {
            return true;
        }

        return in_array($permission, $normalized, true);
    }

    public static function containsAny(mixed $value, array $permissions): bool
    {
        $normalized = self::normalize($value);
        if (in_array(self::SUPERUSER, $normalized, true)) {
            return true;
        }

        foreach ($permissions as $permission) {
            if (in_array($permission, $normalized, true)) {
                return true;
            }
        }

        return false;
    }
}
