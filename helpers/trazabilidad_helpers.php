<?php

namespace Helpers;

use PDO;

class TrazabilidadHelpers
{
    public static function contieneBiometria($texto)
    {
        return preg_match('/biometr/i', $texto);
    }


    public static function contieneAnestesia($texto)
    {
        return preg_match('/anest/i', $texto);
    }

    public static function contieneOjo($texto)
    {
        if (!$texto) return '';
        if (preg_match('/der/i', $texto)) return 'OJO DERECHO';
        if (preg_match('/izq/i', $texto)) return 'OJO IZQUIERDO';
        return '';
    }

    public static function obtenerFechaValida($item)
    {
        return $item['fecha'] ?? $item['fecha_consulta'] ?? null;
    }

    public static function construirEpisodios(array $datos_ordenados): array
    {
        $episodios = [];
        $utilizados = [];

        for ($i = 0; $i < count($datos_ordenados); ++$i) {
            $item = $datos_ordenados[$i];
            $form_id = $item['form_id'];
            if (in_array($form_id, $utilizados)) continue;

            // 🔍 Solicitud de biometría
            $esSolicitudBio = false;
            $bio_text = '';
            $fecha_solicitud = null;
            if (!empty($item['plan']) && self::contieneBiometria($item['plan'])) {
                $esSolicitudBio = true;
                $bio_text = $item['plan'];
                $fecha_solicitud = $item['fecha'] ?? $item['fecha_consulta'] ?? null;
            } elseif (!empty($item['solicitado']) && self::contieneBiometria($item['solicitado'])) {
                $esSolicitudBio = true;
                $bio_text = $item['solicitado'];
                $fecha_solicitud = $item['fecha'] ?? $item['fecha_consulta'] ?? null;
            } elseif (!empty($item['examenes'])) {
                $examenes = json_decode($item['examenes'], true);
                if (is_array($examenes)) {
                    foreach ($examenes as $ex) {
                        if ($ex['codigo'] === '281230') {
                            $esSolicitudBio = true;
                            $bio_text = 'Solicitud de biometría ocular';
                            $fecha_solicitud = $item['fecha'] ?? $item['fecha_consulta'] ?? null;
                            break;
                        }
                    }
                }
            }
            if (!$esSolicitudBio) continue;

            // 🔬 Biometría realizada
            $bio_form_id = $fecha_bio = null;
            for ($j = $i + 1; $j < count($datos_ordenados); $j++) {
                $b = $datos_ordenados[$j];
                if (in_array($b['form_id'], $utilizados)) continue;
                if (
                    (!empty($b['procedimiento_proyectado']) && self::contieneBiometria($b['procedimiento_proyectado'])) ||
                    (!empty($b['examenes']) && strpos($b['examenes'], '281230') !== false)
                ) {
                    if (in_array(strtoupper($b['estado_agenda']), ['LLEGADO', 'REALIZADO'])) {
                        $bio_form_id = $b['form_id'];
                        $fecha_bio = $b['fecha'] ?? $b['fecha_consulta'] ?? null;
                        break;
                    }
                }
            }
            if (!$bio_form_id) continue;

            // 💉 Anestesia
            $anest_form_id = $fecha_anest = null;
            for ($j = $i + 1; $j < count($datos_ordenados); $j++) {
                $a = $datos_ordenados[$j];
                if (in_array($a['form_id'], $utilizados)) continue;
                if (
                    (!empty($a['plan']) && self::contieneAnestesia($a['plan'])) ||
                    (!empty($a['motivo_consulta']) && self::contieneAnestesia($a['motivo_consulta']))
                ) {
                    $anest_form_id = $a['form_id'];
                    $fecha_anest = $a['fecha'] ?? $a['fecha_consulta'] ?? null;
                    break;
                }
            }
            if (!$anest_form_id) continue;

            // 🗓️ Cirugía programada
            $prog_form_id = $fecha_prog = $prog_cirugia = $prog_ojo = null;
            for ($j = $i + 1; $j < count($datos_ordenados); $j++) {
                $p = $datos_ordenados[$j];
                if (in_array($p['form_id'], $utilizados)) continue;
                if (
                    !empty($p['estado_agenda']) && strtoupper($p['estado_agenda']) === 'AGENDADO' &&
                    !empty($p['cirugia']) && strtoupper(trim($p['cirugia'])) !== 'SELECCIONE'
                ) {
                    $prog_form_id = $p['form_id'];
                    $fecha_prog = $p['fecha'] ?? null;
                    $prog_cirugia = $p['cirugia'];
                    $prog_ojo = self::contieneOjo($p['cirugia']);
                    break;
                }
            }
            if (!$prog_form_id) continue;

            // 🏥 Cirugía realizada
            $real_form_id = $fecha_real = null;
            for ($j = $i + 1; $j < count($datos_ordenados); $j++) {
                $r = $datos_ordenados[$j];
                if (in_array($r['form_id'], $utilizados)) continue;
                if (
                    !empty($r['estado_agenda']) && strtoupper($r['estado_agenda']) === 'REALIZADO' &&
                    !empty($r['fecha']) &&
                    !empty($r['cirugia']) && strtoupper(trim($r['cirugia'])) !== 'SELECCIONE'
                ) {
                    $real_form_id = $r['form_id'];
                    $fecha_real = $r['fecha'];
                    break;
                }
            }
            if (!$real_form_id) continue;

            // ✅ Marcar como usados
            foreach ([$form_id, $bio_form_id, $anest_form_id, $prog_form_id, $real_form_id] as $fid) {
                if ($fid) $utilizados[] = $fid;
            }

            // 🧬 Agregar episodio
            $episodios[] = [
                'solicitud' => [
                    'fecha' => $fecha_solicitud,
                    'form_id' => $form_id,
                    'texto' => $bio_text,
                ],
                'biometria' => [
                    'fecha' => $fecha_bio,
                    'form_id' => $bio_form_id,
                ],
                'anestesia' => [
                    'fecha' => $fecha_anest,
                    'form_id' => $anest_form_id,
                ],
                'programada' => [
                    'fecha' => $fecha_prog,
                    'form_id' => $prog_form_id,
                    'cirugia' => $prog_cirugia,
                    'ojo' => $prog_ojo,
                ],
                'realizada' => [
                    'fecha' => $fecha_real,
                    'form_id' => $real_form_id,
                ],
            ];
        }

        return $episodios;
    }

