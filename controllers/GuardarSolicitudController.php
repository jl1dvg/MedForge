<?php

namespace Controllers;

use PDO;
use Modules\Notifications\Services\PusherConfigService;

class GuardarSolicitudController
{
    private $db;
    private PusherConfigService $pusherConfigService;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
        $this->pusherConfigService = new PusherConfigService($pdo);
    }

    public function guardar(array $data): array
    {
        if (!isset($data['hcNumber'], $data['form_id'], $data['solicitudes']) || !is_array($data['solicitudes'])) {
            return ["success" => false, "message" => "Datos no válidos o incompletos"];
        }

        // Helper closures for limpieza/normalización
        $clean = function ($v) {
            if (is_string($v)) {
                $v = trim($v);
                if ($v === '' || in_array(mb_strtoupper($v), ['SELECCIONE', 'NINGUNO'], true)) {
                    return null;
                }
                return $v;
            }
            return $v === '' ? null : $v;
        };

        $normPrioridad = function ($v) {
            $v = is_string($v) ? mb_strtoupper(trim($v)) : $v;
            return ($v === 'SI' || $v === 1 || $v === '1' || $v === true) ? 'SI' : 'NO';
        };

        $normFecha = function ($v) {
            $v = is_string($v) ? trim($v) : $v;
            if (!$v) {
                return null;
            }
            // Si viene ya en formato ISO lo dejamos tal cual
            if (preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $v)) {
                return $v;
            }
            // Intentar parsear formatos comunes (ej: dd/mm/yyyy hh:mm)
            $fmt = ['d/m/Y H:i', 'd-m-Y H:i', 'd/m/Y', 'd-m-Y', 'm/d/Y H:i', 'm-d-Y H:i'];
            foreach ($fmt as $f) {
                $dt = \DateTime::createFromFormat($f, $v);
                if ($dt instanceof \DateTime) {
                    return $dt->format(strlen($f) >= 10 ? 'Y-m-d H:i:s' : 'Y-m-d');
                }
            }
            // Si no se pudo parsear, devolver null para evitar warnings y errores
            return null;
        };

        // Validar que todas las solicitudes tengan procedimiento antes de persistir
        $missingProcedimientos = [];
        foreach ($data['solicitudes'] as $idx => $solicitud) {
            $procedimientoVal = $clean($solicitud['procedimiento'] ?? null);
            if ($procedimientoVal === null) {
                $missingProcedimientos[] = $solicitud['secuencia'] ?? ($idx + 1);
            }
        }
        if ($missingProcedimientos) {
            return [
                "success" => false,
                "message" => "El procedimiento es obligatorio en todas las solicitudes (faltante en: " . implode(', ', $missingProcedimientos) . ")"
            ];
        }

        // SQL con upsert, usando columnas explícitas (sin detalles_json)
        $sql = "INSERT INTO solicitud_procedimiento 
                (hc_number, form_id, secuencia, tipo, afiliacion, procedimiento, doctor, fecha, duracion, ojo, prioridad, producto, observacion, sesiones, lente_id, lente_nombre, lente_poder, lente_observacion, incision) 
                VALUES (:hc, :form_id, :secuencia, :tipo, :afiliacion, :procedimiento, :doctor, :fecha, :duracion, :ojo, :prioridad, :producto, :observacion, :sesiones, :lente_id, :lente_nombre, :lente_poder, :lente_observacion, :incision)
                ON DUPLICATE KEY UPDATE 
                    tipo = VALUES(tipo),
                    afiliacion = VALUES(afiliacion),
                    procedimiento = VALUES(procedimiento),
                    doctor = VALUES(doctor),
                    fecha = VALUES(fecha),
                    duracion = VALUES(duracion),
                    ojo = VALUES(ojo),
                    prioridad = VALUES(prioridad),
                    producto = VALUES(producto),
                    observacion = VALUES(observacion),
                    sesiones = VALUES(sesiones),
                    lente_id = VALUES(lente_id),
                    lente_nombre = VALUES(lente_nombre),
                    lente_poder = VALUES(lente_poder),
                    lente_observacion = VALUES(lente_observacion),
                    incision = VALUES(incision)";

        $stmt = $this->db->prepare($sql);

        foreach ($data['solicitudes'] as $solicitud) {
            // Limpieza / normalización por campo
            $secuencia = $solicitud['secuencia'] ?? null;
            $tipo = $clean($solicitud['tipo'] ?? null);            // texto: CIRUGÍA / INTERCONSULTA / TERAPIA
            $afiliacion = $clean($solicitud['afiliacion'] ?? null);      // texto, no id
            $procedimiento = $clean($solicitud['procedimiento'] ?? null);   // texto, no id
            $doctor = $clean($solicitud['doctor'] ?? null);           // texto, no id
            $fecha = $normFecha($solicitud['fecha'] ?? null);        // ISO si posible, sino null
            $duracion = $clean($solicitud['duracion'] ?? null);
            $prioridad = $normPrioridad($solicitud['prioridad'] ?? 'NO');
            $producto = $clean($solicitud['producto'] ?? null);         // texto libre
            $observacion = $clean($solicitud['observacion'] ?? null);
            $sesiones = $clean($solicitud['sesiones'] ?? null);

            // ojo puede venir como string o array
            $ojoVal = $solicitud['ojo'] ?? null;
            if (is_array($ojoVal)) {
                // ejemplo ["DERECHO","IZQUIERDO"] -> "DERECHO,IZQUIERDO"
                $ojoVal = implode(',', array_values(array_filter(array_map($clean, $ojoVal))));
            } else {
                $ojoVal = $clean($ojoVal);
            }

            // Mapear detalles (lente/insumos) al esquema plano
            $lenteId = $clean($solicitud['lente_id'] ?? null);
            $lenteNombre = $clean($solicitud['lente_nombre'] ?? null);
            $lentePoder = $clean($solicitud['lente_poder'] ?? null);
            $lenteObs = $clean($solicitud['lente_observacion'] ?? null);
            $incision = $clean($solicitud['incision'] ?? null);

            $detalles = $solicitud['detalles'] ?? [];
            if (is_array($detalles)) {
                // Preferir el primer detalle marcado como principal o el primero con datos
                $detallePlano = null;
                foreach ($detalles as $d) {
                    if (!is_array($d)) continue;
                    $detallePlano = $d;
                    if (!empty($d['principal']) || !empty($d['tipo'])) {
                        break;
                    }
                }
                if ($detallePlano) {
                    $lenteId = $lenteId ?: $clean($detallePlano['id_lente_intraocular'] ?? $detallePlano['lente_id'] ?? null);
                    $lenteNombre = $lenteNombre ?: $clean($detallePlano['lente'] ?? $detallePlano['lente_nombre'] ?? null);
                    $lentePoder = $lentePoder ?: $clean($detallePlano['poder'] ?? $detallePlano['lente_poder'] ?? null);
                    $lenteObs = $lenteObs ?: $clean($detallePlano['observaciones'] ?? $detallePlano['lente_observacion'] ?? null);
                    $incision = $incision ?: $clean($detallePlano['incision'] ?? null);
                    if (!$ojoVal) {
                        $ojoVal = $clean($detallePlano['lateralidad'] ?? null);
                    }
                }
            }

            $stmt->execute([
                ':hc' => $data['hcNumber'],
                ':form_id' => $data['form_id'],
                ':secuencia' => $secuencia,
                ':tipo' => $tipo,
                ':afiliacion' => $afiliacion,
                ':procedimiento' => $procedimiento,
                ':doctor' => $doctor,
                ':fecha' => $fecha,
                ':duracion' => $duracion,
                ':ojo' => $ojoVal,
                ':prioridad' => $prioridad,
                ':producto' => $producto,
                ':observacion' => $observacion,
                ':sesiones' => $sesiones,
                ':lente_id' => $lenteId,
                ':lente_nombre' => $lenteNombre,
                ':lente_poder' => $lentePoder,
                ':lente_observacion' => $lenteObs,
                ':incision' => $incision,
            ]);

            $this->pusherConfigService->trigger([
                'hc_number' => $data['hcNumber'],
                'form_id' => $data['form_id'],
                'secuencia' => $secuencia,
                'tipo' => $tipo,
                'afiliacion' => $afiliacion,
                'procedimiento' => $procedimiento,
                'doctor' => $doctor,
                'fecha' => $fecha,
                'duracion' => $duracion,
                'ojo' => $ojoVal,
                'prioridad' => $prioridad,
                'producto' => $producto,
                'observacion' => $observacion,
                'sesiones' => $sesiones,
                'detalles' => $detalles,
                'channels' => $this->pusherConfigService->getNotificationChannels(),
            ]);
        }

        return ["success" => true, "message" => "Solicitudes guardadas o actualizadas correctamente"];
    }
}
