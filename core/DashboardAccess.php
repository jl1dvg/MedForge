<?php

namespace Core;

use PDO;

class DashboardAccess
{
    public const DASHBOARD_GENERAL = 'general';
    public const DASHBOARD_SOLICITUDES = 'solicitudes';
    public const DASHBOARD_CIRUGIAS = 'cirugias';
    public const DASHBOARD_BILLING = 'billing';

    private const DASHBOARD_META = [
        self::DASHBOARD_GENERAL => [
            'label' => 'General',
            'path' => '/dashboard/general',
        ],
        self::DASHBOARD_SOLICITUDES => [
            'label' => 'Solicitudes',
            'path' => '/dashboard/solicitudes',
        ],
        self::DASHBOARD_CIRUGIAS => [
            'label' => 'Cirugías',
            'path' => '/dashboard/cirugias',
        ],
        self::DASHBOARD_BILLING => [
            'label' => 'Billing',
            'path' => '/dashboard/billing',
        ],
    ];

    private const SPECIALTY_MAP = [
        'coordinación quirúrgica' => self::DASHBOARD_SOLICITUDES,
        'facturación' => self::DASHBOARD_BILLING,
        'enfermera' => self::DASHBOARD_CIRUGIAS,
    ];

    public static function dashboards(): array
    {
        return self::DASHBOARD_META;
    }

    public static function dashboardPath(string $key): string
    {
        return self::DASHBOARD_META[$key]['path'] ?? self::DASHBOARD_META[self::DASHBOARD_GENERAL]['path'];
    }

    public static function resolveDefaultDashboardKey(?string $specialty): string
    {
        $normalized = self::normalizeSpecialty($specialty);

        return self::SPECIALTY_MAP[$normalized] ?? self::DASHBOARD_GENERAL;
    }

    public static function resolveUserContext(PDO $pdo, int $userId, array $permissions): array
    {
        $specialty = self::fetchUserSpecialty($pdo, $userId);
        $defaultKey = self::resolveDefaultDashboardKey($specialty);
        $isAdmin = self::isAdmin($permissions);
        $allowedKeys = $isAdmin ? array_keys(self::DASHBOARD_META) : [$defaultKey];

        return [
            'specialty' => $specialty,
            'default_key' => $defaultKey,
            'default_path' => self::dashboardPath($defaultKey),
            'allowed_keys' => $allowedKeys,
            'is_admin' => $isAdmin,
        ];
    }

    public static function canAccess(array $context, string $dashboardKey): bool
    {
        if (!empty($context['is_admin'])) {
            return true;
        }

        return in_array($dashboardKey, $context['allowed_keys'] ?? [], true);
    }

    public static function enforceAccess(array $context, string $dashboardKey): void
    {
        if (self::canAccess($context, $dashboardKey)) {
            return;
        }

        $fallback = $context['default_path'] ?? self::dashboardPath(self::DASHBOARD_GENERAL);
        header('Location: ' . $fallback);
        exit;
    }

    public static function isAdmin(array $permissions): bool
    {
        return Permissions::containsAny($permissions, [
            'administrativo',
            'admin.usuarios.manage',
            'admin.roles.manage',
            'admin.usuarios',
            'admin.roles',
        ]);
    }

    private static function normalizeSpecialty(?string $specialty): string
    {
        $value = trim((string) $specialty);
        if ($value === '') {
            return '';
        }

        return mb_strtolower($value);
    }

    private static function fetchUserSpecialty(PDO $pdo, int $userId): ?string
    {
        if ($userId <= 0) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT especialidad FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);

        $specialty = $stmt->fetchColumn();

        return is_string($specialty) ? $specialty : null;
    }
}