    public static function imprimirIntervalo(string $titulo, ?\DateTime $inicio, ?\DateTime $fin): string
    {
        if ($inicio && $fin) {
            $dias = $inicio->diff($fin)->days;
            return "<div class='item'>📈 {$titulo}: {$dias} días</div>";
        }
        return "<div class='item'>📈 {$titulo}: N/D</div>";
    }

    public static function agruparProcesosPorFormulario(array $datos): array
    {
        $procesos = [];

        foreach ($datos as $item) {
            $formId = $item['form_id'];

            if (!isset($procesos[$formId])) {
                $procesos[$formId] = [
                    'biometria_fecha' => null,
                    'biometria_realizada_fecha' => null,
                    'diagnostico_plan' => null,
                    'examenes' => [],
                    'cirugia_fecha' => null,
                    'cirugia' => null,
                ];
            }

            if (!empty($item['examenes'])) {
                $examenes = json_decode($item['examenes'], true);
                foreach ($examenes as $ex) {
                    if (
                        isset($ex['codigo']) && $ex['codigo'] === '281230' &&
                        empty($procesos[$formId]['biometria_fecha'])
                    ) {
                        $procesos[$formId]['biometria_fecha'] = $item['fecha'] ?? $item['fecha_consulta'] ?? 'SOLICITADA';
                    }
                }
            }

            if (!empty($item['diagnosticos']) && strpos($item['plan'], 'FACO') !== false) {
                $procesos[$formId]['diagnostico_fecha'] = $item['fecha'];
                $procesos[$formId]['diagnostico_plan'] = $item['plan'];
            }

            if ($item['estado_agenda'] === 'LLEGADO' && !empty($item['procedimiento_proyectado'])) {
                $procesos[$formId]['examenes'][] = [
                    'nombre' => $item['procedimiento_proyectado'],
                    'fecha' => $item['fecha']
                ];
            }

            if (
                $item['estado_agenda'] === 'LLEGADO' &&
                strpos($item['procedimiento_proyectado'] ?? '', '281230') !== false &&
                empty($procesos[$formId]['biometria_realizada_fecha'])
            ) {
                $procesos[$formId]['biometria_realizada_fecha'] = $item['fecha'];
            }

            $cirugiaValida = !empty($item['cirugia']) && strtoupper(trim($item['cirugia'])) !== 'SELECCIONE';
            $solicitudValida = !empty($item['solicitado']) && strtoupper(trim($item['solicitado'])) !== 'SELECCIONE';

            if (!empty($item['fecha']) && ($cirugiaValida || $solicitudValida)) {
                $procesos[$formId]['cirugia_fecha'] = $item['fecha'];
                $procesos[$formId]['cirugia'] = $cirugiaValida ? $item['cirugia'] : $item['solicitado'];
            }

            if (!empty($item['solicitado']) && empty($procesos[$formId]['cirugia_fecha'])) {
                $procesos[$formId]['solicitud_cirugia'] = $item['solicitado'];
                $procesos[$formId]['solicitud_cirugia_fecha'] = $item['fecha'];
            }
        }

        return $procesos;
    }

