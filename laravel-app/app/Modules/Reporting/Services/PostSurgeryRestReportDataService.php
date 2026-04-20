<?php

namespace App\Modules\Reporting\Services;

use DateInterval;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use PDO;

class PostSurgeryRestReportDataService
{
    private const DEFAULT_REST_DAYS = 5;
    private const MIN_REST_DAYS = 1;
    private const MAX_REST_DAYS = 30;

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? DB::connection()->getPdo();
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>|null
     */
    public function buildData(string $formId, string $hcNumber, array $options = []): ?array
    {
        $formId = trim($formId);
        $hcNumber = trim($hcNumber);
        if ($formId === '' || $hcNumber === '') {
            return null;
        }

        $surgery = $this->fetchSurgery($formId, $hcNumber);
        if ($surgery === null) {
            return null;
        }

        $today = new DateTimeImmutable('today');
        $surgeryDate = $this->parseDate($surgery['fecha_inicio'] ?? null);
        $restDays = $this->normalizeRestDays($options['dias_descanso'] ?? null);
        $restStart = $this->parseDate($options['fecha_inicio_descanso'] ?? null) ?? $surgeryDate ?? $today;
        $restEnd = $restStart->add(new DateInterval('P' . max(0, $restDays - 1) . 'D'));

        $patientName = $this->buildPatientFullName($surgery);
        $birthDate = $this->parseDate($surgery['fecha_nacimiento'] ?? null);
        $age = null;
        if ($birthDate !== null) {
            $referenceDate = $surgeryDate ?? $today;
            $age = $birthDate->diff($referenceDate)->y;
        }

        $diagnosticosIngreso = $this->extractDiagnoses($surgery['diagnosticos_previos'] ?? null);
        $diagnosticosEgreso = $this->extractDiagnoses($surgery['diagnosticos'] ?? null);
        $diagnosticos = $diagnosticosEgreso !== [] ? $diagnosticosEgreso : $diagnosticosIngreso;
        if ($diagnosticos === []) {
            $diagnosticos = ['Diagnostico postquirurgico.'];
        }

        $doctorName = trim((string) ($surgery['doctor_proyectado'] ?? ''));
        if ($doctorName === '') {
            $doctorName = trim((string) ($surgery['cirujano_1'] ?? ''));
        }

        $doctor = $this->resolveDoctor($doctorName);
        $observaciones = $this->normalizeObservaciones($options['observaciones'] ?? null);
        $patientDocument = $this->resolvePatientDocument($surgery, $hcNumber);
        $patientAddress = $this->resolvePatientAddress($surgery);
        $patientPhone = $this->resolvePatientPhone($surgery);
        $patientInstitution = $this->resolveInstitution($surgery);
        $patientOccupation = $this->resolveOccupation($surgery);
        $issueCity = $this->resolveIssueCity($surgery);
        $dischargeDate = $this->parseDate($surgery['fecha_fin'] ?? null) ?? $surgeryDate ?? $today;
        $contingency = trim((string) ($options['tipo_contingencia'] ?? ''));

        return [
            'certificado_numero' => $this->buildCertificateNumber($formId, $hcNumber),
            'fecha_emision' => $today->format('Y-m-d'),
            'fecha_emision_legible' => $this->formatDateSpanish($today),
            'ciudad_emision' => $issueCity,
            'form_id' => (string) ($surgery['form_id'] ?? $formId),
            'hc_number' => (string) ($surgery['hc_number'] ?? $hcNumber),
            'paciente' => [
                'nombre' => $patientName,
                'identificacion' => $patientDocument,
                'historia_clinica' => (string) ($surgery['hc_number'] ?? $hcNumber),
                'edad' => $age,
                'sexo' => $this->normalizeSexLabel((string) ($surgery['sexo'] ?? '')),
                'afiliacion' => $patientInstitution,
                'domicilio' => $patientAddress,
                'telefono' => $patientPhone,
                'empresa_institucion' => $patientInstitution,
                'puesto_trabajo' => $patientOccupation,
            ],
            'procedimiento' => $this->extractProcedureName($surgery),
            'fecha_cirugia' => $surgeryDate?->format('Y-m-d'),
            'fecha_cirugia_legible' => $surgeryDate ? $this->formatDateSpanish($surgeryDate) : '',
            'fecha_egreso' => $dischargeDate->format('Y-m-d'),
            'fecha_egreso_legible' => $this->formatDateSpanish($dischargeDate),
            'diagnosticos' => $diagnosticos,
            'diagnostico_ingreso' => $diagnosticosIngreso[0] ?? ($diagnosticos[0] ?? ''),
            'diagnostico_egreso' => $diagnosticosEgreso[0] ?? ($diagnosticos[0] ?? ''),
            'tipo_contingencia' => $contingency,
            'tratamiento' => $observaciones,
            'reposo' => [
                'dias' => $restDays,
                'dias_en_letras' => $this->numberToSpanishWords($restDays),
                'desde' => $restStart->format('Y-m-d'),
                'desde_legible' => $this->formatDateSpanish($restStart),
                'hasta' => $restEnd->format('Y-m-d'),
                'hasta_legible' => $this->formatDateSpanish($restEnd),
            ],
            'doctor' => $doctor,
            'observaciones' => $observaciones,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchSurgery(string $formId, string $hcNumber): ?array
    {
        $sql = <<<'SQL'
            SELECT
                p.*,
                pr.form_id,
                pr.fecha_inicio,
                pr.fecha_fin,
                pr.cirujano_1,
                pr.membrete,
                pr.procedimientos,
                pr.diagnosticos,
                pr.diagnosticos_previos,
                pp.doctor AS doctor_proyectado,
                pp.procedimiento_proyectado,
                pp.sede_departamento,
                pp.afiliacion AS proyeccion_afiliacion
            FROM protocolo_data pr
            INNER JOIN patient_data p ON p.hc_number = pr.hc_number
            LEFT JOIN procedimiento_proyectado pp
                ON pp.form_id = pr.form_id
               AND pp.hc_number = pr.hc_number
            WHERE pr.form_id = :form_id
              AND pr.hc_number = :hc_number
            LIMIT 1
        SQL;

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':form_id' => $formId,
            ':hc_number' => $hcNumber,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function buildPatientFullName(array $row): string
    {
        $parts = [
            trim((string) ($row['fname'] ?? '')),
            trim((string) ($row['mname'] ?? '')),
            trim((string) ($row['lname'] ?? '')),
            trim((string) ($row['lname2'] ?? '')),
        ];

        $parts = array_values(array_filter($parts, static fn(string $part): bool => $part !== ''));

        return trim(implode(' ', $parts));
    }

    private function normalizeSexLabel(string $sex): string
    {
        $value = strtoupper(trim($sex));

        return match ($value) {
            'F', 'FEMENINO' => 'Femenino',
            'M', 'MASCULINO' => 'Masculino',
            default => $sex !== '' ? ucfirst(strtolower($sex)) : 'No especificado',
        };
    }

    private function normalizeRestDays(mixed $value): int
    {
        if (!is_numeric($value)) {
            return self::DEFAULT_REST_DAYS;
        }

        $days = (int) $value;
        if ($days < self::MIN_REST_DAYS) {
            return self::MIN_REST_DAYS;
        }

        if ($days > self::MAX_REST_DAYS) {
            return self::MAX_REST_DAYS;
        }

        return $days;
    }

    private function extractProcedureName(array $row): string
    {
        $membrete = trim((string) ($row['membrete'] ?? ''));
        if ($membrete !== '') {
            return $membrete;
        }

        $projected = trim((string) ($row['procedimiento_proyectado'] ?? ''));
        if ($projected !== '') {
            $parts = array_map('trim', explode(' - ', $projected, 3));
            if (isset($parts[2]) && $parts[2] !== '') {
                return $parts[2];
            }

            return $projected;
        }

        $procedimientos = $this->decodeJsonArray($row['procedimientos'] ?? null);
        foreach ($procedimientos as $procedimiento) {
            if (!is_array($procedimiento)) {
                continue;
            }

            $candidate = trim((string) ($procedimiento['procInterno'] ?? $procedimiento['codigo'] ?? ''));
            if ($candidate === '') {
                continue;
            }

            $parts = array_map('trim', explode(' - ', $candidate, 3));
            if (isset($parts[2]) && $parts[2] !== '') {
                return $parts[2];
            }

            return $candidate;
        }

        return 'Procedimiento quirurgico';
    }

    /**
     * @return array<int, string>
     */
    private function extractDiagnoses(mixed $value): array
    {
        $diagnoses = [];
        $items = $this->decodeJsonArray($value);

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $raw = trim((string) ($item['idDiagnostico'] ?? ''));
            $cie10 = trim((string) ($item['cie10'] ?? ''));
            $descripcion = trim((string) ($item['descripcion'] ?? ''));

            if ($raw !== '' && $descripcion === '' && $cie10 === '') {
                $parts = preg_split('/\s*-\s*/', $raw, 2);
                $cie10 = trim((string) ($parts[0] ?? ''));
                $descripcion = trim((string) ($parts[1] ?? ''));
            }

            if ($descripcion === '' && $raw !== '') {
                $descripcion = $raw;
            }

            $entry = trim($descripcion . ($cie10 !== '' ? ' (CIE10: ' . $cie10 . ')' : ''));
            if ($entry === '' || in_array($entry, $diagnoses, true)) {
                continue;
            }

            $diagnoses[] = $entry;
            if (count($diagnoses) >= 3) {
                break;
            }
        }

        return $diagnoses;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveDoctor(string $doctorName): array
    {
        $fallbackName = trim($doctorName);
        $doctor = [];
        if ($fallbackName !== '') {
            $doctor = $this->findDoctorByExactName($fallbackName)
                ?? $this->findDoctorByTokenName($fallbackName)
                ?? [];
        }

        $name = $fallbackName !== '' ? $fallbackName : 'Medico tratante';

        return [
            'nombre' => $name,
            'cedula' => trim((string) ($doctor['cedula'] ?? '')),
            'registro' => trim((string) ($doctor['registro'] ?? '')),
            'especialidad' => trim((string) ($doctor['especialidad'] ?? 'CIRUGIA OFTALMOLOGICA')),
            'firma' => trim((string) ($doctor['firma'] ?? '')),
            'signature_path' => trim((string) ($doctor['signature_path'] ?? '')),
        ];
    }

    private function resolvePatientDocument(array $row, string $hcNumber): string
    {
        foreach (['cedula', 'ci', 'identificacion'] as $field) {
            $value = trim((string) ($row[$field] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return (string) ($row['hc_number'] ?? $hcNumber);
    }

    private function resolvePatientAddress(array $row): string
    {
        foreach (['direccion', 'address', 'ciudad'] as $field) {
            $value = trim((string) ($row[$field] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function resolvePatientPhone(array $row): string
    {
        foreach (['celular', 'telefono', 'phone'] as $field) {
            $value = trim((string) ($row[$field] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function resolveInstitution(array $row): string
    {
        return trim((string) ($row['lugar_trabajo'] ?? ''));
    }

    private function resolveOccupation(array $row): string
    {
        foreach (['ocupacion', 'puesto_trabajo', 'lugar_trabajo', 'trabajo'] as $field) {
            $value = trim((string) ($row[$field] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function resolveIssueCity(array $row): string
    {
        $sede = $this->normalizeSiteName((string) ($row['sede_departamento'] ?? $row['sede'] ?? ''));
        if ($sede === 'MATRIZ') {
            return 'Daule';
        }
        if ($sede === 'CEIBOS') {
            return 'Guayaquil';
        }

        $city = trim((string) ($row['ciudad'] ?? ''));
        if ($city !== '') {
            return $city;
        }

        return '';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findDoctorByExactName(string $doctorName): ?array
    {
        $normalizedTarget = $this->normalizePersonName($doctorName);
        if ($normalizedTarget === '') {
            return null;
        }

        $stmt = $this->db->query('SELECT * FROM users WHERE nombre IS NOT NULL AND TRIM(nombre) <> ""');
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $candidate = $this->extractDoctorDisplayName($row);
            if ($candidate === '') {
                continue;
            }

            if ($this->normalizePersonName($candidate) === $normalizedTarget) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findDoctorByTokenName(string $doctorName): ?array
    {
        $targetTokens = $this->personNameTokens($doctorName);
        if ($targetTokens === []) {
            return null;
        }

        $stmt = $this->db->query('SELECT * FROM users WHERE nombre IS NOT NULL AND TRIM(nombre) <> ""');
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $candidateTokens = $this->personNameTokens($this->extractDoctorDisplayName($row));
            if ($candidateTokens === []) {
                continue;
            }

            if ($candidateTokens === $targetTokens) {
                return $row;
            }
        }

        return null;
    }

    private function extractDoctorDisplayName(array $doctor): string
    {
        $name = trim((string) ($doctor['nombre'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        return trim(implode(' ', array_filter([
            trim((string) ($doctor['first_name'] ?? '')),
            trim((string) ($doctor['middle_name'] ?? '')),
            trim((string) ($doctor['last_name'] ?? '')),
            trim((string) ($doctor['second_last_name'] ?? '')),
        ])));
    }

    private function normalizePersonName(string $value): string
    {
        $value = $this->asciiUpper($value);
        $value = preg_replace('/[^A-Z0-9 ]+/', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/', ' ', trim($value)) ?? trim($value);

        return $value;
    }

    /**
     * @return array<int, string>
     */
    private function personNameTokens(string $value): array
    {
        $normalized = $this->normalizePersonName($value);
        if ($normalized === '') {
            return [];
        }

        $tokens = preg_split('/\s+/', $normalized) ?: [];
        $tokens = array_values(array_filter($tokens, static fn(string $token): bool => $token !== ''));
        sort($tokens);

        return $tokens;
    }

    private function normalizeSiteName(string $value): string
    {
        $normalized = $this->asciiUpper($value);

        if (str_contains($normalized, 'CEIB')) {
            return 'CEIBOS';
        }

        if (str_contains($normalized, 'MATRIZ')) {
            return 'MATRIZ';
        }

        return trim($normalized);
    }

    private function asciiUpper(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($transliterated) && $transliterated !== '') {
            $value = $transliterated;
        }

        return strtoupper($value);
    }

    private function normalizeObservaciones(mixed $value): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return 'Guardar reposo relativo, evitar esfuerzos fisicos y acudir a control postoperatorio segun indicacion medica.';
        }

        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        if (mb_strlen($text) > 600) {
            return trim(mb_substr($text, 0, 600)) . '...';
        }

        return $text;
    }

    private function buildCertificateNumber(string $formId, string $hcNumber): string
    {
        $cleanForm = preg_replace('/[^A-Za-z0-9]/', '', $formId) ?? $formId;
        $cleanHc = preg_replace('/[^A-Za-z0-9]/', '', $hcNumber) ?? $hcNumber;

        return sprintf('CDP-%s-%s', $cleanForm, $cleanHc);
    }

    private function parseDate(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $datePart = substr($value, 0, 10);

        $fromYmd = DateTimeImmutable::createFromFormat('Y-m-d', $datePart);
        if ($fromYmd !== false) {
            return $fromYmd;
        }

        $fromDmy = DateTimeImmutable::createFromFormat('d/m/Y', $datePart);
        if ($fromDmy !== false) {
            return $fromDmy;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return (new DateTimeImmutable())->setTimestamp($timestamp);
    }

    private function formatDateSpanish(DateTimeImmutable $date): string
    {
        $months = [
            1 => 'enero',
            2 => 'febrero',
            3 => 'marzo',
            4 => 'abril',
            5 => 'mayo',
            6 => 'junio',
            7 => 'julio',
            8 => 'agosto',
            9 => 'septiembre',
            10 => 'octubre',
            11 => 'noviembre',
            12 => 'diciembre',
        ];

        $month = $months[(int) $date->format('n')] ?? '';

        return sprintf('%s de %s de %s', $date->format('d'), $month, $date->format('Y'));
    }

    /**
     * @return array<int, mixed>
     */
    private function decodeJsonArray(mixed $value): array
    {
        if (!is_string($value)) {
            return [];
        }

        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    private function numberToSpanishWords(int $number): string
    {
        $map = [
            0 => 'cero',
            1 => 'uno',
            2 => 'dos',
            3 => 'tres',
            4 => 'cuatro',
            5 => 'cinco',
            6 => 'seis',
            7 => 'siete',
            8 => 'ocho',
            9 => 'nueve',
            10 => 'diez',
            11 => 'once',
            12 => 'doce',
            13 => 'trece',
            14 => 'catorce',
            15 => 'quince',
            16 => 'dieciseis',
            17 => 'diecisiete',
            18 => 'dieciocho',
            19 => 'diecinueve',
            20 => 'veinte',
            21 => 'veintiuno',
            22 => 'veintidos',
            23 => 'veintitres',
            24 => 'veinticuatro',
            25 => 'veinticinco',
            26 => 'veintiseis',
            27 => 'veintisiete',
            28 => 'veintiocho',
            29 => 'veintinueve',
            30 => 'treinta',
        ];

        return $map[$number] ?? (string) $number;
    }
}
