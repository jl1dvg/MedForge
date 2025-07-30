<?php

namespace Models;

use PDO;

class TrazabilidadModel
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function getEventosTimeline($hc_number)
    {
        $stmt = $this->db->prepare("
            SELECT pp.procedimiento_proyectado, pp.form_id, pp.hc_number, 
                   COALESCE(cd.fecha, pr.fecha_inicio) AS fecha, 
                   cd.motivo_consulta AS motivo_consulta,
                   COALESCE(cd.examen_fisico, pr.membrete) AS contenido,
                   cd.diagnosticos AS diagnosticos, cd.examenes AS examenes, cd.plan AS plan
            FROM procedimiento_proyectado pp
            LEFT JOIN consulta_data cd ON pp.hc_number = cd.hc_number AND pp.form_id = cd.form_id
            LEFT JOIN protocolo_data pr ON pp.hc_number = pr.hc_number AND pp.form_id = pr.form_id
            WHERE pp.hc_number = ? AND pp.procedimiento_proyectado NOT LIKE '%optometría%'
            ORDER BY fecha ASC
        ");
        $stmt->execute([$hc_number]);

        $eventos = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($row['fecha']) && strtotime($row['fecha'])) {
                $texto = strtoupper(trim($row['procedimiento_proyectado'] . ' ' . $row['contenido']));

                if (strpos($texto, 'CONTROL ANESTESICO') !== false) {
                    $tipo = 'control_anestesico';
                } elseif (strpos($texto, 'BIOMETRIA') !== false) {
                    $tipo = 'biometria';
                } elseif (strpos($texto, 'CONSULTA OFTALMOLOGICA NUEVO PACIENTE') !== false) {
                    $tipo = 'solicitud_biometria';
                } elseif (!empty($row['fecha']) && !empty($row['contenido']) && (strpos($row['contenido'], 'quirófano') !== false || strpos($row['contenido'], 'protocolo') !== false)) {
                    $tipo = 'cirugia_realizada';
                } else {
                    $tipo = $this->clasificarEvento($row['contenido']);
                }
                $eventos[] = [
                    'fecha' => $row['fecha'],
                    'contenido' => $row['contenido'],
                    'tipo' => $tipo,
                    'form_id' => $row['form_id'],
                    'procedimiento' => $row['procedimiento_proyectado']
                ];
            }
        }
        return $eventos;
    }

    public function getTodosLosProcedimientosProyectados($hc_number)
    {
        $stmt = $this->db->prepare("
            SELECT DISTINCT pp.form_id, 
                   pp.id, pp.procedimiento_proyectado, pp.doctor, pp.estado_agenda, pp.afiliacion, pp.fecha, 
                   cd.fecha AS fecha_consulta, cd.motivo_consulta, cd.enfermedad_actual, cd.examen_fisico, cd.plan, cd.diagnosticos, cd.examenes,
                   pd.procedimiento_id, pd.membrete AS cirugia, pd.lateralidad, pd.fecha_inicio AS fecha_cirugia,
                   sp.tipo AS tipo_solicitud, 
                   NULLIF(NULLIF(sp.procedimiento, ''), 'SELECCIONE') AS solicitado, 
                   sp.ojo AS ojo_solicitado,
                   CASE
    WHEN CONCAT_WS(' ', pp.procedimiento_proyectado, sp.procedimiento, pd.membrete) LIKE '%CONSULTA OFTALMOLOGICA NUEVO PACIENTE%' THEN 'solicitud_biometria'
    WHEN CONCAT_WS(' ', pp.procedimiento_proyectado, sp.procedimiento, pd.membrete) LIKE '%BIOMETRIA%' THEN 'biometria'
    WHEN CONCAT_WS(' ', pp.procedimiento_proyectado, sp.procedimiento, pd.membrete) LIKE '%CONTROL ANESTESICO%' THEN 'control_anestesico'
    WHEN pd.fecha_inicio IS NOT NULL THEN 'cirugia_realizada'
    WHEN NULLIF(NULLIF(sp.procedimiento, ''), 'SELECCIONE') IS NOT NULL THEN 'solicitud_procedimiento'
    ELSE 'evento_pendiente'
END AS tipo_evento
            FROM procedimiento_proyectado AS pp 
            LEFT JOIN consulta_data cd ON cd.hc_number = pp.hc_number AND cd.form_id = pp.form_id 
            LEFT JOIN protocolo_data pd ON pd.hc_number = pp.hc_number AND pd.form_id = pp.form_id 
            LEFT JOIN solicitud_procedimiento sp ON sp.hc_number = pp.hc_number AND sp.form_id = pp.form_id 
            WHERE pp.hc_number = ? 
            ORDER BY pp.fecha ASC
        ");
        $stmt->execute([$hc_number]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function clasificarEvento($contenido)
    {
        $contenido = strtolower($contenido);
        if (str_contains($contenido, 'plan quirúrgico') || str_contains($contenido, 'protocolo') || str_contains($contenido, 'quirófano')) {
            return 'plan_quirurgico';
        } elseif (str_contains($contenido, 'dx') || str_contains($contenido, 'diagnóstico')) {
            return 'consulta_diagnostica';
        } elseif (str_contains($contenido, 'examen') || str_contains($contenido, 'tomografía') || str_contains($contenido, 'oct')) {
            return 'examenes';
        } elseif (str_contains($contenido, 'cirugía') || str_contains($contenido, 'operación') || str_contains($contenido, 'postoperatorio')) {
            return 'cirugia';
        } else {
            return 'otro';
        }
    }
}