<?php

namespace Modules\Consulta\Services;

use Modules\Consulta\Models\ConsultaModel;
use Modules\Pacientes\Services\PacienteService;
use PDO;

class ConsultaReportService
{
    private ConsultaModel $consultaModel;
    private PacienteService $pacienteService;

    public function __construct(PDO $pdo)
    {
        $this->consultaModel = new ConsultaModel($pdo);
        $this->pacienteService = new PacienteService($pdo);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildConsultaReportData(string $hc, string $form_id): array
    {
        $paciente = $this->pacienteService->getPatientDetails($hc);
        $consulta = $this->consultaModel->obtenerConsultaConProcedimiento($form_id, $hc);
        $diagnostico = $this->consultaModel->obtenerDxDeConsulta($form_id);
        $dxDerivacion = $this->consultaModel->obtenerDxDerivacion($form_id);

        return [
            'paciente' => $paciente,
            'diagnostico' => $diagnostico,
            'consulta' => $consulta,
            'dx_derivacion' => $dxDerivacion,
        ];
    }
}