    public static function renderFormulariosRestantes(array $datos_ordenados, array $utilizados, $controller): string
    {
        $html = '';
        $restantes = array_filter($datos_ordenados, function ($item) use ($utilizados) {
            return !in_array($item['form_id'], $utilizados);
        });

        if (count($restantes) > 0) {
            $html .= "<div class='box'>";
            $html .= "<div class='title'>📌 Formularios no agrupados o sin trazabilidad completa:</div>";
            $html .= "<ul style='margin-left:0;padding-left:18px;'>";

            foreach ($restantes as $r) {
                $fecha = self::obtenerFechaValida($r);
                $fid = $r['form_id'];
                $tipo = $r['tipo_evento'] ?? 'pendiente';
                $procedimiento = $r['procedimiento_proyectado'] ?? $r['solicitado'] ?? 'Formulario';

                // Caso especial: plan quirúrgico sin solicitud
                if (
                    (!empty($r['plan']) && preg_match('/faco|implante|quirurg/i', $r['plan'])) &&
                    (empty($r['cirugia']) || strtoupper(trim($r['cirugia'])) === 'SELECCIONE') &&
                    (!empty($r['solicitado']) && strtoupper(trim($r['solicitado'])) !== 'SELECCIONE')
                ) {
                    $label = $r['procedimiento_proyectado'] ?? "Formulario $fid";
                    $html .= "<li>⚠️ Plan quirúrgico registrado, pero sin solicitud formal. ($label, $fecha)</li>";

                    $eventoSolicitud = [
                        'tipo' => 'solicitud_cirugia',
                        'form_id' => $fid,
                        'fecha' => $fecha,
                        'procedimiento_proyectado' => $r['solicitado'],
                    ];
                    $html .= "<li>" . $controller->renderEvento($eventoSolicitud) . "</li>";
                    continue;
                }

                // Clasificación como control anestésico si aplica
                if (
                    (empty($r['tipo_evento']) || $r['tipo_evento'] === 'evento_pendiente') &&
                    (
                        (!empty($r['plan']) && self::contieneAnestesia($r['plan'])) ||
                        (!empty($r['motivo_consulta']) && self::contieneAnestesia($r['motivo_consulta']))
                    )
                ) {
                    $r['tipo_evento'] = 'control_anestesico';
                }

                $html .= "<li>" . $controller->renderEvento($r) . "</li>";
            }

            $html .= "</ul>";
            $html .= "</div>";
        }

        return $html;
    }
}