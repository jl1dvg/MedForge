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
                $diaAleatorio = rand(15, 23);
                $ultimoDiaDelMes = (int)$fechaTentativa->format('t');
                $diaFinal = min($diaAleatorio, $ultimoDiaDelMes);

                $fechaTentativa->setDate(
                    (int)$fechaTentativa->format('Y'),
                    (int)$fechaTentativa->format('m'),
                    $diaFinal
                );
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
}