<?php

require_once __DIR__ . '/../../../bootstrap.php';

use Core\Permissions;
use Modules\CRM\Models\LeadModel;

header('Content-Type: application/json; charset=UTF-8');

$action = isset($_GET['action']) ? trim((string) $_GET['action']) : '';
if ($action !== 'profile') {
    http_response_code(400);
    echo json_encode(['success' => false, 'ok' => false, 'error' => 'Acción no soportada'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'ok' => false, 'error' => 'Sesión expirada'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!Permissions::containsAny($_SESSION['permisos'] ?? [], ['crm.view', 'crm.manage'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'ok' => false, 'error' => 'Acceso denegado'], JSON_UNESCAPED_UNICODE);
    exit;
}

$leadId = isset($_GET['leadId']) ? (int) $_GET['leadId'] : 0;
if ($leadId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'ok' => false, 'error' => 'leadId inválido'], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * @param array<string, mixed>|null $patient
 */
function buildComputedProfile(?array $patient): array
{
    if (!$patient) {
        return [];
    }

    $birthdate = '';
    if (!empty($patient['fecha_nacimiento'])) {
        $birthdate = (string) $patient['fecha_nacimiento'];
    } elseif (!empty($patient['birthdate'])) {
        $birthdate = (string) $patient['birthdate'];
    }

    $age = null;
    if ($birthdate !== '') {
        try {
            $date = new DateTimeImmutable($birthdate);
            $now = new DateTimeImmutable('today');
            $age = $date->diff($now)->y;
        } catch (Throwable $exception) {
            $age = null;
        }
    }

    $address = trim((string) ($patient['address'] ?? $patient['direccion'] ?? $patient['domicilio'] ?? ''));
    $city = trim((string) ($patient['city'] ?? $patient['ciudad'] ?? ''));
    $state = trim((string) ($patient['state'] ?? $patient['provincia'] ?? $patient['region'] ?? ''));
    $zip = trim((string) ($patient['zip'] ?? $patient['codigo_postal'] ?? $patient['postal_code'] ?? ''));
    $country = trim((string) ($patient['country'] ?? $patient['pais'] ?? ''));

    $displayParts = [];
    if ($address !== '') {
        $displayParts[] = $address;
    }

    $cityStateZip = trim(implode(' ', array_filter([$city, $state, $zip])));
    if ($cityStateZip !== '') {
        $displayParts[] = $cityStateZip;
    }

    if ($country !== '') {
        $displayParts[] = $country;
    }

    $displayAddress = $displayParts ? implode(', ', $displayParts) : null;

    return [
        'edad' => $age,
        'display_address' => $displayAddress,
    ];
}

try {
    $leadModel = new LeadModel($pdo);
    $profile = $leadModel->fetchProfileById($leadId);

    if (!$profile) {
        http_response_code(404);
        echo json_encode(['success' => false, 'ok' => false, 'error' => 'Lead no encontrado'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $patient = $profile['patient'] ?? null;
    $computed = buildComputedProfile(is_array($patient) ? $patient : null);

    echo json_encode([
        'success' => true,
        'ok' => true,
        'data' => [
            'lead' => $profile['lead'],
            'patient' => $patient,
            'computed' => $computed,
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'ok' => false, 'error' => 'No se pudo cargar el perfil'], JSON_UNESCAPED_UNICODE);
}
