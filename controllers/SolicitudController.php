<?php

namespace Controllers;

use Models\SolicitudModel;
use Modules\Pacientes\Services\PacienteService;

class SolicitudController
{
    protected SolicitudModel $solicitudModel;
    protected PacienteService $pacienteService;


    public function __construct($pdo)
    {
        $this->solicitudModel = new SolicitudModel($pdo);
        $this->pacienteService = new PacienteService($pdo);
    }

    public function getSolicitudesConDetalles(array $filtros = []): array
    {
        return $this->solicitudModel->fetchSolicitudesConDetallesFiltrado($filtros);
    }

    public function obtenerDatosParaVista($hc, $form_id)
    {
        $data = $this->solicitudModel->obtenerDerivacionPorFormId($form_id);
        $solicitud = $this->solicitudModel->obtenerDatosYCirujanoSolicitud($form_id, $hc);
        $paciente = $this->pacienteService->getPatientDetails($hc);
        $diagnostico = $this->solicitudModel->obtenerDxDeSolicitud($form_id);
        $consulta = $this->solicitudModel->obtenerConsultaDeSolicitud($form_id);
        return [
            'derivacion' => $data,
            'solicitud' => $solicitud,
            'paciente' => $paciente,
            'diagnostico' => $diagnostico,
            'consulta' => $consulta,
        ];
    }

    public function actualizarEstado(int $id, string $estado): void
    {
        $this->solicitudModel->actualizarEstado($id, $estado);
    }

    public function actualizarSolicitudParcial(int $id, array $campos): array
    {
        return $this->solicitudModel->actualizarSolicitudParcial($id, $campos);
    }

    public function marcarChecklistAptoOftalmologo(int $id): bool
    {
        return $this->solicitudModel->marcarChecklistAptoOftalmologo($id);
    }

    public function obtenerEstadosPorHc(string $hcNumber): array
    {
        $solicitudes = $this->solicitudModel->obtenerEstadosPorHc($hcNumber);

        return [
            'success'      => true,
            'hcNumber'     => $hcNumber,
            'total'        => count($solicitudes),
            'solicitudes'  => $solicitudes,
        ];
    }
}
