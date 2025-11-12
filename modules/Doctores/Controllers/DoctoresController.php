<?php

namespace Modules\Doctores\Controllers;

use Core\BaseController;
use Modules\Doctores\Models\DoctorModel;
use PDO;

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

        $insights = $this->buildDoctorInsights($doctor);

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
    private function buildDoctorInsights(array $doctor): array
    {
        $baseSeed = (string)($doctor['id'] ?? $doctor['name'] ?? '0');

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
        ];
    }

    /**
     * @param array<string, mixed> $doctor
     * @return array<int, array<string, string>>
     */
    private function buildTodayPatients(array $doctor, string $seed): array
    {
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
