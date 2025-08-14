<?php

namespace Controllers;

use Models\IplPlanificadorModel;
use PDO;

class IplPlanificadorController
{
    private PDO $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    public function obtenerCirugias(): array
    {
        return IplPlanificadorModel::obtenerTodas($this->db);
    }

    public function verificarDerivacion(string $form_id, string $hc_number, array $scraperResponse): void
    {
        IplPlanificadorModel::verificarOInsertarDerivacion($this->db, $form_id, $hc_number, $scraperResponse);
    }

    public function existeDerivacionEnBD($form_id, $hc_number): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM derivaciones_form_id WHERE form_id = ? AND hc_number = ?");
        $stmt->execute([$form_id, $hc_number]);
        return $stmt->fetchColumn() > 0;
    }

    public function existePlanificacionYDerivacion($form_id, $hc_number): array
    {
        // Verificar si existe en derivaciones_form_id
        $stmtDerivacion = $this->db->prepare("SELECT COUNT(*) FROM derivaciones_form_id WHERE form_id = ? AND hc_number = ?");
        $stmtDerivacion->execute([$form_id, $hc_number]);
        $existeDerivacion = $stmtDerivacion->fetchColumn() > 0;

        // Verificar si existe en ipl_planificador
        $stmtPlanificacion = $this->db->prepare("SELECT COUNT(*) FROM ipl_planificador WHERE form_id_origen = ? AND hc_number = ?");
        $stmtPlanificacion->execute([$form_id, $hc_number]);
        $existePlanificacion = $stmtPlanificacion->fetchColumn() > 0;

        return [
            'derivacion' => $existeDerivacion,
            'planificacion' => $existePlanificacion
        ];
    }

    public function obtenerPlanificacionPendiente($hc_number, $fecha_ideal): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM ipl_planificador WHERE hc_number = ? AND fecha_ficticia = ?");
        $stmt->execute([$hc_number, $fecha_ideal]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        return $resultado === false ? null : $resultado;
    }

    public function guardarDerivacionManual($form_id, $hc_number, $cod_derivacion, $fecha_registro, $fecha_vigencia, $diagnostico)
    {
        $stmt = $this->db->prepare("INSERT INTO derivaciones_form_id (form_id, hc_number, cod_derivacion, fecha_registro, fecha_vigencia, diagnostico) VALUES (?, ?, ?, ?, ?, ?)");
        try {
            $stmt->execute([$form_id, $hc_number, $cod_derivacion, $fecha_registro, $fecha_vigencia, $diagnostico]);
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function guardarPlanificacionManual($hc_number, $form_id_origen, $nro_sesion, $fecha_ficticia, $form_id_real, $estado, $derivacion_id, $doctor, $procedimiento, $diagnostico)
    {
        $form_id_origen_valido = $form_id_origen;

        $form_id_real_valido = null;
        $stmtCheckReal = $this->db->prepare("SELECT COUNT(*) FROM protocolo_data WHERE form_id = ?");
        $stmtCheckReal->execute([$form_id_real]);
        $form_id_real_valido = $stmtCheckReal->fetchColumn() > 0 ? $form_id_real : null;

        $stmt = $this->db->prepare("
            INSERT INTO ipl_planificador (
                hc_number, form_id_origen, nro_sesion, fecha_ficticia,
                form_id_real, estado, derivacion_id, doctor, procedimiento, diagnostico
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        try {
            $stmt->execute([
                $hc_number, $form_id_origen_valido, $nro_sesion, $fecha_ficticia,
                $form_id_real_valido, $estado, $derivacion_id, $doctor, $procedimiento, $diagnostico
            ]);
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function generarFechasIdeales(\DateTime $fechaInicio, \DateTime $fechaVigencia): array
    {
        $fechas_ideales = [];
        $contador = 1;

        $inicio = clone $fechaInicio;
        $fin = clone $fechaVigencia;

        for ($i = 0; $contador <= 4; $i++) {
            $fechaTentativa = (clone $inicio)->modify("+{$i} months");

            if ($i === 0) {
                // Para el primer mes, usar el mismo día +2 o el último día si se pasa
                $diaInicio = (int)$inicio->format('d');
                $ultimoDiaMes = (int)$fechaTentativa->format('t');
                $diaTentativo = min($diaInicio + 2, $ultimoDiaMes);

                $fechaTentativa->setDate(
                    (int)$fechaTentativa->format('Y'),
                    (int)$fechaTentativa->format('m'),
                    $diaTentativo
                );
            } else {
                // Se selecciona un día aleatorio entre el 15 y el 23 de cada mes para simular variabilidad de programación
                // sin salirse del rango del mes y evitando fines de semana.
                $ultimoDiaDelMes = (int)$fechaTentativa->format('t');
                $anioMesTentativo = $fechaTentativa->format('Y-m');
                $ultimoDiaPermitido = (int)(($fin->format('Y-m') === $anioMesTentativo) ? $fin->format('d') : $ultimoDiaDelMes);

                // Si el último día permitido del mes es menor a 15, usar ese día directamente
                if ($ultimoDiaPermitido < 15) {
                    $diaFinal = $ultimoDiaPermitido;
                } else {
                    $diaAleatorio = rand(15, min(23, $ultimoDiaPermitido));
                    $diaFinal = $diaAleatorio;
                }
            }

            // Ajustar si cae en sábado (6) o domingo (7)
            while ((int)$fechaTentativa->format('N') >= 6) {
                $fechaTentativa->modify('+1 day');
            }

            // Validar rango
            if ($fechaTentativa >= $inicio && $fechaTentativa <= $fin) {
                $fechas_ideales[] = [
                    'contador' => $contador++,
                    'fecha' => $fechaTentativa->format('Y-m-d'),
                ];
            }

            // Si la fecha tentativa ya supera la vigencia, cortamos el bucle
            if ($fechaTentativa > $fin) {
                break;
            }
        }

        return $fechas_ideales;
    }

    public function asignarFormIdOrigen(int $id, string $form_id): array
    {
        $stmtCheck = $this->db->prepare("SELECT COUNT(*) FROM protocolo_data WHERE form_id = ?");
        $stmtCheck->execute([$form_id]);

        if ($stmtCheck->fetchColumn() === 0) {
            return ['success' => false, 'message' => 'El form_id no existe en protocolo_data'];
        }

        $stmtUpdate = $this->db->prepare("UPDATE ipl_planificador SET form_id_origen = ? WHERE id = ?");
        try {
            $stmtUpdate->execute([$form_id, $id]);
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function obtenerFechaIdeal($formId, $hcNumber)
    {
        $stmt = $this->db->prepare("SELECT fecha_ficticia FROM ipl_planificador WHERE form_id_origen = ? AND hc_number = ?");
        $stmt->execute([$formId, $hcNumber]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        return $resultado['fecha_ficticia'] ?? null;
    }
}

