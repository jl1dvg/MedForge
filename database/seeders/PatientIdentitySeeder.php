<?php

declare(strict_types=1);

use Modules\CRM\Models\LeadModel;
use Modules\Pacientes\Models\PacientesModel;
use Modules\Shared\Services\PatientIdentityService;

require_once __DIR__ . '/../bootstrap.php';

if (!isset($pdo) || !$pdo instanceof PDO) {
    fwrite(STDERR, "No se pudo inicializar la conexión PDO. Configure la base de datos antes de ejecutar el seeder." . PHP_EOL);
    exit(1);
}

$identityService = new PatientIdentityService($pdo);
$leadModel = new LeadModel($pdo);
$patientModel = new PacientesModel($pdo);

function cleanupRecords(PDO $pdo, string $hcNumber): void
{
    $tables = ['crm_leads', 'crm_customers', 'patient_data'];
    foreach ($tables as $table) {
        $stmt = $pdo->prepare("DELETE FROM {$table} WHERE hc_number = :hc");
        $stmt->execute([':hc' => $hcNumber]);
    }
}

function dumpResult(string $title, array $data): void
{
    echo str_repeat('=', 80) . PHP_EOL;
    echo $title . PHP_EOL;
    echo str_repeat('-', 80) . PHP_EOL;
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            echo sprintf("%-25s %s" . PHP_EOL, $key . ':', json_encode($value, JSON_UNESCAPED_UNICODE));
        } else {
            echo sprintf("%-25s %s" . PHP_EOL, $key . ':', (string) $value);
        }
    }
    echo PHP_EOL;
}

// 1) Crear un lead desde CRM y verificar su reflejo en patient_data
$crmHc = 'HC-SEED-CRM-001';
cleanupRecords($pdo, $crmHc);

$lead = $leadModel->create([
    'hc_number' => $crmHc,
    'name' => 'Paciente CRM Uno',
    'email' => 'paciente.crm1@example.com',
    'phone' => '+5930000001',
    'status' => 'nuevo',
    'source' => 'seeder',
    'notes' => 'Generado desde el seeder de identidad',
], 0);

$patient = $patientModel->getDetallePaciente($crmHc);
$customerStmt = $pdo->prepare('SELECT id, name, hc_number FROM crm_customers WHERE hc_number = :hc LIMIT 1');
$customerStmt->execute([':hc' => $crmHc]);
$customer = $customerStmt->fetch(PDO::FETCH_ASSOC) ?: [];

dumpResult('Sincronización desde CRM', [
    'lead' => $lead,
    'crm_customer' => $customer,
    'patient_data' => $patient,
]);

// 2) Crear/actualizar ficha clínica y forzar sincronización inversa hacia CRM
$clinicalHc = 'HC-SEED-CLIN-001';
cleanupRecords($pdo, $clinicalHc);

$identityService->syncPatient($clinicalHc, [
    'fname' => 'Paciente',
    'lname' => 'Clínico',
    'afiliacion' => 'Particular',
    'celular' => '+5930000002',
]);

$identity = $identityService->ensureIdentity($clinicalHc, [
    'customer' => [
        'name' => 'Paciente Clínico',
        'phone' => '+5930000002',
        'affiliation' => 'Particular',
        'source' => 'seeder',
    ],
    'patient' => [
        'fname' => 'Paciente',
        'lname' => 'Clínico',
        'celular' => '+5930000002',
    ],
]);

$leadFromClinical = $identityService->syncLead($clinicalHc, [
    'name' => 'Paciente Clínico',
    'phone' => '+5930000002',
    'source' => 'clinical-record',
    'customer_id' => $identity['customer_id'] ?? null,
], true);

$patientClinical = $patientModel->getDetallePaciente($clinicalHc);
$customerStmt->execute([':hc' => $clinicalHc]);
$customerClinical = $customerStmt->fetch(PDO::FETCH_ASSOC) ?: [];

dumpResult('Sincronización desde ficha clínica', [
    'identity' => $identity,
    'lead' => $leadFromClinical,
    'crm_customer' => $customerClinical,
    'patient_data' => $patientClinical,
]);

echo str_repeat('=', 80) . PHP_EOL;
echo 'Seeder completado. Revisa los resultados anteriores para validar la sincronización.' . PHP_EOL;
