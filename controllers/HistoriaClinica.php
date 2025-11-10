<?php
namespace Controllers;

use Modules\Pacientes\Services\PacienteService;
use PDO;

class HistoriaClinica
{
    private PacienteService $pacienteService;

    public function __construct(PDO $pdo, ?PacienteService $pacienteService = null)
    {
        $this->pacienteService = $pacienteService ?? new PacienteService($pdo);
    }

    public function obtenerResumenPaciente(string $hcNumber): array
    {
        return $this->pacienteService->obtenerContextoPaciente($hcNumber);
    }

    public function obtenerEventosPaciente(string $hcNumber): array
    {
        return $this->pacienteService->getEventosTimeline($hcNumber);
    }

    public function obtenerDocumentosPaciente(string $hcNumber): array
    {
        return $this->pacienteService->getDocumentosDescargables($hcNumber);
    }
}
