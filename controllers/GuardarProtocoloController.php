<?php

namespace Controllers;

use PDO;

class GuardarProtocoloController
{
    private $db;

    public function __construct($pdo)
    {
        $this->db = $pdo;
    }

    public function guardar(array $data): bool
    {
        $sql = "UPDATE protocolo_data SET
                    membrete = :membrete,
                    dieresis = :dieresis,
                    exposicion = :exposicion,
                    hallazgo = :hallazgo,
                    operatorio = :operatorio,
                    complicaciones_operatorio = :complicaciones_operatorio,
                    datos_cirugia = :datos_cirugia,
                    procedimientos = :procedimientos,
                    diagnosticos = :diagnosticos,
                    lateralidad = :lateralidad,
                    tipo_anestesia = :tipo_anestesia,
                    hora_inicio = :hora_inicio,
                    hora_fin = :hora_fin,
                    fecha_inicio = :fecha_inicio,
                    fecha_fin = :fecha_fin,
                    cirujano_1 = :cirujano_1,
                    cirujano_2 = :cirujano_2,
                    primer_ayudante = :primer_ayudante,
                    segundo_ayudante = :segundo_ayudante,
                    tercer_ayudante = :tercer_ayudante,
                    ayudante_anestesia = :ayudante_anestesia,
                    anestesiologo = :anestesiologo,
                    instrumentista = :instrumentista,
                    circulante = :circulante,
                    insumos = :insumos,
                    medicamentos = :medicamentos,
                    status = :status
                WHERE form_id = :form_id AND hc_number = :hc_number";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'membrete' => $data['membrete'] ?? '',
            'dieresis' => $data['dieresis'] ?? '',
            'exposicion' => $data['exposicion'] ?? '',
            'hallazgo' => $data['hallazgo'] ?? '',
            'operatorio' => $data['operatorio'] ?? '',
            'complicaciones_operatorio' => $data['complicaciones_operatorio'] ?? '',
            'datos_cirugia' => $data['datos_cirugia'] ?? '',
            'procedimientos' => json_encode($data['procedimientos'] ?? '[]'),
            'diagnosticos' => json_encode($data['diagnosticos'] ?? '[]'),
            'lateralidad' => $data['lateralidad'] ?? '',
            'tipo_anestesia' => $data['tipo_anestesia'] ?? '',
            'hora_inicio' => $data['hora_inicio'] ?? '',
            'hora_fin' => $data['hora_fin'] ?? '',
            'fecha_inicio' => $data['fecha_inicio'] ?? '',
            'fecha_fin' => $data['fecha_fin'] ?? '',
            'cirujano_1' => $data['cirujano_1'] ?? '',
            'cirujano_2' => $data['cirujano_2'] ?? '',
            'primer_ayudante' => $data['primer_ayudante'] ?? '',
            'segundo_ayudante' => $data['segundo_ayudante'] ?? '',
            'tercer_ayudante' => $data['tercer_ayudante'] ?? '',
            'ayudante_anestesia' => $data['ayudanteAnestesia'] ?? '',
            'anestesiologo' => $data['anestesiologo'] ?? '',
            'instrumentista' => $data['instrumentista'] ?? '',
            'circulante' => $data['circulante'] ?? '',
            'insumos' => is_string($data['insumos']) ? $data['insumos'] : json_encode($data['insumos'] ?? []),
            'medicamentos' => is_string($data['medicamentos']) ? $data['medicamentos'] : json_encode($data['medicamentos'] ?? []),
            'status' => $data['status'] ?? 0,
            'form_id' => $data['form_id'],
            'hc_number' => $data['hc_number']
        ]);
    }
}