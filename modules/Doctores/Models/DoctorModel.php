<?php

namespace Modules\Doctores\Models;

use PDO;

class DoctorModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $sql = <<<'SQL'
            SELECT
                u.id,
                u.nombre,
                u.full_name,
                u.first_name,
                u.middle_name,
                u.last_name,
                u.second_last_name,
                u.username,
                u.email,
                u.especialidad,
                u.subespecialidad,
                u.sede,
                u.cedula,
                u.registro,
                u.firma,
                u.signature_path,
                u.profile_photo,
                u.is_subscribed,
                u.is_approved,
                r.name AS role_name
            FROM users u
            LEFT JOIN roles r ON r.id = u.role_id
            WHERE
                (u.especialidad IS NOT NULL AND u.especialidad = 'Cirujano Oftalmólogo')
                OR (r.name IS NOT NULL AND r.name <> '')
            ORDER BY COALESCE(NULLIF(u.nombre, ''), NULLIF(u.username, ''), u.email) ASC
        SQL;

        $stmt = $this->pdo->query($sql);
        if (!$stmt) {
            return [];
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'mapDoctorRow'], $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $sql = <<<'SQL'
            SELECT
                u.id,
                u.nombre,
                u.full_name,
                u.first_name,
                u.middle_name,
                u.last_name,
                u.second_last_name,
                u.username,
                u.email,
                u.especialidad,
                u.subespecialidad,
                u.sede,
                u.cedula,
                u.registro,
                u.firma,
                u.signature_path,
                u.profile_photo,
                u.is_subscribed,
                u.is_approved,
                r.name AS role_name
            FROM users u
            LEFT JOIN roles r ON r.id = u.role_id
            WHERE u.id = :id
            LIMIT 1
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->mapDoctorRow($row) : null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function mapDoctorRow(array $row): array
    {
        $structuredFullName = $this->buildFullNameFromParts([
            $row['first_name'] ?? null,
            $row['middle_name'] ?? null,
            $row['last_name'] ?? null,
            $row['second_last_name'] ?? null,
        ]);
        $legacyFullName = $this->nullIfEmpty($row['nombre'] ?? null);
        $fullName = $this->nullIfEmpty($row['full_name'] ?? null) ?? $structuredFullName ?? $legacyFullName;
        $username = $this->nullIfEmpty($row['username'] ?? null);
        $displayName = $fullName ?? $username ?? $this->nullIfEmpty($row['email'] ?? null) ?? 'Usuario sin nombre';

        $prefixedName = $displayName;
        if ($this->shouldPrefixDoctorTitle($displayName)) {
            $prefixedName = 'Dr. ' . $displayName;
        }

        [$statusLabel, $statusVariant] = $this->resolveStatus($row);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => $displayName,
            'full_name' => $fullName,
            'legacy_full_name' => $legacyFullName,
            'first_name' => $this->nullIfEmpty($row['first_name'] ?? null),
            'middle_name' => $this->nullIfEmpty($row['middle_name'] ?? null),
            'last_name' => $this->nullIfEmpty($row['last_name'] ?? null),
            'second_last_name' => $this->nullIfEmpty($row['second_last_name'] ?? null),
            'display_name' => $prefixedName,
            'email' => $this->nullIfEmpty($row['email'] ?? null),
            'especialidad' => $this->nullIfEmpty($row['especialidad'] ?? null),
            'subespecialidad' => $this->nullIfEmpty($row['subespecialidad'] ?? null),
            'sede' => $this->nullIfEmpty($row['sede'] ?? null),
            'cedula' => $this->nullIfEmpty($row['cedula'] ?? null),
            'registro' => $this->nullIfEmpty($row['registro'] ?? null),
            'firma' => $this->nullIfEmpty($row['firma'] ?? null),
            'signature_path' => $this->nullIfEmpty($row['signature_path'] ?? null),
            'profile_photo' => $this->nullIfEmpty($row['profile_photo'] ?? null),
            'role_name' => $this->nullIfEmpty($row['role_name'] ?? null),
            'is_subscribed' => (int) ($row['is_subscribed'] ?? 0) === 1,
            'is_approved' => (int) ($row['is_approved'] ?? 0) === 1,
            'status' => $statusLabel,
            'status_variant' => $statusVariant,
            'detail_url' => '/doctores/' . (int) ($row['id'] ?? 0),
            'username' => $username,
        ];
    }

    private function shouldPrefixDoctorTitle(string $name): bool
    {
        return preg_match('/^(Dr\.?|Dra\.?)/i', $name) !== 1;
    }

    private function nullIfEmpty(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param array<int, mixed> $parts
     */
    private function buildFullNameFromParts(array $parts): ?string
    {
        $normalized = array_values(array_filter(array_map(static function ($value) {
            if ($value === null) {
                return null;
            }

            $trimmed = trim((string) $value);

            return $trimmed === '' ? null : $trimmed;
        }, $parts)));

        if ($normalized === []) {
            return null;
        }

        return implode(' ', $normalized);
    }

    /**
     * @param array<string, mixed> $row
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveStatus(array $row): array
    {
        $isApproved = (int) ($row['is_approved'] ?? 0) === 1;
        $isSubscribed = (int) ($row['is_subscribed'] ?? 0) === 1;

        if ($isApproved && $isSubscribed) {
            return ['Full Time', 'primary'];
        }

        if ($isApproved) {
            return ['Activo', 'success'];
        }

        if ($isSubscribed) {
            return ['Part Time', 'danger'];
        }

        return [null, null];
    }
}
