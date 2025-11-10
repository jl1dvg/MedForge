<?php

namespace Modules\Pacientes\Controllers;

use Modules\Pacientes\Services\PacienteService;
use PDO;

class Pacientes
{
    private PacienteService $service;

    public function __construct(PDO $pdo, ?PacienteService $service = null)
    {
        $this->service = $service ?? new PacienteService($pdo);
    }

    public function obtenerPacientesConUltimaConsulta(): array
    {
        return $this->service->obtenerPacientesConUltimaConsulta();
    }

    public function getDiagnosticosPorPaciente(string $hcNumber): array
    {
        return $this->service->getDiagnosticosPorPaciente($hcNumber);
    }

    public function getDoctoresAsignados(string $hcNumber): array
    {
        return $this->service->getDoctoresAsignados($hcNumber);
    }

    public function getSolicitudesPorPaciente(string $hcNumber, int $limit = 50): array
    {
        return $this->service->getSolicitudesPorPaciente($hcNumber, $limit);
    }

    public function getDetalleSolicitud(string $hcNumber, string $formId): array
    {
        return $this->service->getDetalleSolicitud($hcNumber, $formId);
    }

    public function getDocumentosDescargables(string $hcNumber): array
    {
        return $this->service->getDocumentosDescargables($hcNumber);
    }

    public function getPatientDetails(string $hcNumber): array
    {
        return $this->service->getPatientDetails($hcNumber);
    }

    public function getEventosTimeline(string $hcNumber): array
    {
        return $this->service->getEventosTimeline($hcNumber);
    }

    public function getEstadisticasProcedimientos(string $hcNumber): array
    {
        return $this->service->getEstadisticasProcedimientos($hcNumber);
    }

    public function calcularEdad(?string $fechaNacimiento, ?string $fechaActual = null): ?int
    {
        return $this->service->calcularEdad($fechaNacimiento, $fechaActual);
    }

    public function verificarCoberturaPaciente(string $hcNumber): string
    {
        return $this->service->verificarCoberturaPaciente($hcNumber);
    }

    public function getPrefacturasPorPaciente(string $hcNumber, int $limit = 50): array
    {
        return $this->service->getPrefacturasPorPaciente($hcNumber, $limit);
    }

    public function obtenerStaffPorEspecialidad(): array
    {
        return $this->service->obtenerStaffPorEspecialidad();
    }

    public function actualizarPaciente(
        string $hcNumber,
        string $fname,
        string $mname,
        string $lname,
        string $lname2,
        string $afiliacion,
        string $fechaNacimiento,
        string $sexo,
        string $celular
    ): void {
        $this->service->actualizarPaciente(
            $hcNumber,
            $fname,
            $mname,
            $lname,
            $lname2,
            $afiliacion,
            $fechaNacimiento,
            $sexo,
            $celular
        );
    }

    public function getAfiliacionesDisponibles(): array
    {
        return $this->service->getAfiliacionesDisponibles();
    }

    public function getAtencionesParticularesPorSemana(string $fechaInicio, string $fechaFin): array
    {
        return $this->service->getAtencionesParticularesPorSemana($fechaInicio, $fechaFin);
    }

    public function obtenerPacientesPaginados(
        int $start,
        int $length,
        string $search = '',
        string $orderColumn = 'hc_number',
        string $orderDir = 'ASC'
    ): array {
        return $this->service->obtenerPacientesPaginados($start, $length, $search, $orderColumn, $orderDir);
    }
}
