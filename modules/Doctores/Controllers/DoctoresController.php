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
    /** @var array<int, array<string, mixed>> */
    private array $doctorPerformanceCache = [];
    /** @var array<int, array<string, mixed>> */
    private array $doctorCardCache = [];

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->doctors = new DoctorModel($pdo);
    }

    public function index(): void
    {
        $this->requireAuth();

        $doctors = array_map(function (array $doctor): array {
            $performance = $this->getDoctorCardPerformanceSummary($doctor);
            $doctor['performance_summary'] = $performance['rating'] ?? [];
            $doctor['performance_quick_stats'] = $performance['quick_stats'] ?? [];

            return $doctor;
        }, $this->doctors->all());

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
        $appointmentsSchedule = $this->buildAppointmentsSchedule($doctor, '', $selectedDate);
        $performance = $this->getDoctorPerformanceContext($doctor);
        $todayPatients = $this->buildTodayPatients($doctor, '');

        return [
            'todayPatients' => $todayPatients,
            'activityStats' => $this->buildActivityStats($doctor, ''),
            'careProgress' => $this->buildCareProgress($doctor, ''),
            'milestones' => $this->buildMilestones($doctor, ''),
            'biographyParagraphs' => $this->buildBiographyParagraphs($doctor, ''),
            'availabilitySummary' => $this->buildAvailabilitySummary($doctor, ''),
            'focusAreas' => $this->buildFocusAreas($doctor),
            'supportChannels' => $this->buildSupportChannels($doctor, ''),
            'researchHighlights' => $this->buildResearchHighlights($doctor, ''),
            'performanceSummary' => $performance['rating'] ?? [],
            'operationalNotes' => $performance['operational_notes'] ?? [],
            'recentSurgeries' => $performance['recent_surgeries'] ?? [],
            'recentRequests' => $performance['recent_requests'] ?? [],
            'recentExams' => $performance['recent_exams'] ?? [],
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
        return !empty($realPatients) ? $realPatients : [];
    }

    /**
     * @param array<string, mixed> $doctor
     * @return array{days: array<int, array<string, mixed>>, appointments: array<int, array<string, mixed>>, selectedDate: ?string, selectedLabel: ?string}
     */
    private function buildAppointmentsSchedule(array $doctor, string $seed, ?string $requestedDate): array
    {
        $appointments = $this->loadAppointmentsFromDatabase($doctor, $requestedDate);

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
        $context = $this->getDoctorPerformanceContext($doctor);
        $agenda = $context['agenda_30'] ?? [];
        $agendaPrev = $context['agenda_prev_30'] ?? [];
        $surgeries = $context['surgeries_90'] ?? [];
        $surgeriesPrev = $context['surgeries_prev_90'] ?? [];
        $exams = $context['exams_30'] ?? [];
        $examsPrev = $context['exams_prev_30'] ?? [];

        return [
            [
                'label' => 'Pacientes 30d',
                'value' => (int) ($agenda['unique_patients'] ?? 0),
                'suffix' => '',
                'trend' => $this->buildTrend(
                    (int) ($agenda['unique_patients'] ?? 0),
                    (int) ($agendaPrev['unique_patients'] ?? 0)
                ),
            ],
            [
                'label' => 'Citas atendidas 30d',
                'value' => (int) ($agenda['completed'] ?? 0),
                'suffix' => '',
                'trend' => $this->buildTrend(
                    (int) ($agenda['completed'] ?? 0),
                    (int) ($agendaPrev['completed'] ?? 0)
                ),
            ],
            [
                'label' => 'Cirugías 90d',
                'value' => (int) ($surgeries['total'] ?? 0),
                'suffix' => '',
                'trend' => $this->buildTrend(
                    (int) ($surgeries['total'] ?? 0),
                    (int) ($surgeriesPrev['total'] ?? 0)
                ),
            ],
            [
                'label' => 'Exámenes solicitados 30d',
                'value' => (int) ($exams['total'] ?? 0),
                'suffix' => '',
                'trend' => $this->buildTrend(
                    (int) ($exams['total'] ?? 0),
                    (int) ($examsPrev['total'] ?? 0)
                ),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $doctor
     * @return array<int, array<string, mixed>>
     */
    private function buildCareProgress(array $doctor, string $seed): array
    {
        $context = $this->getDoctorPerformanceContext($doctor);
        $agenda = $context['agenda_30'] ?? [];
        $surgeries = $context['surgeries_90'] ?? [];
        $requests = $context['requests_90'] ?? [];

        $agendaTotal = max(0, (int) ($agenda['total'] ?? 0));
        $agendaCompleted = max(0, (int) ($agenda['completed'] ?? 0));
        $agendaLost = max(0, (int) ($agenda['lost'] ?? 0));
        $surgeriesTotal = max(0, (int) ($surgeries['total'] ?? 0));
        $surgeriesReviewed = max(0, (int) ($surgeries['reviewed'] ?? 0));
        $requestsTotal = max(0, (int) ($requests['total'] ?? 0));

        return [
            [
                'label' => 'Agenda efectiva',
                'variant' => 'primary',
                'value' => $this->safePercentage($agendaCompleted, $agendaTotal),
            ],
            [
                'label' => 'Control de ausentismo',
                'variant' => 'success',
                'value' => $agendaTotal > 0 ? max(0, 100 - $this->safePercentage($agendaLost, $agendaTotal)) : 0,
            ],
            [
                'label' => 'Protocolos revisados',
                'variant' => 'warning',
                'value' => $this->safePercentage($surgeriesReviewed, $surgeriesTotal),
            ],
            [
                'label' => 'Conversión a cirugía',
                'variant' => 'info',
                'value' => min(100, $this->safePercentage($surgeriesTotal, $requestsTotal)),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $doctor
     * @return array<int, array<string, string>>
     */
    private function buildMilestones(array $doctor, string $seed): array
    {
        $context = $this->getDoctorPerformanceContext($doctor);

        return $context['recent_activity'] ?? [];
    }

    /**
     * @param array<string, mixed> $doctor
     * @return array<int, string>
     */
    private function buildBiographyParagraphs(array $doctor, string $seed): array
    {
        $context = $this->getDoctorPerformanceContext($doctor);

        return $context['summary_paragraphs'] ?? [];
    }

    /**
     * @param array<string, mixed> $doctor
     * @return array<string, mixed>
     */
    private function buildAvailabilitySummary(array $doctor, string $seed): array
    {
        $context = $this->getDoctorPerformanceContext($doctor);
        $upcoming = $context['agenda_next_7'] ?? [];

        return [
            'schedule_label' => $this->buildScheduleLabel(
                (string) ($upcoming['weekdays'] ?? ''),
                (string) ($upcoming['first_hour'] ?? ''),
                (string) ($upcoming['last_hour'] ?? '')
            ),
            'next_7d_appointments' => (int) ($upcoming['total'] ?? 0),
            'today_patients' => (int) ($context['today_patients_count'] ?? 0),
            'latest_surgery_label' => $this->formatRecencyLabel((string) ($context['surgeries_90']['latest_date'] ?? '')),
            'latest_exam_label' => $this->formatRecencyLabel((string) ($context['exams_30']['latest_date'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $doctor
     * @return array<int, string>
     */
    private function buildFocusAreas(array $doctor): array
    {
        $context = $this->getDoctorPerformanceContext($doctor);
        $areas = is_array($context['focus_areas'] ?? null) ? $context['focus_areas'] : [];

        if ($areas !== []) {
            return $areas;
        }

        $specialty = trim((string) ($doctor['especialidad'] ?? ''));
        return $specialty !== '' ? [$specialty] : [];
    }

    /**
     * @param array<string, mixed> $doctor
     * @return array<int, array<string, string>>
     */
    private function buildSupportChannels(array $doctor, string $seed): array
    {
        $context = $this->getDoctorPerformanceContext($doctor);

        return $context['distribution_rows'] ?? [];
    }

    /**
     * @param array<string, mixed> $doctor
     * @return array<int, array<string, string>>
     */
    private function buildResearchHighlights(array $doctor, string $seed): array
    {
        $context = $this->getDoctorPerformanceContext($doctor);
        $mixRows = is_array($context['mix_rows'] ?? null) ? $context['mix_rows'] : [];

        return array_map(static function (array $row): array {
            return [
                'year' => (string) ($row['source'] ?? 'Actividad'),
                'title' => (string) ($row['label'] ?? 'Sin detalle'),
                'description' => sprintf('%s registro(s) en la ventana operativa analizada.', number_format((int) ($row['count'] ?? 0))),
            ];
        }, array_slice($mixRows, 0, 5));
    }

    /**
     * @param array<string, mixed> $doctor
     * @return array<string, mixed>
     */
    private function getDoctorPerformanceContext(array $doctor): array
    {
        $doctorId = (int) ($doctor['id'] ?? 0);
        $cacheKey = $doctorId > 0 ? $doctorId : abs((int) crc32(implode('|', $this->resolveDoctorLookupValues($doctor))));

        if (isset($this->doctorPerformanceCache[$cacheKey])) {
            return $this->doctorPerformanceCache[$cacheKey];
        }

        $today = new DateTimeImmutable('today');
        $agenda30 = $this->loadDoctorAgendaAggregate($doctor, $today->modify('-29 days'), $today);
        $agendaPrev30 = $this->loadDoctorAgendaAggregate($doctor, $today->modify('-59 days'), $today->modify('-30 days'));
        $agendaNext7 = $this->loadDoctorAgendaAggregate($doctor, $today, $today->modify('+6 days'), true);
        $surgeries90 = $this->loadDoctorSurgeryAggregate($doctor, $today->modify('-89 days'), $today);
        $surgeriesPrev90 = $this->loadDoctorSurgeryAggregate($doctor, $today->modify('-179 days'), $today->modify('-90 days'));
        $requests90 = $this->loadDoctorRequestAggregate($doctor, $today->modify('-89 days'), $today);
        $requestsPrev90 = $this->loadDoctorRequestAggregate($doctor, $today->modify('-179 days'), $today->modify('-90 days'));
        $exams30 = $this->loadDoctorExamAggregate($doctor, $today->modify('-29 days'), $today);
        $examsPrev30 = $this->loadDoctorExamAggregate($doctor, $today->modify('-59 days'), $today->modify('-30 days'));

        $recentSurgeries = $this->loadRecentSurgeries($doctor, 5);
        $recentRequests = $this->loadRecentRequests($doctor, 5);
        $recentExams = $this->loadRecentExams($doctor, 5);
        $agendaMix = $this->loadTopAgendaProcedures($doctor, 4);
        $surgeryMix = $this->loadTopSurgeryProcedures($doctor, 4);
        $examMix = $this->loadTopExamProcedures($doctor, 4);

        $mixRows = array_merge(
            array_map(static fn(array $row): array => ['source' => 'Agenda', 'label' => (string) ($row['label'] ?? ''), 'count' => (int) ($row['count'] ?? 0)], $agendaMix),
            array_map(static fn(array $row): array => ['source' => 'Cirugías', 'label' => (string) ($row['label'] ?? ''), 'count' => (int) ($row['count'] ?? 0)], $surgeryMix),
            array_map(static fn(array $row): array => ['source' => 'Exámenes', 'label' => (string) ($row['label'] ?? ''), 'count' => (int) ($row['count'] ?? 0)], $examMix),
        );
        usort($mixRows, static fn(array $a, array $b): int => ((int) ($b['count'] ?? 0)) <=> ((int) ($a['count'] ?? 0)));

        $todayPatientsCount = count($this->loadTodayPatientsFromDatabase($doctor));
        $rating = $this->buildDoctorRating($agenda30, $surgeries90, $requests90, $exams30);
        $recentActivity = $this->buildRecentActivityTimeline($recentSurgeries, $recentRequests, $recentExams);

        $context = [
            'agenda_30' => $agenda30,
            'agenda_prev_30' => $agendaPrev30,
            'agenda_next_7' => $agendaNext7,
            'surgeries_90' => $surgeries90,
            'surgeries_prev_90' => $surgeriesPrev90,
            'requests_90' => $requests90,
            'requests_prev_90' => $requestsPrev90,
            'exams_30' => $exams30,
            'exams_prev_30' => $examsPrev30,
            'today_patients_count' => $todayPatientsCount,
            'recent_surgeries' => $recentSurgeries,
            'recent_requests' => $recentRequests,
            'recent_exams' => $recentExams,
            'recent_activity' => $recentActivity,
            'mix_rows' => $mixRows,
            'focus_areas' => $this->extractFocusAreas($mixRows, $doctor),
            'distribution_rows' => $this->buildDistributionRows($agenda30, $surgeries90, $requests90, $exams30),
            'rating' => $rating,
            'quick_stats' => $this->buildQuickStats($agenda30, $surgeries90, $rating),
            'summary_paragraphs' => $this->buildSummaryParagraphs($doctor, $agenda30, $surgeries90, $requests90, $exams30, $rating),
            'operational_notes' => $this->buildOperationalNotes($agenda30, $surgeries90, $requests90, $exams30),
        ];

        $this->doctorPerformanceCache[$cacheKey] = $context;

        return $context;
    }

    /**
     * @param array<string, mixed> $doctor
     * @return array<string, mixed>
     */
    private function getDoctorCardPerformanceSummary(array $doctor): array
    {
        $doctorId = (int) ($doctor['id'] ?? 0);
        $cacheKey = $doctorId > 0 ? $doctorId : abs((int) crc32(implode('|', $this->resolveDoctorLookupValues($doctor))));

        if (isset($this->doctorCardCache[$cacheKey])) {
            return $this->doctorCardCache[$cacheKey];
        }

        $today = new DateTimeImmutable('today');
        $agenda30 = $this->loadDoctorAgendaAggregate($doctor, $today->modify('-29 days'), $today);
        $surgeries90 = $this->loadDoctorSurgeryAggregate($doctor, $today->modify('-89 days'), $today);
        $rating = $this->buildDoctorRating($agenda30, $surgeries90, ['total' => 0], ['total' => 0]);

        $summary = [
            'rating' => $rating,
            'quick_stats' => $this->buildQuickStats($agenda30, $surgeries90, $rating),
        ];

        $this->doctorCardCache[$cacheKey] = $summary;

        return $summary;
    }

    /**
     * @return array{total:int,unique_patients:int,completed:int,lost:int,rescheduled:int,first_hour:string,last_hour:string,weekdays:string,latest_date:string}
     */
    private function loadDoctorAgendaAggregate(array $doctor, DateTimeInterface $start, DateTimeInterface $end, bool $excludeCancelled = false): array
    {
        $empty = [
            'total' => 0,
            'unique_patients' => 0,
            'completed' => 0,
            'lost' => 0,
            'rescheduled' => 0,
            'first_hour' => '',
            'last_hour' => '',
            'weekdays' => '',
            'latest_date' => '',
        ];
        $lookupValues = $this->resolveDoctorLookupValues($doctor);
        if ($lookupValues === []) {
            return $empty;
        }

        $attempts = [
            $this->buildMatchClause($lookupValues, 'pp.doctor', false, 'agenda_eq_'),
            $this->buildMatchClause($lookupValues, 'pp.doctor', true, 'agenda_like_'),
        ];

        foreach ($attempts as [$clause, $params]) {
            if ($clause === '') {
                continue;
            }

            $result = $this->runDoctorAgendaAggregateQuery($clause, $params, $start, $end, $excludeCancelled);
            if ((int) ($result['total'] ?? 0) > 0) {
                return $result;
            }
        }

        return $empty;
    }

    /**
     * @param array<string, string> $params
     * @return array{total:int,unique_patients:int,completed:int,lost:int,rescheduled:int,first_hour:string,last_hour:string,weekdays:string,latest_date:string}
     */
    private function runDoctorAgendaAggregateQuery(
        string $doctorClause,
        array $params,
        DateTimeInterface $start,
        DateTimeInterface $end,
        bool $excludeCancelled
    ): array {
        $successSql = $this->quoteStringList($this->doctorAgendaSuccessStatuses());
        $dangerSql = $this->quoteStringList($this->doctorAgendaDangerStatuses());
        $warningSql = $this->quoteStringList($this->doctorAgendaWarningStatuses());
        $excludeSql = $excludeCancelled ? ' AND UPPER(TRIM(COALESCE(pp.estado_agenda, ""))) NOT IN (' . $dangerSql . ')' : '';

        $sql = <<<SQL
            SELECT
                COUNT(*) AS total,
                COUNT(DISTINCT CASE
                    WHEN pp.hc_number IS NOT NULL AND TRIM(pp.hc_number) <> '' THEN pp.hc_number
                    ELSE NULL
                END) AS unique_patients,
                SUM(CASE WHEN UPPER(TRIM(COALESCE(pp.estado_agenda, ''))) IN ({$successSql}) THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN UPPER(TRIM(COALESCE(pp.estado_agenda, ''))) IN ({$dangerSql}) THEN 1 ELSE 0 END) AS lost,
                SUM(CASE WHEN UPPER(TRIM(COALESCE(pp.estado_agenda, ''))) IN ({$warningSql}) THEN 1 ELSE 0 END) AS rescheduled,
                MIN(NULLIF(TRIM(pp.hora), '')) AS first_hour,
                MAX(NULLIF(TRIM(pp.hora), '')) AS last_hour,
                GROUP_CONCAT(DISTINCT DAYOFWEEK(pp.fecha) ORDER BY DAYOFWEEK(pp.fecha) SEPARATOR ',') AS weekdays,
                MAX(DATE(pp.fecha)) AS latest_date
            FROM procedimiento_proyectado pp
            WHERE {$doctorClause}
              AND DATE(pp.fecha) BETWEEN :agenda_start AND :agenda_end
              AND pp.procedimiento_proyectado IS NOT NULL
              AND TRIM(pp.procedimiento_proyectado) <> ''
              {$excludeSql}
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_merge($params, [
                ':agenda_start' => $start->format('Y-m-d'),
                ':agenda_end' => $end->format('Y-m-d'),
            ]));
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException) {
            return [
                'total' => 0,
                'unique_patients' => 0,
                'completed' => 0,
                'lost' => 0,
                'rescheduled' => 0,
                'first_hour' => '',
                'last_hour' => '',
                'weekdays' => '',
                'latest_date' => '',
            ];
        }

        return [
            'total' => (int) ($row['total'] ?? 0),
            'unique_patients' => (int) ($row['unique_patients'] ?? 0),
            'completed' => (int) ($row['completed'] ?? 0),
            'lost' => (int) ($row['lost'] ?? 0),
            'rescheduled' => (int) ($row['rescheduled'] ?? 0),
            'first_hour' => trim((string) ($row['first_hour'] ?? '')),
            'last_hour' => trim((string) ($row['last_hour'] ?? '')),
            'weekdays' => trim((string) ($row['weekdays'] ?? '')),
            'latest_date' => trim((string) ($row['latest_date'] ?? '')),
        ];
    }

    /**
     * @return array{total:int,reviewed:int,latest_date:string}
     */
    private function loadDoctorSurgeryAggregate(array $doctor, DateTimeInterface $start, DateTimeInterface $end): array
    {
        $empty = ['total' => 0, 'reviewed' => 0, 'latest_date' => ''];
        $lookupValues = $this->resolveDoctorLookupValues($doctor);
        if ($lookupValues === []) {
            return $empty;
        }

        $attempts = [
            $this->buildMatchClause($lookupValues, 'pd.cirujano_1', false, 'surgery_eq_'),
            $this->buildMatchClause($lookupValues, 'pd.cirujano_1', true, 'surgery_like_'),
        ];

        foreach ($attempts as [$clause, $params]) {
            if ($clause === '') {
                continue;
            }

            $result = $this->runDoctorSurgeryAggregateQuery($clause, $params, $start, $end);
            if ((int) ($result['total'] ?? 0) > 0) {
                return $result;
            }
        }

        return $empty;
    }

    /**
     * @param array<string, string> $params
     * @return array{total:int,reviewed:int,latest_date:string}
     */
    private function runDoctorSurgeryAggregateQuery(string $doctorClause, array $params, DateTimeInterface $start, DateTimeInterface $end): array
    {
        $sql = <<<SQL
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN COALESCE(pd.status, 0) = 1 THEN 1 ELSE 0 END) AS reviewed,
                MAX(DATE(pd.fecha_inicio)) AS latest_date
            FROM protocolo_data pd
            WHERE {$doctorClause}
              AND DATE(pd.fecha_inicio) BETWEEN :surgery_start AND :surgery_end
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_merge($params, [
                ':surgery_start' => $start->format('Y-m-d'),
                ':surgery_end' => $end->format('Y-m-d'),
            ]));
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException) {
            return ['total' => 0, 'reviewed' => 0, 'latest_date' => ''];
        }

        return [
            'total' => (int) ($row['total'] ?? 0),
            'reviewed' => (int) ($row['reviewed'] ?? 0),
            'latest_date' => trim((string) ($row['latest_date'] ?? '')),
        ];
    }

    /**
     * @return array{total:int,unique_patients:int,latest_date:string}
     */
    private function loadDoctorRequestAggregate(array $doctor, DateTimeInterface $start, DateTimeInterface $end): array
    {
        $empty = ['total' => 0, 'unique_patients' => 0, 'latest_date' => ''];
        $lookupValues = $this->resolveDoctorLookupValues($doctor);
        if ($lookupValues === []) {
            return $empty;
        }

        $attempts = [
            $this->buildMatchClause($lookupValues, 'sp.doctor', false, 'request_eq_'),
            $this->buildMatchClause($lookupValues, 'sp.doctor', true, 'request_like_'),
        ];

        foreach ($attempts as [$clause, $params]) {
            if ($clause === '') {
                continue;
            }

            $result = $this->runDoctorRequestAggregateQuery($clause, $params, $start, $end);
            if ((int) ($result['total'] ?? 0) > 0) {
                return $result;
            }
        }

        return $empty;
    }

    /**
     * @param array<string, string> $params
     * @return array{total:int,unique_patients:int,latest_date:string}
     */
    private function runDoctorRequestAggregateQuery(string $doctorClause, array $params, DateTimeInterface $start, DateTimeInterface $end): array
    {
        $sql = <<<SQL
            SELECT
                COUNT(*) AS total,
                COUNT(DISTINCT CASE
                    WHEN sp.hc_number IS NOT NULL AND TRIM(sp.hc_number) <> '' THEN sp.hc_number
                    ELSE NULL
                END) AS unique_patients,
                MAX(DATE(COALESCE(sp.created_at, sp.fecha))) AS latest_date
            FROM solicitud_procedimiento sp
            WHERE {$doctorClause}
              AND DATE(COALESCE(sp.created_at, sp.fecha)) BETWEEN :request_start AND :request_end
              AND sp.procedimiento IS NOT NULL
              AND TRIM(sp.procedimiento) <> ''
              AND TRIM(sp.procedimiento) <> 'SELECCIONE'
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_merge($params, [
                ':request_start' => $start->format('Y-m-d'),
                ':request_end' => $end->format('Y-m-d'),
            ]));
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException) {
            return ['total' => 0, 'unique_patients' => 0, 'latest_date' => ''];
        }

        return [
            'total' => (int) ($row['total'] ?? 0),
            'unique_patients' => (int) ($row['unique_patients'] ?? 0),
            'latest_date' => trim((string) ($row['latest_date'] ?? '')),
        ];
    }

    /**
     * @return array{total:int,unique_patients:int,latest_date:string}
     */
    private function loadDoctorExamAggregate(array $doctor, DateTimeInterface $start, DateTimeInterface $end): array
    {
        $empty = ['total' => 0, 'unique_patients' => 0, 'latest_date' => ''];
        if (!$this->tableExists('consulta_examenes')) {
            return $empty;
        }

        $lookupValues = $this->resolveDoctorLookupValues($doctor);
        if ($lookupValues === []) {
            return $empty;
        }

        $expression = "COALESCE(NULLIF(TRIM(pp.doctor), ''), NULLIF(TRIM(ce.doctor), ''), NULLIF(TRIM(ce.solicitante), ''), '')";
        $attempts = [
            $this->buildMatchClause($lookupValues, $expression, false, 'exam_eq_'),
            $this->buildMatchClause($lookupValues, $expression, true, 'exam_like_'),
        ];

        foreach ($attempts as [$clause, $params]) {
            if ($clause === '') {
                continue;
            }

            $result = $this->runDoctorExamAggregateQuery($clause, $params, $start, $end);
            if ((int) ($result['total'] ?? 0) > 0) {
                return $result;
            }
        }

        return $empty;
    }

    /**
     * @param array<string, string> $params
     * @return array{total:int,unique_patients:int,latest_date:string}
     */
    private function runDoctorExamAggregateQuery(string $doctorClause, array $params, DateTimeInterface $start, DateTimeInterface $end): array
    {
        $sql = <<<SQL
            SELECT
                COUNT(*) AS total,
                COUNT(DISTINCT CASE
                    WHEN ce.hc_number IS NOT NULL AND TRIM(ce.hc_number) <> '' THEN ce.hc_number
                    ELSE NULL
                END) AS unique_patients,
                MAX(DATE(COALESCE(ce.consulta_fecha, ce.created_at))) AS latest_date
            FROM consulta_examenes ce
            LEFT JOIN procedimiento_proyectado pp ON pp.form_id = ce.form_id AND pp.hc_number = ce.hc_number
            WHERE {$doctorClause}
              AND ce.examen_nombre IS NOT NULL
              AND TRIM(ce.examen_nombre) <> ''
              AND DATE(COALESCE(ce.consulta_fecha, ce.created_at)) BETWEEN :exam_start AND :exam_end
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_merge($params, [
                ':exam_start' => $start->format('Y-m-d'),
                ':exam_end' => $end->format('Y-m-d'),
            ]));
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException) {
            return ['total' => 0, 'unique_patients' => 0, 'latest_date' => ''];
        }

        return [
            'total' => (int) ($row['total'] ?? 0),
            'unique_patients' => (int) ($row['unique_patients'] ?? 0),
            'latest_date' => trim((string) ($row['latest_date'] ?? '')),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function loadRecentSurgeries(array $doctor, int $limit = 5): array
    {
        $lookupValues = $this->resolveDoctorLookupValues($doctor);
        if ($lookupValues === []) {
            return [];
        }

        $attempts = [
            $this->buildMatchClause($lookupValues, 'pd.cirujano_1', false, 'recent_surgery_eq_'),
            $this->buildMatchClause($lookupValues, 'pd.cirujano_1', true, 'recent_surgery_like_'),
        ];

        foreach ($attempts as [$clause, $params]) {
            if ($clause === '') {
                continue;
            }

            $rows = $this->runRecentSurgeryQuery($clause, $params, $limit);
            if ($rows !== []) {
                return $rows;
            }
        }

        return [];
    }

    /**
     * @param array<string, string> $params
     * @return array<int, array<string, string>>
     */
    private function runRecentSurgeryQuery(string $doctorClause, array $params, int $limit): array
    {
        $sql = <<<SQL
            SELECT
                DATE(pd.fecha_inicio) AS event_date,
                COALESCE(NULLIF(TRIM(pd.membrete), ''), 'Cirugía sin membrete') AS procedure_label,
                COALESCE(
                    NULLIF(TRIM(CONCAT_WS(' ', NULLIF(p.fname, ''), NULLIF(p.mname, ''), NULLIF(p.lname, ''), NULLIF(p.lname2, ''))), ''),
                    CONCAT('HC ', pd.hc_number)
                ) AS patient_name,
                CASE WHEN COALESCE(pd.status, 0) = 1 THEN 'Revisado' ELSE 'Pendiente de revisión' END AS state_label
            FROM protocolo_data pd
            LEFT JOIN patient_data p ON p.hc_number = pd.hc_number
            WHERE {$doctorClause}
            ORDER BY pd.fecha_inicio DESC
            LIMIT {$limit}
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException) {
            return [];
        }

        return array_map(function (array $row): array {
            return [
                'event_date' => trim((string) ($row['event_date'] ?? '')),
                'label' => $this->truncateText((string) ($row['procedure_label'] ?? 'Cirugía'), 70),
                'patient' => trim((string) ($row['patient_name'] ?? '')),
                'state' => trim((string) ($row['state_label'] ?? '')),
            ];
        }, $rows);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function loadRecentRequests(array $doctor, int $limit = 5): array
    {
        $lookupValues = $this->resolveDoctorLookupValues($doctor);
        if ($lookupValues === []) {
            return [];
        }

        $attempts = [
            $this->buildMatchClause($lookupValues, 'sp.doctor', false, 'recent_request_eq_'),
            $this->buildMatchClause($lookupValues, 'sp.doctor', true, 'recent_request_like_'),
        ];

        foreach ($attempts as [$clause, $params]) {
            if ($clause === '') {
                continue;
            }

            $rows = $this->runRecentRequestQuery($clause, $params, $limit);
            if ($rows !== []) {
                return $rows;
            }
        }

        return [];
    }

    /**
     * @param array<string, string> $params
     * @return array<int, array<string, string>>
     */
    private function runRecentRequestQuery(string $doctorClause, array $params, int $limit): array
    {
        $sql = <<<SQL
            SELECT
                DATE(COALESCE(sp.created_at, sp.fecha)) AS event_date,
                COALESCE(NULLIF(TRIM(sp.procedimiento), ''), 'Solicitud sin procedimiento') AS procedure_label,
                COALESCE(
                    NULLIF(TRIM(CONCAT_WS(' ', NULLIF(p.fname, ''), NULLIF(p.mname, ''), NULLIF(p.lname, ''), NULLIF(p.lname2, ''))), ''),
                    CONCAT('HC ', sp.hc_number)
                ) AS patient_name,
                COALESCE(NULLIF(TRIM(sp.estado), ''), 'Sin estado') AS state_label
            FROM solicitud_procedimiento sp
            LEFT JOIN patient_data p ON p.hc_number = sp.hc_number
            WHERE {$doctorClause}
              AND sp.procedimiento IS NOT NULL
              AND TRIM(sp.procedimiento) <> ''
              AND TRIM(sp.procedimiento) <> 'SELECCIONE'
            ORDER BY COALESCE(sp.created_at, sp.fecha) DESC, sp.id DESC
            LIMIT {$limit}
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException) {
            return [];
        }

        return array_map(function (array $row): array {
            return [
                'event_date' => trim((string) ($row['event_date'] ?? '')),
                'label' => $this->truncateText((string) ($row['procedure_label'] ?? 'Solicitud'), 70),
                'patient' => trim((string) ($row['patient_name'] ?? '')),
                'state' => $this->formatStatusLabel((string) ($row['state_label'] ?? '')) ?? 'Sin estado',
            ];
        }, $rows);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function loadRecentExams(array $doctor, int $limit = 5): array
    {
        if (!$this->tableExists('consulta_examenes')) {
            return [];
        }

        $lookupValues = $this->resolveDoctorLookupValues($doctor);
        if ($lookupValues === []) {
            return [];
        }

        $expression = "COALESCE(NULLIF(TRIM(pp.doctor), ''), NULLIF(TRIM(ce.doctor), ''), NULLIF(TRIM(ce.solicitante), ''), '')";
        $attempts = [
            $this->buildMatchClause($lookupValues, $expression, false, 'recent_exam_eq_'),
            $this->buildMatchClause($lookupValues, $expression, true, 'recent_exam_like_'),
        ];

        foreach ($attempts as [$clause, $params]) {
            if ($clause === '') {
                continue;
            }

            $rows = $this->runRecentExamQuery($clause, $params, $limit);
            if ($rows !== []) {
                return $rows;
            }
        }

        return [];
    }

    /**
     * @param array<string, string> $params
     * @return array<int, array<string, string>>
     */
    private function runRecentExamQuery(string $doctorClause, array $params, int $limit): array
    {
        $sql = <<<SQL
            SELECT
                DATE(COALESCE(ce.consulta_fecha, ce.created_at)) AS event_date,
                COALESCE(NULLIF(TRIM(ce.examen_nombre), ''), 'Examen sin nombre') AS exam_label,
                COALESCE(
                    NULLIF(TRIM(CONCAT_WS(' ', NULLIF(p.fname, ''), NULLIF(p.mname, ''), NULLIF(p.lname, ''), NULLIF(p.lname2, ''))), ''),
                    CONCAT('HC ', ce.hc_number)
                ) AS patient_name
            FROM consulta_examenes ce
            LEFT JOIN procedimiento_proyectado pp ON pp.form_id = ce.form_id AND pp.hc_number = ce.hc_number
            LEFT JOIN patient_data p ON p.hc_number = ce.hc_number
            WHERE {$doctorClause}
              AND ce.examen_nombre IS NOT NULL
              AND TRIM(ce.examen_nombre) <> ''
            ORDER BY COALESCE(ce.consulta_fecha, ce.created_at) DESC
            LIMIT {$limit}
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException) {
            return [];
        }

        return array_map(function (array $row): array {
            return [
                'event_date' => trim((string) ($row['event_date'] ?? '')),
                'label' => $this->truncateText((string) ($row['exam_label'] ?? 'Examen'), 70),
                'patient' => trim((string) ($row['patient_name'] ?? '')),
                'state' => 'Solicitado',
            ];
        }, $rows);
    }

    /**
     * @return array<int, array{label:string,count:int}>
     */
    private function loadTopAgendaProcedures(array $doctor, int $limit = 4): array
    {
        $lookupValues = $this->resolveDoctorLookupValues($doctor);
        if ($lookupValues === []) {
            return [];
        }

        $attempts = [
            $this->buildMatchClause($lookupValues, 'pp.doctor', false, 'mix_agenda_eq_'),
            $this->buildMatchClause($lookupValues, 'pp.doctor', true, 'mix_agenda_like_'),
        ];

        foreach ($attempts as [$clause, $params]) {
            if ($clause === '') {
                continue;
            }

            $rows = $this->runTopAgendaProceduresQuery($clause, $params, $limit);
            if ($rows !== []) {
                return $rows;
            }
        }

        return [];
    }

    /**
     * @param array<string, string> $params
     * @return array<int, array{label:string,count:int}>
     */
    private function runTopAgendaProceduresQuery(string $doctorClause, array $params, int $limit): array
    {
        $sql = <<<SQL
            SELECT
                COALESCE(NULLIF(TRIM(pp.procedimiento_proyectado), ''), 'Sin procedimiento') AS label,
                COUNT(*) AS total
            FROM procedimiento_proyectado pp
            WHERE {$doctorClause}
              AND DATE(pp.fecha) >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
              AND pp.procedimiento_proyectado IS NOT NULL
              AND TRIM(pp.procedimiento_proyectado) <> ''
            GROUP BY label
            ORDER BY total DESC, label ASC
            LIMIT {$limit}
        SQL;

        return $this->runTopLabelsQuery($sql, $params);
    }

    /**
     * @return array<int, array{label:string,count:int}>
     */
    private function loadTopSurgeryProcedures(array $doctor, int $limit = 4): array
    {
        $lookupValues = $this->resolveDoctorLookupValues($doctor);
        if ($lookupValues === []) {
            return [];
        }

        $attempts = [
            $this->buildMatchClause($lookupValues, 'pd.cirujano_1', false, 'mix_surgery_eq_'),
            $this->buildMatchClause($lookupValues, 'pd.cirujano_1', true, 'mix_surgery_like_'),
        ];

        foreach ($attempts as [$clause, $params]) {
            if ($clause === '') {
                continue;
            }

            $sql = <<<SQL
                SELECT
                    COALESCE(NULLIF(TRIM(pd.membrete), ''), 'Cirugía sin membrete') AS label,
                    COUNT(*) AS total
                FROM protocolo_data pd
                WHERE {$clause}
                  AND DATE(pd.fecha_inicio) >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)
                GROUP BY label
                ORDER BY total DESC, label ASC
                LIMIT {$limit}
            SQL;

            $rows = $this->runTopLabelsQuery($sql, $params);
            if ($rows !== []) {
                return $rows;
            }
        }

        return [];
    }

    /**
     * @return array<int, array{label:string,count:int}>
     */
    private function loadTopExamProcedures(array $doctor, int $limit = 4): array
    {
        if (!$this->tableExists('consulta_examenes')) {
            return [];
        }

        $lookupValues = $this->resolveDoctorLookupValues($doctor);
        if ($lookupValues === []) {
            return [];
        }

        $expression = "COALESCE(NULLIF(TRIM(pp.doctor), ''), NULLIF(TRIM(ce.doctor), ''), NULLIF(TRIM(ce.solicitante), ''), '')";
        $attempts = [
            $this->buildMatchClause($lookupValues, $expression, false, 'mix_exam_eq_'),
            $this->buildMatchClause($lookupValues, $expression, true, 'mix_exam_like_'),
        ];

        foreach ($attempts as [$clause, $params]) {
            if ($clause === '') {
                continue;
            }

            $sql = <<<SQL
                SELECT
                    COALESCE(NULLIF(TRIM(ce.examen_nombre), ''), 'Examen sin nombre') AS label,
                    COUNT(*) AS total
                FROM consulta_examenes ce
                LEFT JOIN procedimiento_proyectado pp ON pp.form_id = ce.form_id AND pp.hc_number = ce.hc_number
                WHERE {$clause}
                  AND DATE(COALESCE(ce.consulta_fecha, ce.created_at)) >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                  AND ce.examen_nombre IS NOT NULL
                  AND TRIM(ce.examen_nombre) <> ''
                GROUP BY label
                ORDER BY total DESC, label ASC
                LIMIT {$limit}
            SQL;

            $rows = $this->runTopLabelsQuery($sql, $params);
            if ($rows !== []) {
                return $rows;
            }
        }

        return [];
    }

    /**
     * @param array<string, string> $params
     * @return array<int, array{label:string,count:int}>
     */
    private function runTopLabelsQuery(string $sql, array $params): array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException) {
            return [];
        }

        return array_map(function (array $row): array {
            return [
                'label' => $this->truncateText((string) ($row['label'] ?? 'Sin detalle'), 64),
                'count' => (int) ($row['total'] ?? 0),
            ];
        }, $rows);
    }

    /**
     * @param array<string, mixed> $agenda
     * @param array<string, mixed> $surgeries
     * @param array<string, mixed> $requests
     * @param array<string, mixed> $exams
     * @return array<string, mixed>
     */
    private function buildDoctorRating(array $agenda, array $surgeries, array $requests, array $exams): array
    {
        $agendaTotal = max(0, (int) ($agenda['total'] ?? 0));
        $agendaCompleted = max(0, (int) ($agenda['completed'] ?? 0));
        $agendaLost = max(0, (int) ($agenda['lost'] ?? 0));
        $patients = max(0, (int) ($agenda['unique_patients'] ?? 0));
        $surgeryTotal = max(0, (int) ($surgeries['total'] ?? 0));
        $surgeryReviewed = max(0, (int) ($surgeries['reviewed'] ?? 0));
        $requestTotal = max(0, (int) ($requests['total'] ?? 0));
        $examTotal = max(0, (int) ($exams['total'] ?? 0));

        $volumeScore = min(1.0, $patients / 40);
        $agendaEffectiveness = $agendaTotal > 0 ? ($agendaCompleted / $agendaTotal) : 0.0;
        $noShowControl = $agendaTotal > 0 ? max(0.0, 1 - ($agendaLost / $agendaTotal)) : 0.0;
        $protocolReview = $surgeryTotal > 0 ? ($surgeryReviewed / $surgeryTotal) : 0.60;
        $throughput = min(1.0, (($surgeryTotal * 2) + $requestTotal + $examTotal) / 50);

        $composite = ($volumeScore * 0.30)
            + ($agendaEffectiveness * 0.25)
            + ($noShowControl * 0.15)
            + ($protocolReview * 0.15)
            + ($throughput * 0.15);

        $stars = max(1.0, min(5.0, round((1 + ($composite * 4)) * 2) / 2));
        $scorePercent = (int) round($composite * 100);

        if ($stars >= 4.5) {
            $label = 'Excelente';
        } elseif ($stars >= 3.5) {
            $label = 'Destacado';
        } elseif ($stars >= 2.5) {
            $label = 'En seguimiento';
        } else {
            $label = 'Oportunidad de mejora';
        }

        return [
            'stars' => $stars,
            'score_percent' => $scorePercent,
            'label' => $label,
            'summary' => $scorePercent . '/100 en consistencia operativa',
        ];
    }

    /**
     * @param array<string, mixed> $agenda
     * @param array<string, mixed> $surgeries
     * @param array<string, mixed> $rating
     * @return array<int, array{label:string,value:string}>
     */
    private function buildQuickStats(array $agenda, array $surgeries, array $rating): array
    {
        return [
            ['label' => 'Pacientes 30d', 'value' => number_format((int) ($agenda['unique_patients'] ?? 0))],
            ['label' => 'Cirugías 90d', 'value' => number_format((int) ($surgeries['total'] ?? 0))],
            ['label' => 'Score', 'value' => (string) (($rating['score_percent'] ?? 0) . '/100')],
        ];
    }

    /**
     * @param array<string, mixed> $doctor
     * @param array<string, mixed> $agenda
     * @param array<string, mixed> $surgeries
     * @param array<string, mixed> $requests
     * @param array<string, mixed> $exams
     * @param array<string, mixed> $rating
     * @return array<int, string>
     */
    private function buildSummaryParagraphs(
        array $doctor,
        array $agenda,
        array $surgeries,
        array $requests,
        array $exams,
        array $rating
    ): array {
        $displayName = (string) ($doctor['display_name'] ?? $doctor['name'] ?? 'El doctor');
        $agendaTotal = max(0, (int) ($agenda['total'] ?? 0));
        $agendaPatients = max(0, (int) ($agenda['unique_patients'] ?? 0));
        $agendaCompleted = max(0, (int) ($agenda['completed'] ?? 0));
        $agendaLost = max(0, (int) ($agenda['lost'] ?? 0));
        $surgeryTotal = max(0, (int) ($surgeries['total'] ?? 0));
        $surgeryReviewed = max(0, (int) ($surgeries['reviewed'] ?? 0));
        $requestTotal = max(0, (int) ($requests['total'] ?? 0));
        $examTotal = max(0, (int) ($exams['total'] ?? 0));

        return [
            sprintf(
                '%s registra %s citas en los últimos 30 días para %s pacientes distintos. La atención efectiva se ubica en %s%% y el ausentismo/cancelación en %s%%.',
                $displayName,
                number_format($agendaTotal),
                number_format($agendaPatients),
                $this->safePercentage($agendaCompleted, $agendaTotal),
                $agendaTotal > 0 ? $this->safePercentage($agendaLost, $agendaTotal) : 0
            ),
            sprintf(
                'En la ventana quirúrgica y diagnóstica reciente lideró %s cirugías, generó %s solicitudes y %s órdenes de exámenes. El score actual es %s con calificación %s.',
                number_format($surgeryTotal),
                number_format($requestTotal),
                number_format($examTotal),
                (string) ($rating['summary'] ?? '0/100'),
                (string) ($rating['label'] ?? 'Sin clasificar')
            ),
            sprintf(
                'De las cirugías analizadas, %s%% ya aparecen revisadas por protocolo.',
                $this->safePercentage($surgeryReviewed, $surgeryTotal)
            ),
        ];
    }

    /**
     * @param array<string, mixed> $agenda
     * @param array<string, mixed> $surgeries
     * @param array<string, mixed> $requests
     * @param array<string, mixed> $exams
     * @return array<int, string>
     */
    private function buildOperationalNotes(array $agenda, array $surgeries, array $requests, array $exams): array
    {
        $notes = [];
        $agendaTotal = max(0, (int) ($agenda['total'] ?? 0));
        $agendaLost = max(0, (int) ($agenda['lost'] ?? 0));
        $agendaCompleted = max(0, (int) ($agenda['completed'] ?? 0));
        $surgeryTotal = max(0, (int) ($surgeries['total'] ?? 0));
        $surgeryReviewed = max(0, (int) ($surgeries['reviewed'] ?? 0));
        $requestTotal = max(0, (int) ($requests['total'] ?? 0));
        $examTotal = max(0, (int) ($exams['total'] ?? 0));

        if ($agendaTotal === 0) {
            $notes[] = 'No registra agenda operativa en los últimos 30 días.';
        } else {
            $notes[] = 'Agenda efectiva del periodo: ' . $this->safePercentage($agendaCompleted, $agendaTotal) . '%.';
            if ($agendaTotal > 0 && $this->safePercentage($agendaLost, $agendaTotal) >= 15) {
                $notes[] = 'El ausentismo/cancelación supera el 15%; conviene revisar confirmaciones y reprogramaciones.';
            }
        }

        if ($surgeryTotal > 0) {
            $notes[] = 'Protocolos revisados: ' . $this->safePercentage($surgeryReviewed, $surgeryTotal) . '% de las cirugías recientes.';
        }

        if ($requestTotal > 0) {
            $notes[] = 'Solicitudes quirúrgicas recientes: ' . number_format($requestTotal) . '.';
        }

        if ($examTotal > 0) {
            $notes[] = 'Órdenes diagnósticas recientes: ' . number_format($examTotal) . '.';
        }

        return array_slice($notes, 0, 4);
    }

    /**
     * @param array<string, mixed> $agenda
     * @param array<string, mixed> $surgeries
     * @param array<string, mixed> $requests
     * @param array<string, mixed> $exams
     * @return array<int, array<string, string>>
     */
    private function buildDistributionRows(array $agenda, array $surgeries, array $requests, array $exams): array
    {
        return [
            [
                'label' => 'Agenda 30d',
                'value' => sprintf(
                    '%s citas · %s pacientes',
                    number_format((int) ($agenda['total'] ?? 0)),
                    number_format((int) ($agenda['unique_patients'] ?? 0))
                ),
            ],
            [
                'label' => 'Quirófano 90d',
                'value' => sprintf(
                    '%s cirugías · %s%% revisadas',
                    number_format((int) ($surgeries['total'] ?? 0)),
                    $this->safePercentage((int) ($surgeries['reviewed'] ?? 0), (int) ($surgeries['total'] ?? 0))
                ),
            ],
            [
                'label' => 'Solicitudes 90d',
                'value' => sprintf(
                    '%s solicitudes · %s pacientes',
                    number_format((int) ($requests['total'] ?? 0)),
                    number_format((int) ($requests['unique_patients'] ?? 0))
                ),
            ],
            [
                'label' => 'Exámenes 30d',
                'value' => sprintf(
                    '%s órdenes · %s pacientes',
                    number_format((int) ($exams['total'] ?? 0)),
                    number_format((int) ($exams['unique_patients'] ?? 0))
                ),
            ],
        ];
    }

    /**
     * @param array<int, array<string, string>> $recentSurgeries
     * @param array<int, array<string, string>> $recentRequests
     * @param array<int, array<string, string>> $recentExams
     * @return array<int, array<string, string>>
     */
    private function buildRecentActivityTimeline(array $recentSurgeries, array $recentRequests, array $recentExams): array
    {
        $items = [];

        foreach ($recentSurgeries as $row) {
            $items[] = [
                'ts' => (string) ($row['event_date'] ?? ''),
                'year' => $this->formatShortDateLabel((string) ($row['event_date'] ?? '')),
                'title' => 'Cirugía · ' . (string) ($row['label'] ?? 'Sin detalle'),
                'description' => trim((string) ($row['patient'] ?? '')) . ($row['state'] !== '' ? ' · ' . (string) $row['state'] : ''),
            ];
        }

        foreach ($recentRequests as $row) {
            $items[] = [
                'ts' => (string) ($row['event_date'] ?? ''),
                'year' => $this->formatShortDateLabel((string) ($row['event_date'] ?? '')),
                'title' => 'Solicitud · ' . (string) ($row['label'] ?? 'Sin detalle'),
                'description' => trim((string) ($row['patient'] ?? '')) . ($row['state'] !== '' ? ' · ' . (string) $row['state'] : ''),
            ];
        }

        foreach ($recentExams as $row) {
            $items[] = [
                'ts' => (string) ($row['event_date'] ?? ''),
                'year' => $this->formatShortDateLabel((string) ($row['event_date'] ?? '')),
                'title' => 'Examen · ' . (string) ($row['label'] ?? 'Sin detalle'),
                'description' => trim((string) ($row['patient'] ?? '')) . ($row['state'] !== '' ? ' · ' . (string) $row['state'] : ''),
            ];
        }

        usort($items, static function (array $a, array $b): int {
            return strcmp((string) ($b['ts'] ?? ''), (string) ($a['ts'] ?? ''));
        });

        return array_map(static function (array $item): array {
            unset($item['ts']);
            return $item;
        }, array_slice($items, 0, 8));
    }

    /**
     * @param array<int, array<string, mixed>> $mixRows
     * @param array<string, mixed> $doctor
     * @return array<int, string>
     */
    private function extractFocusAreas(array $mixRows, array $doctor): array
    {
        $labels = [];
        foreach ($mixRows as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            if ($label !== '') {
                $labels[] = $label;
            }
            if (count($labels) >= 6) {
                break;
            }
        }

        if ($labels === []) {
            $specialty = trim((string) ($doctor['especialidad'] ?? ''));
            if ($specialty !== '') {
                $labels[] = $specialty;
            }
        }

        return array_values(array_unique($labels));
    }

    /**
     * @param array<int, string> $values
     * @return array{0: string, 1: array<string, string>}
     */
    private function buildMatchClause(array $values, string $expression, bool $useLike, string $paramPrefix): array
    {
        $conditions = [];
        $params = [];

        foreach (array_values($values) as $index => $value) {
            $param = ':' . $paramPrefix . $index;
            $conditions[] = $useLike
                ? 'LOWER(TRIM(' . $expression . ')) LIKE ' . $param
                : 'LOWER(TRIM(' . $expression . ')) = ' . $param;

            $normalized = $this->normalizeLower($value);
            $params[$param] = $useLike ? '%' . $normalized . '%' : $normalized;
        }

        return [$conditions !== [] ? '(' . implode(' OR ', $conditions) . ')' : '', $params];
    }

    private function quoteStringList(array $values): string
    {
        return implode(', ', array_map(static function (string $value): string {
            return "'" . str_replace("'", "''", $value) . "'";
        }, $values));
    }

    /**
     * @return array<int, string>
     */
    private function doctorAgendaSuccessStatuses(): array
    {
        return ['LLEGADO', 'LLEGADA', 'ATENDIDO', 'ATENDIDA', 'FACTURADO', 'FACTURADA', 'EN CONSULTA'];
    }

    /**
     * @return array<int, string>
     */
    private function doctorAgendaWarningStatuses(): array
    {
        return ['REPROGRAMADO', 'REPROGRAMADA', 'REAGENDADO', 'REAGENDADA', 'EN ESPERA'];
    }

    /**
     * @return array<int, string>
     */
    private function doctorAgendaDangerStatuses(): array
    {
        return ['ANULADO', 'ANULADA', 'CANCELADO', 'CANCELADA', 'NO ASISTE', 'NO ASISTIO', 'NO SE PRESENTO', 'NO SE PRESENTÓ', 'NO-SE-PRESENTO'];
    }

    private function safePercentage(int $part, int $total): int
    {
        if ($total <= 0) {
            return 0;
        }

        return (int) round(($part * 100) / $total);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildTrend(int $current, int $previous): ?array
    {
        if ($current === 0 && $previous === 0) {
            return null;
        }

        if ($previous <= 0) {
            return $this->formatTrend($current > 0 ? 100 : 0);
        }

        $delta = (int) round((($current - $previous) * 100) / $previous);
        return $this->formatTrend($delta);
    }

    private function buildScheduleLabel(string $weekdaysCsv, string $firstHour, string $lastHour): string
    {
        $days = $this->formatWeekdaysLabel($weekdaysCsv);
        $hours = $this->formatHourRange($firstHour, $lastHour);

        if ($days === '' && $hours === '') {
            return 'Sin agenda futura';
        }

        if ($days === '') {
            return $hours;
        }

        if ($hours === '') {
            return $days;
        }

        return $days . ' · ' . $hours;
    }

    private function formatWeekdaysLabel(string $weekdaysCsv): string
    {
        $map = [
            '1' => 'Dom',
            '2' => 'Lun',
            '3' => 'Mar',
            '4' => 'Mié',
            '5' => 'Jue',
            '6' => 'Vie',
            '7' => 'Sáb',
        ];
        $labels = [];
        foreach (array_filter(array_map('trim', explode(',', $weekdaysCsv))) as $day) {
            if (isset($map[$day])) {
                $labels[] = $map[$day];
            }
        }

        return implode(', ', array_values(array_unique($labels)));
    }

    private function formatHourRange(string $firstHour, string $lastHour): string
    {
        $start = $this->formatHourLabel($firstHour);
        $end = $this->formatHourLabel($lastHour);

        if ($start === '--:--' && $end === '--:--') {
            return '';
        }

        return trim($start . ' - ' . $end, ' -');
    }

    private function formatRecencyLabel(string $date): string
    {
        if ($date === '') {
            return 'Sin registros recientes';
        }

        return $this->formatShortDateLabel($date);
    }

    private function formatShortDateLabel(string $date): string
    {
        if ($date === '') {
            return 'Sin fecha';
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return $date;
        }

        return date('d/m/Y', $timestamp);
    }

    private function tableExists(string $table): bool
    {
        static $cache = [];
        if (isset($cache[$table])) {
            return $cache[$table];
        }

        try {
            $stmt = $this->pdo->prepare(
                'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table LIMIT 1'
            );
            $stmt->execute([':table' => $table]);
            $cache[$table] = (bool) $stmt->fetchColumn();
        } catch (PDOException) {
            $cache[$table] = false;
        }

        return $cache[$table];
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
