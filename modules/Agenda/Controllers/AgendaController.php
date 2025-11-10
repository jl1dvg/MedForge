<?php

namespace Modules\Agenda\Controllers;

use Core\BaseController;
use Modules\Agenda\Models\AgendaModel;
use Modules\IdentityVerification\Models\VerificationModel;
use Modules\IdentityVerification\Services\VerificationPolicyService;
use Modules\Pacientes\Services\PacienteService;
use PDO;

class AgendaController extends BaseController
{
    private AgendaModel $agenda;
    private PacienteService $pacienteService;
    private VerificationModel $verificationModel;
    private VerificationPolicyService $verificationPolicy;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->agenda = new AgendaModel($pdo);
        $this->pacienteService = new PacienteService($pdo);
        $this->verificationModel = new VerificationModel($pdo);
        $this->verificationPolicy = new VerificationPolicyService($pdo);
    }

    public function index(): void
    {
        $this->requireAuth();

        $filters = $this->resolveFilters();
        $agenda = $this->agenda->listarAgenda($filters);

        $this->render(
            __DIR__ . '/../views/index.php',
            [
                'pageTitle' => 'Agenda de procedimientos',
                'filters' => $filters,
                'agenda' => $agenda,
                'estadosDisponibles' => $this->agenda->listarEstadosAgenda(),
                'doctoresDisponibles' => $this->agenda->listarDoctores(),
                'sedesDisponibles' => $this->agenda->listarSedes(),
            ]
        );
    }

    public function mostrarVisita(int $visitaId): void
    {
        $this->requireAuth();

        if ($visitaId <= 0) {
            $this->render(
                __DIR__ . '/../views/visita_no_encontrada.php',
                [
                    'pageTitle' => 'Encuentro no encontrado',
                ]
            );
            return;
        }

        $detalle = $this->agenda->obtenerVisita($visitaId);

        if ($detalle === null) {
            $this->render(
                __DIR__ . '/../views/visita_no_encontrada.php',
                [
                    'pageTitle' => 'Encuentro no encontrado',
                ]
            );
            return;
        }

        $identityVerification = [
            'summary' => null,
            'requires_checkin' => true,
            'validity_days' => $this->verificationPolicy->getValidityDays(),
        ];

        if (!empty($detalle['hc_number'])) {
            $hcNumber = (string) $detalle['hc_number'];
            $detalle['estado_cobertura'] = $this->pacienteService->verificarCoberturaPaciente($hcNumber);
            $detalle['paciente_contexto'] = $this->pacienteService->obtenerContextoPaciente($hcNumber);

            $summary = $this->verificationModel->getStatusSummaryByPatient($hcNumber);
            $identityVerification['summary'] = $summary;
            $identityVerification['requires_checkin'] = $summary === null || ($summary['status'] ?? '') !== 'verified';
        }

        $this->render(
            __DIR__ . '/../views/visita.php',
            [
                'pageTitle' => 'Encuentro #' . $visitaId,
                'visita' => $detalle,
                'identityVerification' => $identityVerification,
            ]
        );
    }

    /**
     * @return array{
     *     fecha_inicio: string,
     *     fecha_fin: string,
     *     doctor: string|null,
     *     estado: string|null,
     *     sede: string|null,
     *     solo_con_visita: bool
     * }
     */
    private function resolveFilters(): array
    {
        $hoy = new \DateTimeImmutable('today');
        $fechaInicioRaw = $_GET['fecha_inicio'] ?? $hoy->format('Y-m-d');
        $fechaFinRaw = $_GET['fecha_fin'] ?? $fechaInicioRaw;

        $fechaInicio = $this->sanitizeDate($fechaInicioRaw) ?? $hoy->format('Y-m-d');
        $fechaFin = $this->sanitizeDate($fechaFinRaw) ?? $fechaInicio;

        if ($fechaFin < $fechaInicio) {
            [$fechaInicio, $fechaFin] = [$fechaFin, $fechaInicio];
        }

        $doctor = $this->sanitizeNullableString($_GET['doctor'] ?? null);
        $estado = $this->sanitizeNullableString($_GET['estado'] ?? null);
        $sede = $this->sanitizeNullableString($_GET['sede'] ?? null);

        return [
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'doctor' => $doctor,
            'estado' => $estado,
            'sede' => $sede,
            'solo_con_visita' => isset($_GET['solo_con_visita']) && $_GET['solo_con_visita'] !== '0',
        ];
    }

    private function sanitizeDate(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if ($date === false) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    private function sanitizeNullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
