<?php

namespace Modules\Doctores\Controllers;

use Core\BaseController;
use DateTimeImmutable;
use DateTimeInterface;
use Modules\Doctores\Models\DoctorModel;
use PDO;
use PDOException;

class DoctoresController extends BaseController
{
    private DoctorModel $doctors;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->doctors = new DoctorModel($pdo);
    }

    public function index(): void
    {
        $this->requireAuth();

        $doctors = $this->doctors->all();

        $this->render(BASE_PATH . '/modules/Doctores/views/index.php', [
            'pageTitle' => 'Doctores',
            'doctors' => $doctors,
            'totalDoctors' => count($doctors),
        ]);
    }

    public function show(int $doctorId): void
    {
        $this->requireAuth();

        $doctor = $this->doctors->find($doctorId);
        if ($doctor === null) {
            header('Location: /doctores');
            exit;
        }

        $selectedDate = isset($_GET['fecha']) ? trim((string) $_GET['fecha']) : null;
        if ($selectedDate !== null) {
            $selectedDate = $this->sanitizeDateInput($selectedDate);
        }

        $insights = $this->buildDoctorInsights($doctor, $selectedDate);

        // JSON mode for AJAX requests on appointments (no full page render)
        if (isset($_GET['json']) && $_GET['json'] === '1') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'selectedDate' => $insights['appointmentsSelectedDate'],
                'selectedLabel' => $insights['appointmentsSelectedLabel'],
                'appointments' => $insights['appointments'],
                'days' => $insights['appointmentsDays'],
            ]);
            return;
        }

        $this->render(
            BASE_PATH . '/modules/Doctores/views/show.php',
            array_merge(
                $insights,
                [
                    'pageTitle' => $doctor['display_name'] ?? $doctor['name'] ?? 'Doctor',
                    'doctor' => $doctor,
                ]
            )
        );
    }

    /**
     * @param array<string, mixed> $doctor
     * @return array<string, mixed>
     */
    private function buildDoctorInsights(array $doctor, ?string $selectedDate): array
    {
        $baseSeed = (string)($doctor['id'] ?? $doctor['name'] ?? '0');

        $appointmentsSchedule = $this->buildAppointmentsSchedule($doctor, $baseSeed, $selectedDate);

        return [
            'todayPatients' => $this->buildTodayPatients($doctor, $baseSeed),
            'activityStats' => $this->buildActivityStats($doctor, $baseSeed),
            'careProgress' => $this->buildCareProgress($doctor, $baseSeed),
            'milestones' => $this->buildMilestones($doctor, $baseSeed),
            'biographyParagraphs' => $this->buildBiographyParagraphs($doctor, $baseSeed),
            'availabilitySummary' => $this->buildAvailabilitySummary($doctor, $baseSeed),
            'focusAreas' => $this->buildFocusAreas($doctor),
            'supportChannels' => $this->buildSupportChannels($doctor, $baseSeed),
            'researchHighlights' => $this->buildResearchHighlights($doctor, $baseSeed),
            'appointmentsDays' => $appointmentsSchedule['days'],
            'appointments' => $appointmentsSchedule['appointments'],
            'appointmentsSelectedDate' => $appointmentsSchedule['selectedDate'],
            'appointmentsSelectedLabel' => $appointmentsSchedule['selectedLabel'],
        ];
    }

    /**
     * @param array<string, mixed> $doctor
     * @return array<int, array<string, string>>
     */
    private function buildTodayPatients(array $doctor, string $seed): array
    {
        $realPatients = $this->loadTodayPatientsFromDatabase($doctor);
        if (!empty($realPatients)) {
            return $realPatients;
        }

        $patients = [
            ['time' => '08:30', 'name' => 'Lucía Paredes', 'diagnosis' => 'Control de seguimiento', 'avatar' => 'images/avatar/1.jpg'],
            ['time' => '09:15', 'name' => 'Andrés Villamar', 'diagnosis' => 'Evaluación de laboratorio', 'avatar' => 'images/avatar/2.jpg'],
            ['time' => '10:30', 'name' => 'María Fernanda León', 'diagnosis' => 'Consulta preventiva', 'avatar' => 'images/avatar/3.jpg'],
            ['time' => '11:00', 'name' => 'Carlos Gutiérrez', 'diagnosis' => 'Revisión postoperatoria', 'avatar' => 'images/avatar/4.jpg'],
            ['time' => '11:45', 'name' => 'Gabriela Intriago', 'diagnosis' => 'Seguimiento crónico', 'avatar' => 'images/avatar/5.jpg'],
            ['time' => '12:30', 'name' => 'Xavier Molina', 'diagnosis' => 'Ajuste de medicación', 'avatar' => 'images/avatar/6.jpg'],
        ];

        $ordered = $this->seededSlice($patients, $seed . '|today', 3);

        return array_map(
            function (array $patient, int $index): array {
                $times = ['08:30', '09:15', '10:30', '11:00', '11:45', '12:30'];
                $patient['time'] = $patient['time'] ?? ($times[$index] ?? '10:00');

                return $patient;
            },
            $ordered,
            array_keys($ordered)
        );
    }

    /**
     * @param array<string, mixed> $doctor
     * @return array{days: array<int, array<string, mixed>>, appointments: array<int, array<string, mixed>>, selectedDate: ?string, selectedLabel: ?string}
     */
    private function buildAppointmentsSchedule(array $doctor, string $seed, ?string $requestedDate): array
    {
        $appointments = $this->loadAppointmentsFromDatabase($doctor, $requestedDate);

        if (empty($appointments)) {
            $appointments = $this->buildFallbackAppointments($seed);
        }

        if (empty($appointments)) {
            return [
                'days' => [],
                'appointments' => [],
                'selectedDate' => $requestedDate,
                'selectedLabel' => null,
            ];
        }

        $grouped = [];
        foreach ($appointments as $appointment) {
            $dateKey = $appointment['date'] ?? null;
            if ($dateKey === null) {
                continue;
            }

            if (!isset($grouped[$dateKey])) {
                $grouped[$dateKey] = [];
            }

            $grouped[$dateKey][] = $appointment;
        }

        if (empty($grouped)) {
            return [
                'days' => [],
                'appointments' => [],
                'selectedDate' => $requestedDate,
                'selectedLabel' => null,
            ];
        }

        $dates = array_keys($grouped);
        sort($dates);

        if ($requestedDate === null || !isset($grouped[$requestedDate])) {
            $requestedDate = $dates[0];
        }

        $selectedAppointments = $grouped[$requestedDate] ?? [];
        $selectedLabel = $requestedDate !== null ? $this->formatSelectedDateLabel($requestedDate) : null;

        $days = [];
        foreach ($dates as $date) {
            $dateObj = DateTimeImmutable::createFromFormat('Y-m-d', $date);
            if ($dateObj === false) {
                try {
                    $dateObj = new DateTimeImmutable($date);
                } catch (\Exception) {
                    $dateObj = new DateTimeImmutable('today');
                }
            }

            $days[] = [
                'date' => $date,
                'label' => $this->formatPaginatorLabel($dateObj),
                'title' => $this->formatSelectedDateLabel($date),
                'is_today' => $date === date('Y-m-d'),
                'is_selected' => $date === $requestedDate,
            ];
        }

        return [
            'days' => $days,
            'appointments' => $selectedAppointments,
            'selectedDate' => $requestedDate,
            'selectedLabel' => $selectedLabel,
        ];
    }

    /**
     * @param array<string, mixed> $doctor
     * @return array<int, array<string, string>>
     */
    private function loadTodayPatientsFromDatabase(array $doctor): array
    {
        $lookupValues = $this->resolveDoctorLookupValues($doctor);
        if (empty($lookupValues)) {
            return [];
        }

        $attempts = [
            $this->buildDoctorClause($lookupValues, false),
            $this->buildDoctorClause($lookupValues, true),
        ];

        $dateClauses = [
            'DATE(pp.fecha) = CURDATE()',
            'DATE(pp.fecha) >= CURDATE()',
        ];

        foreach ($attempts as [$clause, $params]) {
            if (!$clause) {
                continue;
            }

            foreach ($dateClauses as $dateClause) {
                $patients = $this->runTodayPatientsQuery($clause, $params, $dateClause);
                if (!empty($patients)) {
                    return $patients;
                }
            }
        }

        return [];
    }

    /**
     * @param array<int, string> $lookupValues
     * @return array{0: string, 1: array<string, string>}
     */
    private function buildDoctorClause(array $lookupValues, bool $useLike): array
    {
        $conditions = [];
        $params = [];

        foreach (array_values($lookupValues) as $index => $value) {
            $param = sprintf(':%sdoctor_%d', $useLike ? 'like_' : '', $index);
            $conditions[] = $useLike
                ? "LOWER(pp.doctor) LIKE $param"
                : "LOWER(pp.doctor) = $param";

            $normalized = $this->normalizeLower($value);
            $params[$param] = $useLike ? '%' . $normalized . '%' : $normalized;
        }

        return [
            $conditions ? '(' . implode(' OR ', $conditions) . ')' : '',
            $params,
        ];
    }

    /**
     * @param array<string, string> $params
     * @return array<int, array<string, string>>
     */
    private function runTodayPatientsQuery(string $doctorClause, array $params, string $dateClause): array
    {
        if ($doctorClause === '') {
            return [];
        }

        $sql = <<<SQL
            SELECT
                pp.fecha,
                pp.hora,
                pp.hc_number,
                pp.procedimiento_proyectado,
                pp.estado_agenda,
                TRIM(
                    CONCAT_WS(
                        ' ',
                        NULLIF(p.fname, ''),
                        NULLIF(p.mname, ''),
                        NULLIF(p.lname, ''),
                        NULLIF(p.lname2, '')
                    )
                ) AS patient_name
            FROM procedimiento_proyectado pp
            LEFT JOIN patient_data p ON p.hc_number = pp.hc_number
            WHERE $doctorClause
              AND $dateClause
              AND pp.fecha IS NOT NULL
              AND pp.hora IS NOT NULL
              AND pp.hora <> ''
              AND (
                  pp.estado_agenda IS NULL
                  OR UPPER(pp.estado_agenda) NOT IN (
                      'ANULADO', 'CANCELADO', 'NO ASISTE', 'NO ASISTIO',
                      'NO SE PRESENTO', 'NO SE PRESENTÓ', 'NO-SE-PRESENTO'
                  )
              )
            ORDER BY pp.fecha ASC, pp.hora ASC
            LIMIT 3
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException) {
            return [];
        }

        if (empty($rows)) {
            return [];
        }

        $patients = [];
        foreach ($rows as $index => $row) {
            $patients[] = [
                'time' => $this->formatHourLabel($row['hora'] ?? null),
                'name' => $this->formatPatientName($row['patient_name'] ?? null, $row['hc_number'] ?? null),
                'diagnosis' => $this->formatDiagnosis($row['procedimiento_proyectado'] ?? null),
                'avatar' => $this->resolvePatientAvatar($row['hc_number'] ?? null, $index),
            ];
        }

        return $patients;
    }

    /**
     * @param array<string, mixed> $doctor
     * @return array<int, array<string, mixed>>
     */
    private function loadAppointmentsFromDatabase(array $doctor, ?string $requestedDate): array
    {
        $lookupValues = $this->resolveDoctorLookupValues($doctor);
        if (empty($lookupValues)) {
            return [];
        }

        $attempts = [
            $this->buildDoctorClause($lookupValues, false),
            $this->buildDoctorClause($lookupValues, true),
        ];

        // Build date window around the requested date (fallback to today)
        $center = $this->normalizeDateKey($requestedDate) ?? date('Y-m-d');
        $centerDt = \DateTimeImmutable::createFromFormat('Y-m-d', $center) ?: new \DateTimeImmutable('today');
        $start1 = $centerDt->modify('-0 day')->format('Y-m-d');
        $end1   = $centerDt->modify('+7 day')->format('Y-m-d');
        $start2 = $centerDt->modify('-1 day')->format('Y-m-d');
        $end2   = $centerDt->modify('+21 day')->format('Y-m-d');
        $start3 = $centerDt->modify('-30 day')->format('Y-m-d');
        $dateClauses = [
            "DATE(pp.fecha) BETWEEN '{$start1}' AND '{$end1}'",
            "DATE(pp.fecha) BETWEEN '{$start2}' AND '{$end2}'",
            "DATE(pp.fecha) >= '{$start3}'",
        ];

        foreach ($attempts as [$clause, $params]) {
            if ($clause === '') {
                continue;
            }

            foreach ($dateClauses as $dateClause) {
                $appointments = $this->runAppointmentsQuery($clause, $params, $dateClause);
                if (!empty($appointments)) {
                    return $appointments;
                }
            }
        }

        return [];
    }

    /**
     * @param array<string, string> $params
     * @return array<int, array<string, mixed>>
     */
    private function runAppointmentsQuery(string $doctorClause, array $params, string $dateClause): array
    {
        $sql = <<<SQL
            SELECT
                DATE(pp.fecha) AS appointment_date,
                pp.hora,
                pp.hc_number,
                pp.procedimiento_proyectado,
                pp.estado_agenda,
                pp.afiliacion,
                TRIM(
                    CONCAT_WS(
                        ' ',
                        NULLIF(p.fname, ''),
                        NULLIF(p.mname, ''),
                        NULLIF(p.lname, ''),
                        NULLIF(p.lname2, '')
                    )
                ) AS patient_name,
                p.celular
            FROM procedimiento_proyectado pp
            LEFT JOIN patient_data p ON p.hc_number = pp.hc_number
            WHERE $doctorClause
              AND $dateClause
              AND pp.fecha IS NOT NULL
              AND pp.hora IS NOT NULL
              AND pp.hora <> ''
              AND (
                    pp.estado_agenda IS NULL
                    OR UPPER(pp.estado_agenda) NOT IN (
                        'ANULADO', 'ANULADA', 'CANCELADO', 'CANCELADA',
                        'NO ASISTE', 'NO ASISTIO', 'NO SE PRESENTO', 'NO SE PRESENTÓ', 'NO-SE-PRESENTO'
                    )
                )
            ORDER BY appointment_date ASC, pp.hora ASC
            LIMIT 60
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException) {
            return [];
        }

        if (empty($rows)) {
            return [];
        }

        $appointments = [];
        foreach ($rows as $index => $row) {
            $dateKey = $this->normalizeDateKey($row['appointment_date'] ?? null);
            if ($dateKey === null) {
                continue;
            }

            $callHref = $this->formatCallHref($row['celular'] ?? null);

            $appointments[] = [
                'date' => $dateKey,
                'time' => $this->formatHourLabel($row['hora'] ?? null),
                'patient' => $this->formatPatientName($row['patient_name'] ?? null, $row['hc_number'] ?? null),
                'procedure' => $this->formatDiagnosis($row['procedimiento_proyectado'] ?? null),
                'status_label' => $this->formatStatusLabel($row['estado_agenda'] ?? null),
                'status_variant' => $this->resolveStatusVariant($row['estado_agenda'] ?? null),
                'afiliacion_label' => $this->formatAfiliacionLabel($row['afiliacion'] ?? null),
                'hc_label' => $this->formatHcLabel($row['hc_number'] ?? null),
                'call_href' => $callHref ?? 'javascript:void(0);',
                'call_disabled' => $callHref === null,
                'avatar' => $this->resolvePatientAvatar($row['hc_number'] ?? null, $index),
            ];
        }

        return $appointments;
    }

    private function normalizeDateKey($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            $timestamp = strtotime($value);
            return $timestamp === false ? null : date('Y-m-d', $timestamp);
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }

    private function formatStatusLabel(?string $status): ?string
    {
        if ($status === null) {
            return null;
        }

        $status = trim($status);
        if ($status === '') {
            return null;
        }

        if (function_exists('mb_convert_case')) {
            return mb_convert_case($status, MB_CASE_TITLE, 'UTF-8');
        }

        return ucwords(strtolower($status));
    }

    private function resolveStatusVariant(?string $status): string
    {
        if ($status === null) {
            return 'secondary';
        }

        $normalized = strtoupper(trim($status));

        if ($normalized === '') {
            return 'secondary';
        }

        $successStatuses = ['CONFIRMADO', 'CONFIRMADA', 'LLEGADO', 'LLEGADA', 'ATENDIDO', 'ATENDIDA', 'FACTURADO', 'FACTURADA', 'EN CONSULTA'];
        $warningStatuses = ['REPROGRAMADO', 'REPROGRAMADA', 'REAGENDADO', 'REAGENDADA', 'EN ESPERA'];
        $primaryStatuses = ['AGENDADO', 'AGENDADA', 'PENDIENTE', 'REGISTRADO', 'REGISTRADA', 'ASIGNADO', 'ASIGNADA'];
        $dangerStatuses = ['ANULADO', 'ANULADA', 'CANCELADO', 'CANCELADA', 'NO ASISTE', 'NO ASISTIO', 'NO SE PRESENTO', 'NO SE PRESENTÓ', 'NO-SE-PRESENTO'];

        if (in_array($normalized, $successStatuses, true)) {
            return 'success';
        }

        if (in_array($normalized, $warningStatuses, true)) {
            return 'warning';
        }

        if (in_array($normalized, $dangerStatuses, true)) {
            return 'danger';
        }

        if (in_array($normalized, $primaryStatuses, true)) {
            return 'primary';
        }

        return 'secondary';
    }

    private function formatAfiliacionLabel(?string $afiliacion): string
    {
        $value = $afiliacion !== null ? trim($afiliacion) : '';
        if ($value === '') {
            $value = 'Particular';
        }

        if (function_exists('mb_convert_case')) {
            $value = mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
        } else {
            $value = ucwords(strtolower($value));
        }

        return 'Afiliación: ' . $value;
    }

    private function formatHcLabel(?string $hcNumber): ?string
    {
        if ($hcNumber === null) {
            return null;
        }

        $hcNumber = trim($hcNumber);
        if ($hcNumber === '') {
            return null;
        }

        return 'HC ' . $hcNumber;
    }

    private function formatCallHref(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === null || $digits === '' || strlen($digits) < 7) {
            return null;
        }

        return 'tel:' . $digits;
    }

    private function formatSelectedDateLabel(string $date): string
    {
        $dateObj = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if ($dateObj === false) {
            $timestamp = strtotime($date);
            if ($timestamp === false) {
                return $date;
            }

            $dateObj = (new DateTimeImmutable('@' . $timestamp))->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        }

        $dayNames = [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo',
        ];
        $monthNames = [
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

        $dayName = $dayNames[(int) $dateObj->format('N')] ?? $dateObj->format('l');
        $monthName = $monthNames[(int) $dateObj->format('n')] ?? strtolower($dateObj->format('F'));

        $formattedMonth = function_exists('mb_convert_case')
            ? mb_convert_case($monthName, MB_CASE_TITLE, 'UTF-8')
            : ucfirst($monthName);

        return sprintf('%s, %d de %s de %s', $dayName, (int) $dateObj->format('j'), $formattedMonth, $dateObj->format('Y'));
    }

    private function formatPaginatorLabel(DateTimeInterface $date): string
    {
        $dayNames = [
            1 => 'Lun',
            2 => 'Mar',
            3 => 'Mié',
            4 => 'Jue',
            5 => 'Vie',
            6 => 'Sáb',
            7 => 'Dom',
        ];

        $day = $dayNames[(int) $date->format('N')] ?? $date->format('D');
        $dayNumber = (int) $date->format('j');

        return sprintf('%s<br>%dº', $day, $dayNumber);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildFallbackAppointments(string $seed): array
    {
        $names = ['Juan Andrade', 'María Zambrano', 'Pedro Alcívar', 'Ana Dávila', 'Rosa Medina', 'Daniela Chicaiza', 'Luis Ortiz', 'Patricia Reyes'];
        $procedures = [
            'Consulta de seguimiento',
            'Control de laboratorio',
            'Evaluación preoperatoria',
            'Terapia de rehabilitación',
            'Ajuste de tratamiento',
            'Teleconsulta de resultados',
        ];
        $statuses = ['Agendado', 'Confirmado', 'Llegado', 'Reprogramado'];
        $afiliaciones = ['Particular', 'Seguro Privado', 'IESS', 'Convenio Empresarial'];
        $times = ['08:15', '09:40', '10:20', '11:30', '14:00', '15:15', '16:45'];

        $appointments = [];
        $perDay = 3;
        $days = 5;
        $today = new DateTimeImmutable('today');

        for ($i = 0; $i < $perDay * $days; $i++) {
            $dayOffset = intdiv($i, $perDay);
            $date = $today->modify('+' . $dayOffset . ' day')->format('Y-m-d');

            $name = $names[$this->seededRange($seed . '|appt|name|' . $i, 0, count($names) - 1)];
            $procedure = $procedures[$this->seededRange($seed . '|appt|procedure|' . $i, 0, count($procedures) - 1)];
            $statusRaw = $statuses[$this->seededRange($seed . '|appt|status|' . $i, 0, count($statuses) - 1)];
            $afiliacion = $afiliaciones[$this->seededRange($seed . '|appt|afiliacion|' . $i, 0, count($afiliaciones) - 1)];
            $hcNumber = (string) (20000 + $this->seededRange($seed . '|appt|hc|' . $i, 0, 7999));
            $time = $times[$i % count($times)];

            $appointments[] = [
                'date' => $date,
                'time' => $this->formatHourLabel($time),
                'patient' => $name,
                'procedure' => $procedure,
                'status_label' => $this->formatStatusLabel($statusRaw) ?? 'Agendado',
                'status_variant' => $this->resolveStatusVariant($statusRaw),
                'afiliacion_label' => $this->formatAfiliacionLabel($afiliacion),
                'hc_label' => $this->formatHcLabel($hcNumber),
                'call_href' => 'javascript:void(0);',
                'call_disabled' => true,
                'avatar' => $this->resolvePatientAvatar($hcNumber, $i),
            ];
        }

        return $appointments;
    }

    private function sanitizeDateInput(string $value): ?string
    {
        return $this->normalizeDateKey($value);
    }

    /**
     * @param array<string, mixed> $doctor
     * @return array<int, string>
     */
    private function resolveDoctorLookupValues(array $doctor): array
    {
        $candidates = [];
        foreach (['name', 'display_name', 'username'] as $key) {
            if (!empty($doctor[$key]) && is_string($doctor[$key])) {
                $candidates[] = $doctor[$key];
            }
        }

        if (!empty($doctor['email']) && is_string($doctor['email'])) {
            $candidates[] = $doctor['email'];
        }

        $variants = [];
        foreach ($candidates as $candidate) {
            $trimmed = trim((string) $candidate);
            if ($trimmed === '') {
                continue;
            }

            $variants[] = $trimmed;

            $withoutTitle = preg_replace('/^(dr\.?|dra\.?)\s*/i', '', $trimmed) ?? $trimmed;
            if ($withoutTitle !== '') {
                $variants[] = trim($withoutTitle);
            }

            if (!preg_match('/^(dr\.?|dra\.?)/i', $trimmed)) {
                $variants[] = 'Dr. ' . $trimmed;
                $variants[] = 'Dra. ' . $trimmed;
            }

            foreach (preg_split('/\s+-\s+/u', $trimmed) ?: [] as $part) {
                $part = trim($part);
                if ($part !== '' && $part !== $trimmed) {
                    $variants[] = $part;
                }
            }

            foreach (preg_split('/\s*[\/|]\s+/u', $trimmed) ?: [] as $part) {
                $part = trim($part);
                if ($part !== '' && $part !== $trimmed) {
                    $variants[] = $part;
                }
            }
        }

        $unique = [];
        $result = [];
        foreach ($variants as $variant) {
            $normalized = $this->normalizeLower($variant);
            if ($variant === '' || isset($unique[$normalized])) {
                continue;
            }

            $unique[$normalized] = true;
            $result[] = $variant;
        }

        return $result;
    }

    private function normalizeLower(string $value): string
    {
        return function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);
    }

    private function formatHourLabel(?string $time): string
    {
        if ($time === null) {
            return '--:--';
        }

        $normalized = trim((string) $time);
        if ($normalized === '') {
            return '--:--';
        }

        $normalized = str_ireplace(['a.m.', 'p.m.'], ['am', 'pm'], $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
        $timestamp = strtotime($normalized);

        if ($timestamp !== false) {
            return strtolower(date('g:ia', $timestamp));
        }

        $formats = ['H:i:s', 'H:i'];
        foreach ($formats as $format) {
            $dt = \DateTime::createFromFormat($format, $normalized);
            if ($dt !== false) {
                return strtolower($dt->format('g:ia'));
            }
        }

        return $normalized;
    }

    private function formatPatientName(?string $name, ?string $hcNumber): string
    {
        $trimmed = trim((string) $name);
        if ($trimmed !== '') {
            return $trimmed;
        }

        $hcNumber = $hcNumber !== null ? trim((string) $hcNumber) : '';
        if ($hcNumber !== '') {
            return 'HC ' . $hcNumber;
        }

        return 'Paciente sin nombre';
    }

    private function formatDiagnosis(?string $diagnosis): string
    {
        $diagnosis = $diagnosis ?? '';
        $diagnosis = trim($diagnosis);
        if ($diagnosis === '') {
            return 'Consulta programada';
        }

        $diagnosis = preg_replace('/\s+/', ' ', $diagnosis) ?? $diagnosis;
        return $this->truncateText($diagnosis, 80);
    }

    private function resolvePatientAvatar(?string $hcNumber, int $position): string
    {
        $seed = $hcNumber !== null && $hcNumber !== ''
            ? abs((int) crc32($hcNumber))
            : $position;

        $index = ($seed % 8) + 1;

        return sprintf('images/avatar/%d.jpg', $index);
    }

    private function truncateText(string $text, int $limit): string
    {
        if ($limit <= 1) {
            return $text;
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($text, 'UTF-8') > $limit) {
                return rtrim(mb_substr($text, 0, $limit - 1, 'UTF-8')) . '…';
            }

            return $text;
        }

        if (strlen($text) > $limit) {
            return rtrim(substr($text, 0, $limit - 1)) . '…';
        }

        return $text;
    }

    /**
     * @param array<string, mixed> $doctor
     * @return array<int, array<string, mixed>>
     */
    private function buildActivityStats(array $doctor, string $seed): array
    {
        $patientsMonth = $this->seededRange($seed . '|patients_month', 32, 96);
        $procedures = $this->seededRange($seed . '|procedures', 12, 48);
        $satisfaction = $this->seededRange($seed . '|satisfaction', 86, 98);

        return [
            [
                'label' => 'Pacientes atendidos este mes',
                'value' => $patientsMonth,
                'suffix' => '',
                'trend' => $this->formatTrend($this->seededRange($seed . '|patients_trend', -6, 12)),
            ],
            [
                'label' => 'Procedimientos resueltos',
                'value' => $procedures,
                'suffix' => '',
                'trend' => $this->formatTrend($this->seededRange($seed . '|procedures_trend', -4, 9)),
            ],
            [
                'label' => 'Índice de satisfacción',
                'value' => $satisfaction,
                'suffix' => '%',
                'trend' => $this->formatTrend($this->seededRange($seed . '|satisfaction_trend', -2, 5)),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $doctor
     * @return array<int, array<string, mixed>>
     */
    private function buildCareProgress(array $doctor, string $seed): array
    {
        $specialty = strtolower((string)($doctor['especialidad'] ?? ''));
        $defaults = [
            ['label' => 'Controles preventivos', 'variant' => 'primary'],
            ['label' => 'Tratamientos activos', 'variant' => 'success'],
            ['label' => 'Seguimientos virtuales', 'variant' => 'info'],
            ['label' => 'Rehabilitación', 'variant' => 'warning'],
            ['label' => 'Casos críticos', 'variant' => 'danger'],
        ];

        if (str_contains($specialty, 'gine') || str_contains($specialty, 'obst')) {
            $defaults = [
                ['label' => 'Controles prenatales', 'variant' => 'primary'],
                ['label' => 'Seguimiento postparto', 'variant' => 'success'],
                ['label' => 'Planificación familiar', 'variant' => 'info'],
                ['label' => 'Procedimientos quirúrgicos', 'variant' => 'warning'],
                ['label' => 'Casos de alto riesgo', 'variant' => 'danger'],
            ];
        } elseif (str_contains($specialty, 'cardio')) {
            $defaults = [
                ['label' => 'Control hipertensión', 'variant' => 'primary'],
                ['label' => 'Rehabilitación cardiaca', 'variant' => 'success'],
                ['label' => 'Telemonitorización', 'variant' => 'info'],
                ['label' => 'Intervenciones programadas', 'variant' => 'warning'],
                ['label' => 'Casos críticos', 'variant' => 'danger'],
            ];
        } elseif (str_contains($specialty, 'pedi')) {
            $defaults = [
                ['label' => 'Vacunas al día', 'variant' => 'primary'],
                ['label' => 'Controles de crecimiento', 'variant' => 'success'],
                ['label' => 'Teleconsulta familiar', 'variant' => 'info'],
                ['label' => 'Casos respiratorios', 'variant' => 'warning'],
                ['label' => 'Casos críticos', 'variant' => 'danger'],
            ];
        }

        return array_map(
            function (array $item, int $index) use ($seed): array {
                $percentage = $this->seededRange($seed . '|progress|' . $index, 48, 96);
                $item['value'] = $percentage;

                return $item;
            },
            $defaults,
            array_keys($defaults)
        );
    }

    /**
     * @param array<string, mixed> $doctor
     * @return array<int, array<string, string>>
     */
    private function buildMilestones(array $doctor, string $seed): array
    {
        $displayName = $doctor['display_name'] ?? $doctor['name'] ?? 'El especialista';
        $baseYear = 2006 + ($this->seededRange($seed . '|milestone_base', 0, 6));

        return [
            [
                'year' => (string)($baseYear),
                'title' => 'Inicio de la práctica profesional',
                'description' => sprintf('%s se incorporó al staff de la clínica con un enfoque en atención integral.', $displayName),
            ],
            [
                'year' => (string)($baseYear + 5),
                'title' => 'Implementación de protocolos especializados',
                'description' => 'Lideró la adopción de guías clínicas basadas en evidencia para optimizar los resultados de los pacientes.',
            ],
            [
                'year' => (string)($baseYear + 9),
                'title' => 'Coordinación de programa multidisciplinario',
                'description' => 'Integró equipos de enfermería, rehabilitación y apoyo social para acompañar a los pacientes complejos.',
            ],
            [
                'year' => 'Actualidad',
                'title' => 'Mentoría y formación continua',
                'description' => 'Actualmente lidera sesiones de actualización clínica y acompaña a residentes en el desarrollo de casos complejos.',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $doctor
     * @return array<int, string>
     */
    private function buildBiographyParagraphs(array $doctor, string $seed): array
    {
        $displayName = $doctor['display_name'] ?? $doctor['name'] ?? 'El especialista';
        $specialty = $doctor['especialidad'] ?? 'medicina integral';
        $location = $doctor['sede'] ?? 'nuestras sedes principales';
        $years = $this->seededRange($seed . '|experience_years', 7, 18);
        $patients = $this->seededRange($seed . '|patients_total', 1200, 3600);

        return [
            sprintf('%s cuenta con %d años de experiencia en %s y acompaña a los pacientes en %s. Ha desarrollado estrategias de atención personalizada que combinan evidencia científica con un trato cercano.', $displayName, $years, strtolower($specialty), $location),
            sprintf('Durante su trayectoria ha coordinado más de %d procesos de diagnóstico y seguimiento, trabajando de la mano con equipos interdisciplinarios para garantizar continuidad asistencial y mejora sostenida de los indicadores clínicos.', $patients),
            'Su práctica clínica incorpora tableros de monitoreo en tiempo real, seguimiento proactivo de signos de alerta y sesiones educativas con pacientes y familias para reforzar la adherencia a los tratamientos.',
        ];
    }

    /**
     * @param array<string, mixed> $doctor
     * @return array<string, mixed>
     */
    private function buildAvailabilitySummary(array $doctor, string $seed): array
    {
        $startHour = $this->seededRange($seed . '|start_hour', 7, 9);
        $endHour = $startHour + $this->seededRange($seed . '|work_length', 7, 9);
        $virtualSlots = $this->seededRange($seed . '|virtual_slots', 2, 6);
        $inPersonSlots = $this->seededRange($seed . '|in_person_slots', 5, 12);
        $responseHours = $this->seededRange($seed . '|response_hours', 4, 12);

        return [
            'working_days_label' => 'Lunes a Viernes',
            'working_hours_label' => sprintf('%02d:00 - %02d:00', $startHour, $endHour),
            'virtual_slots' => $virtualSlots,
            'in_person_slots' => $inPersonSlots,
            'response_time_hours' => $responseHours,
            'primary_location' => $doctor['sede'] ?? 'Consultorio principal',
        ];
    }

    /**
     * @param array<string, mixed> $doctor
     * @return array<int, string>
     */
    private function buildFocusAreas(array $doctor): array
    {
        $specialty = strtolower((string)($doctor['especialidad'] ?? ''));
        $areas = ['Atención basada en evidencia', 'Coordinación inter-disciplinaria', 'Seguimiento remoto de pacientes'];

        if (str_contains($specialty, 'gine') || str_contains($specialty, 'obst')) {
            $areas = array_merge($areas, ['Salud materno-fetal', 'Educación prenatal', 'Planificación familiar']);
        } elseif (str_contains($specialty, 'cardio')) {
            $areas = array_merge($areas, ['Prevención cardiovascular', 'Rehabilitación cardíaca', 'Telemetría clínica']);
        } elseif (str_contains($specialty, 'pedi')) {
            $areas = array_merge($areas, ['Control del desarrollo infantil', 'Programas de vacunación', 'Educación familiar']);
        } elseif (str_contains($specialty, 'derma')) {
            $areas = array_merge($areas, ['Dermatoscopía digital', 'Protocolos de fototerapia', 'Prevención de cáncer de piel']);
        }

        return array_values(array_unique($areas));
    }

    /**
     * @param array<string, mixed> $doctor
     * @return array<int, array<string, string>>
     */
    private function buildSupportChannels(array $doctor, string $seed): array
    {
        $assistants = [
            ['label' => 'Asistente clínica', 'value' => 'María Silva · Ext. 204'],
            ['label' => 'Coordinación quirúrgica', 'value' => 'Carlos Benítez · Ext. 219'],
            ['label' => 'Gestor de seguros', 'value' => 'Paola Díaz · Ext. 132'],
            ['label' => 'Soporte telemedicina', 'value' => 'telemedicina@medforge.com'],
            ['label' => 'Línea de emergencias', 'value' => '+593 99 123 4567'],
        ];

        return $this->seededSlice($assistants, $seed . '|channels', 4);
    }

    /**
     * @param array<string, mixed> $doctor
     * @return array<int, array<string, string>>
     */
    private function buildResearchHighlights(array $doctor, string $seed): array
    {
        $specialty = strtolower((string)($doctor['especialidad'] ?? ''));
        $topics = [
            'Aplicación de analítica predictiva en seguimiento ambulatorio',
            'Protocolos colaborativos con enfermería avanzada',
            'Optimización de rutas asistenciales con tableros operativos',
        ];

        if (str_contains($specialty, 'gine') || str_contains($specialty, 'obst')) {
            $topics[] = 'Resultados materno-fetales en programas de alto riesgo';
        } elseif (str_contains($specialty, 'cardio')) {
            $topics[] = 'Uso de telemetría para pacientes con insuficiencia cardíaca';
        } elseif (str_contains($specialty, 'pedi')) {
            $topics[] = 'Innovación en monitoreo remoto pediátrico';
        }

        $years = [2019, 2020, 2021, 2022, 2023];

        $highlights = [];
        foreach ($this->seededSlice($topics, $seed . '|research', 3) as $index => $topic) {
            $year = $years[$this->seededRange($seed . '|research_year|' . $index, 0, count($years) - 1)];
            $highlights[] = [
                'year' => (string)$year,
                'title' => $topic,
                'description' => 'Documento presentado en jornadas científicas internas con propuestas para fortalecer la práctica clínica.',
            ];
        }

        return $highlights;
    }

    /**
     * @param list<array<string, string>> $items
     * @return list<array<string, string>>
     */
    private function seededSlice(array $items, string $seed, int $length): array
    {
        $hash = crc32($seed);
        $count = count($items);
        if ($count === 0) {
            return [];
        }

        $offset = $hash % $count;
        $ordered = [];
        for ($i = 0; $i < $count; $i++) {
            $ordered[] = $items[($offset + $i) % $count];
        }

        return array_slice($ordered, 0, min($length, $count));
    }

    private function seededRange(string $seed, int $min, int $max): int
    {
        if ($max <= $min) {
            return $min;
        }

        $hash = crc32($seed);
        $range = $max - $min + 1;

        return $min + ($hash % $range);
    }

    /**
     * @return array<string, string>
     */
    private function formatTrend(int $value): array
    {
        $direction = $value >= 0 ? 'up' : 'down';
        $formatted = ($value > 0 ? '+' : '') . $value . '%';

        return [
            'value' => $formatted,
            'direction' => $direction,
        ];
    }
}
