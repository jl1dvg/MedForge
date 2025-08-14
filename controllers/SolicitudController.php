<?php

namespace Controllers;

use Models\SolicitudModel;
use Controllers\PacienteController;

class SolicitudController
{
    protected $pdo;
    protected $solicitudModel;
    protected $pacienteController;


    public function __construct($pdo)
    {
        $this->solicitudModel = new SolicitudModel($pdo);
        $this->pacienteController = new PacienteController($pdo);
    }

    public function getSolicitudesConDetalles(): array
    {
        return $this->solicitudModel->fetchSolicitudesConDetalles();
    }

    public function obtenerDatosParaVista($hc, $form_id)
    {
        $data = $this->solicitudModel->obtenerDerivacionPorFormId($form_id);
        $solicitud = $this->solicitudModel->obtenerDatosYCirujanoSolicitud($form_id, $hc);
        $paciente = $this->pacienteController->getPatientDetails($hc);
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
}