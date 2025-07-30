<?php

namespace Controllers;

use Models\TrazabilidadModel;

class TrazabilidadController
{
    protected $pdo;
    protected $trazabilidadModel;

    public function __construct($db)
    {
        $this->trazabilidadModel = new TrazabilidadModel($db);
    }

    public function mostrarEventosPaciente($hc_number)
    {
        $eventos = $this->trazabilidadModel->getEventosTimeline($hc_number);

        echo "<pre>";
        print_r($eventos);
        echo "</pre>";
    }

    public function mostrarTodosLosProcedimientos($hc_number)
    {
        return $this->trazabilidadModel->getTodosLosProcedimientosProyectados($hc_number);
    }

    public function renderEvento(array $evento): string
    {
        $tipo = $evento['tipo_evento'] ?? $evento['tipo'] ?? 'pendiente';
        $formulario = isset($evento['form_id']) ? "Formulario {$evento['form_id']}" : "Sin formulario";
        $fecha = $evento['fecha'] ?? $evento['fecha_consulta'] ?? $evento['fecha_cirugia'] ?? 'Fecha no registrada';
        $proc = !empty($evento['procedimiento_proyectado']) ? $evento['procedimiento_proyectado'] : 'Procedimiento no especificado';
        switch ($tipo) {
            case 'solicitud_biometria':
                return "📝 Solicitud de biometría: {$proc} — {$formulario}, {$fecha}";
            case 'biometria':
            case 'biometria_realizada':
                return "🔬 Biometría realizada: {$proc} — {$formulario}, {$fecha}";
            case 'control_anestesico':
                return "📅 Control anestésico: {$proc} — {$formulario}, {$fecha}";
            case 'cirugia_realizada':
                return "🏥 Cirugía realizada: {$proc} — {$formulario}, {$fecha}";
            case 'solicitud_procedimiento':
            case 'solicitud_cirugia':
                return "📝 Solicitud de cirugía registrada: {$proc} — {$formulario}, {$fecha}";
            case 'postoperatorio':
                return "📄 Consulta postoperatoria: {$proc} — {$formulario}, {$fecha}";
            case 'pendiente':
            default:
                return "⚠️ Evento pendiente: {$proc} — {$formulario}, {$fecha}";
        }
    }

    public function obtenerProcesos($hc_number)
    {
        $datos = $this->trazabilidadModel->getTodosLosProcedimientosProyectados($hc_number);
        return \Helpers\TrazabilidadHelpers::agruparProcesosPorFormulario($datos);
    }
}