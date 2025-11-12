<?php

namespace Models;

use PDO;

class EstadisticaFlujoModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function obtenerEstadisticas($filtros)
    {
        $pacientes = $this->obtenerFormIdsFiltrados($filtros);
        $resultados = [];

        foreach ($pacientes as $p) {
            $tiempos = $this->calcularDuracionesPorFormId($p['form_id']);

            $resultados[] = [
                'form_id' => $p['form_id'],
                'hc_number' => $p['hc_number'],
                'doctor' => $p['doctor'],
                'servicio' => $p['sede_departamento'],
                'fecha' => $p['fecha'],
                'estado_agenda' => $p['estado_agenda'],
                'tiempos' => $tiempos,
            ];
        }

        return $resultados;
    }

    private function obtenerFormIdsFiltrados($filtros)
    {
        $sql = "SELECT form_id, hc_number, doctor, sede_departamento, fecha, estado_agenda 
                FROM procedimiento_proyectado 
                WHERE 1=1";
        $params = [];

        if (!empty($filtros['fecha_inicio'])) {
            $sql .= " AND fecha >= ?";
            $params[] = $filtros['fecha_inicio'];
        }
        if (!empty($filtros['fecha_fin'])) {
            $sql .= " AND fecha <= ?";
            $params[] = $filtros['fecha_fin'];
        }
        if (!empty($filtros['medico'])) {
            $sql .= " AND doctor = ?";
            $params[] = $filtros['medico'];
        }
        if (!empty($filtros['servicio'])) {
            $sql .= " AND sede_departamento = ?";
            $params[] = $filtros['servicio'];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function calcularDuracionesPorFormId($form_id)
    {
        // Obtener la hora programada de la cita
        $sqlCita = "SELECT fecha, hora FROM procedimiento_proyectado WHERE form_id = ?";
        $stmtCita = $this->db->prepare($sqlCita);
        $stmtCita->execute([$form_id]);
        $cita = $stmtCita->fetch(PDO::FETCH_ASSOC);

        $citaProgramada = null;
        if ($cita && $cita['fecha'] && $cita['hora']) {
            $citaProgramada = $cita['fecha'] . ' ' . $cita['hora'];
        }

        // Obtener estados y sus marcas de tiempo
        $sql = "SELECT estado, fecha_hora_cambio 
                FROM procedimiento_proyectado_estado 
                WHERE form_id = ? 
                ORDER BY fecha_hora_cambio ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$form_id]);
        $estados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $marcas = [];
        foreach ($estados as $e) {
            $marcas[$e['estado']] = $e['fecha_hora_cambio'];
        }

        return [
            'espera' => $this->minutosEntre($citaProgramada, $marcas['LLEGADO'] ?? null),
            'sala' => $this->minutosEntre($marcas['LLEGADO'] ?? null, $marcas['OPTOMETRIA'] ?? null),
            'optometria' => $this->minutosEntre(
                $marcas['OPTOMETRIA'] ?? null,
                $marcas['OPTOMETRIA_TERMINADO'] ?? $marcas['DILATAR'] ?? null
            ),
            'total' => $this->minutosEntre($marcas['LLEGADO'] ?? null, $marcas['OPTOMETRIA_TERMINADO'] ?? $marcas['DILATAR'] ?? null),
        ];
    }

    private function minutosEntre($inicio, $fin)
    {
        if (!$inicio || !$fin) return null;
        return round((strtotime($fin) - strtotime($inicio)) / 60, 2);
    }

    public function getEstadisticaFlujo($fecha_inicio, $fecha_fin, $doctor = null, $sede = null, $estado_agenda = null)
    {
        $sql = "SELECT * FROM procedimiento_proyectado AS pp
            LEFT JOIN procedimiento_proyectado_estado AS es ON pp.form_id = es.form_id
            WHERE pp.fecha BETWEEN ? AND ?";

        $params = [$fecha_inicio, $fecha_fin];

        if ($doctor) {
            $sql .= " AND pp.doctor = ?";
            $params[] = $doctor;
        }

        if ($sede) {
            $sql .= " AND pp.id_sede = ?";
            $params[] = $sede;
        }

        if ($estado_agenda) {
            $sql .= " AND pp.estado_agenda = ?";
            $params[] = $estado_agenda;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }
}
