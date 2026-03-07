<?php

namespace App\Modules\Billing\Services;

use PDO;

class BillingPreviewService
{
    private PDO $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    public function prepararPreviewFacturacion(string $formId, string $hcNumber): array
    {
        $preview = [
            'procedimientos' => [],
            'insumos' => [],
            'derechos' => [],
            'oxigeno' => [],
            'anestesia' => [],
            'reglas' => [],
        ];

        $appliedRules = [];

        $esImagen = false;
        $esConsulta = false;

        // 1. Procedimientos
        $stmt = $this->db->prepare("SELECT procedimientos, fecha_inicio FROM protocolo_data WHERE form_id = ?");
        $stmt->execute([$formId]);
        $rowProtocolo = $stmt->fetch(PDO::FETCH_ASSOC);
        $json = $rowProtocolo['procedimientos'] ?? null;
        $fechaInicio = $rowProtocolo['fecha_inicio'] ?? null;

        // Obtener edad del paciente
        $stmtEdad = $this->db->prepare("SELECT fecha_nacimiento FROM patient_data WHERE hc_number = ?");
        $stmtEdad->execute([$hcNumber]);
        $fechaNacimiento = $stmtEdad->fetchColumn();

        $edad = null;
        if ($fechaNacimiento && $fechaInicio) {
            $nac = new \DateTime($fechaNacimiento);
            $fechaReferencia = new \DateTime($fechaInicio);
            $edad = $fechaReferencia->diff($nac)->y;
        }

        if ($json) {
            $procedimientos = json_decode($json, true);
            if (is_array($procedimientos)) {
                $tarifarioStmt = $this->db->prepare("
                    SELECT valor_facturar_nivel3, descripcion 
                    FROM tarifario_2014 
                    WHERE codigo = :codigo OR codigo = :codigo_sin_0 LIMIT 1
                ");

                foreach ($procedimientos as $p) {
                    $codigo = null;
                    $detalle = null;
                    $texto = $p['procInterno'] ?? '';

                    // Consultas oftalmológicas (SER-OFT-003/004/005/006/007) con códigos diferenciados
                    $codigoConsulta = $this->obtenerCodigoConsultaOftalmo($texto);
                    if ($codigoConsulta) {
                        $codigo = $codigoConsulta;
                        $detalle = $this->detalleConsultaOftalmo($texto);
                        $esConsulta = true;
                    } elseif (isset($p['procInterno']) && preg_match('/-\\s+(\\d{5,6})\\s+-\\s+(.+)$/', $p['procInterno'], $matches)) {
                        $codigo = $matches[1];
                        $detalle = $matches[2];
                    } else {
                        $imagen = $this->extraerProcedimientoImagen($texto);
                        if ($imagen) {
                            $codigo = $imagen['codigo'];
                            $detalle = $imagen['detalle'];
                            $esImagen = true;
                        }
                    }

                    if ($codigo && $detalle) {
                        $tarifa = $this->obtenerTarifaCodigo($tarifarioStmt, $codigo);
                        $precio = $tarifa['precio'];
                        $detalleTarifa = trim($tarifa['descripcion'] ?? '') ?: $detalle;
                        if ($tarifa['sinResultado']) {
                            $this->logPreviewDebug('Tarifa no encontrada para procedimiento de protocolo', [
                                'codigo' => $codigo,
                                'detalle' => $detalle,
                                'procInterno' => $texto,
                            ]);
                        }

                        $appliedRules[] = [
                            'titulo' => 'Tarifario',
                            // Para consulta/imágenes, y en general, preferimos mostrar la descripción oficial del tarifario.
                            'detalle' => sprintf('Código %s (%s) con valor: $%0.2f', $codigo, $detalleTarifa, $precio),
                        ];

                        $preview['procedimientos'][] = [
                            'procCodigo' => $codigo,
                            'procDetalle' => $detalleTarifa,
                            'procPrecio' => $precio
                        ];
                    }
                }
            }
        }

        // Fallback para procedimientos de imágenes (no quirúrgicos) o consulta oftalmo
        if (empty($preview['procedimientos'])) {
            $stmtImagen = $this->db->prepare("SELECT procedimiento_proyectado FROM procedimiento_proyectado WHERE form_id = ? LIMIT 1");
            $stmtImagen->execute([$formId]);
            $procTexto = $stmtImagen->fetchColumn();

            $procTexto = $procTexto ?: '';

            // Si es consulta oftalmológica, facturar con el código definido por SER-OFT
            $codigoConsulta = $this->obtenerCodigoConsultaOftalmo($procTexto);
            if ($codigoConsulta) {
                $esConsulta = true;
                $tarifarioStmt = $this->db->prepare("
                    SELECT valor_facturar_nivel3, descripcion 
                    FROM tarifario_2014 
                    WHERE codigo = :codigo OR codigo = :codigo_sin_0 LIMIT 1
                ");

                $detalleConsulta = $this->detalleConsultaOftalmo($procTexto);
                $tarifa = $this->obtenerTarifaCodigo($tarifarioStmt, $codigoConsulta);
                $precio = $tarifa['precio'];
                if ($tarifa['sinResultado']) {
                    $this->logPreviewDebug('Tarifa no encontrada para consulta oftalmo (fallback)', [
                        'codigo' => $codigoConsulta,
                        'detalle' => $detalleConsulta,
                        'texto' => $procTexto,
                    ]);
                }

                $detalleTarifa = trim($tarifa['descripcion'] ?? '') ?: $detalleConsulta;

                $preview['procedimientos'][] = [
                    'procCodigo' => $codigoConsulta,
                    // Mostrar la descripción oficial del tarifario cuando exista.
                    'procDetalle' => $detalleTarifa,
                    'procPrecio' => $precio,
                ];

                $appliedRules[] = [
                    'titulo' => 'Tarifario',
                    'detalle' => sprintf('Código %s (%s) con valor: $%0.2f', $codigoConsulta, $detalleTarifa, $precio),
                ];
            } else {
                $imagen = $this->extraerProcedimientoImagen($procTexto);
                if ($imagen) {
                    $esImagen = true;
                    $tarifarioStmt = $this->db->prepare("
                    SELECT valor_facturar_nivel3, descripcion 
                    FROM tarifario_2014 
                    WHERE codigo = :codigo OR codigo = :codigo_sin_0 LIMIT 1
                ");
                    $tarifa = $this->obtenerTarifaCodigo($tarifarioStmt, $imagen['codigo']);
                    $precio = $tarifa['precio'];
                    $detalleTarifa = trim($tarifa['descripcion'] ?? '') ?: $imagen['detalle'];
                    if ($tarifa['sinResultado']) {
                        $this->logPreviewDebug('Tarifa no encontrada para imagen (fallback)', [
                            'codigo' => $imagen['codigo'],
                            'detalle' => $imagen['detalle'],
                            'texto' => $procTexto,
                        ]);
                    }

                    $preview['procedimientos'][] = [
                        'procCodigo' => $imagen['codigo'],
                        'procDetalle' => $detalleTarifa,
                        'procPrecio' => $precio
                    ];

                    $appliedRules[] = [
                        'titulo' => 'Tarifario',
                        'detalle' => sprintf('Código %s (%s) con valor: $%0.2f', $imagen['codigo'], $detalleTarifa, $precio),
                    ];
                }
            }
        }

        // 2. Insumos y derechos (desde API)
        if ($esImagen || $esConsulta) {
            return [
                'procedimientos' => $preview['procedimientos'],
                'insumos' => [],
                'derechos' => [],
                'oxigeno' => [],
                'anestesia' => [],
                'reglas' => $appliedRules,
            ];
        }

        $opts = [
            "http" => [
                "method" => "POST",
                "header" => "Content-Type: application/json",
                "content" => json_encode(["hcNumber" => $hcNumber, "form_id" => $formId])
            ]
        ];
        $context = stream_context_create($opts);

        $responseData = [];
        try {
            $result = @file_get_contents("https://asistentecive.consulmed.me/api/insumos/obtener.php", false, $context);
            if ($result === false) {
                throw new \RuntimeException('No se pudo contactar con el servicio de insumos.');
            }

            $decoded = json_decode($result, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                throw new \RuntimeException('Respuesta inválida del servicio de insumos.');
            }

            $responseData = $decoded;
        } catch (\Throwable $e) {
            error_log("❌ Error al obtener insumos para el preview: " . $e->getMessage());
            $responseData = [];
        }

        if (!empty($responseData['insumos'])) {
            $insumosDecodificados = $responseData['insumos'];
            $afiliacion = strtoupper(trim($responseData['afiliacion'] ?? ''));
            if ($afiliacion !== '') {
                $appliedRules[] = [
                    'titulo' => 'Tarifa por afiliación',
                    'detalle' => "Valores calculados usando afiliación {$afiliacion} para insumos y derechos",
                ];
            }

            foreach (['quirurgicos', 'anestesia'] as $categoria) {
                if (!empty($insumosDecodificados[$categoria])) {
                    foreach ($insumosDecodificados[$categoria] as $i) {
                        if (!empty($i['codigo'])) {
                            $precio = $this->obtenerPrecioPorAfiliacion(
                                $i['codigo'],
                                $afiliacion,
                                (int)($i['id'] ?? 0)
                            ) ?? ($i['precio'] ?? 0);

                            $preview['insumos'][] = [
                                'id' => $i['id'],
                                'codigo' => $i['codigo'],
                                'nombre' => $i['nombre'],
                                'cantidad' => $i['cantidad'],
                                'precio' => $precio,
                                'iva' => $i['iva'] ?? 1
                            ];

                            $appliedRules[] = [
                                'titulo' => 'Precio de insumo',
                                'detalle' => sprintf('Código %s (%s) con tarifa $%0.2f', $i['codigo'], $i['nombre'], $precio),
                            ];
                        }
                    }
                }
            }

            if (!empty($insumosDecodificados['equipos'])) {
                foreach ($insumosDecodificados['equipos'] as $equipo) {
                    if (!empty($equipo['codigo'])) {
                        $precio = $this->obtenerPrecioPorAfiliacion(
                            $equipo['codigo'],
                            $afiliacion,
                            (int)($equipo['id'] ?? 0)
                        ) ?? ($equipo['precio'] ?? 0);

                        $preview['derechos'][] = [
                            'id' => (int)$equipo['id'],
                            'codigo' => $equipo['codigo'],
                            'detalle' => $equipo['nombre'],
                            'cantidad' => (int)$equipo['cantidad'],
                            'iva' => 0,
                            'precioAfiliacion' => $precio
                        ];

                        $appliedRules[] = [
                            'titulo' => 'Derechos de sala',
                            'detalle' => sprintf('Equipo %s → %s x%d = $%0.2f', $equipo['codigo'], $equipo['nombre'], (int)($equipo['cantidad'] ?? 1), $precio),
                        ];
                    }
                }
            }

            // 🔄 Unificar insumos duplicados por codigo
            if (!empty($preview['insumos'])) {
                $insumosAgrupados = [];
                foreach ($preview['insumos'] as $insumo) {
                    $key = $insumo['codigo']; // solo agrupamos por código
                    if (!isset($insumosAgrupados[$key])) {
                        $insumosAgrupados[$key] = $insumo;
                    } else {
                        // Sumar cantidades
                        $insumosAgrupados[$key]['cantidad'] += $insumo['cantidad'];

                        // Si el nombre del existente está vacío y este tiene, lo actualizamos
                        if (empty($insumosAgrupados[$key]['nombre']) && !empty($insumo['nombre'])) {
                            $insumosAgrupados[$key]['nombre'] = $insumo['nombre'];
                        }
                    }
                }
                $preview['insumos'] = array_values($insumosAgrupados);
                usort($preview['insumos'], function ($a, $b) {
                    return strcasecmp($a['nombre'], $b['nombre']);
                });
            }
        }

        // 3. Oxígeno
        if (!empty($responseData['duracion'])) {
            [$h, $m] = explode(':', $responseData['duracion']);
            $tiempo = (float)$h + ((int)$m / 60);

            $preview['oxigeno'][] = [
                'codigo' => '911111',
                'nombre' => 'OXIGENO',
                'tiempo' => $tiempo,
                'litros' => 3,
                'valor1' => 60.00,
                'valor2' => 0.01,
                'precio' => round($tiempo * 3 * 60.00 * 0.01, 2)
            ];

            $appliedRules[] = [
                'titulo' => 'Oxígeno',
                'detalle' => sprintf('Duración %s horas con flujo estándar 3 L/min', number_format($tiempo, 2)),
            ];
        }

        // 4. Anestesia
        $afiliacion = strtoupper(trim($responseData['afiliacion'] ?? ''));
        $codigoCirugia = $preview['procedimientos'][0]['procCodigo'] ?? '';
        $duracion = $responseData['duracion'] ?? '01:00';
        [$h, $m] = explode(':', $duracion);
        $cuartos = ceil(((int)$h * 60 + (int)$m) / 15);

        try {
            if (!empty($responseData['duracion'])) {
                [$h, $m] = explode(':', $responseData['duracion']);
                $duracionMin = ((int)$h * 60) + (int)$m;

                $derechos = $this->obtenerDerechoPorDuracion($duracionMin);
                foreach ($derechos as $d) {
                    $preview['derechos'][] = [
                        'codigo' => $d['codigo'],
                        'detalle' => $d['detalle'],
                        'cantidad' => 1,
                        'iva' => 0,
                        'precioAfiliacion' => $d['precioAfiliacion']
                    ];

                    $appliedRules[] = [
                        'titulo' => 'Derechos por duración',
                        'detalle' => sprintf('Duración %d minutos → código %s', $duracionMin, $d['codigo']),
                    ];
                }
            }
        } catch (\Throwable $e) {
            error_log("❌ Error en obtenerDerechoPorDuracion: " . $e->getMessage());
        }

        // Determinar código de anestesia y agregar entradas según afiliación
        $codigoAnestesiaBase = '999999';

        if ($afiliacion === "ISSFA" && $codigoCirugia === "66984") {
            // Regla: ISSFA + 66984 → solo 999999 por tiempo de anestesia
            $preview['anestesia'][] = [
                'codigo' => $codigoAnestesiaBase,
                'nombre' => 'MODIFICADOR POR TIEMPO DE ANESTESIA',
                'tiempo' => $cuartos,
                'valor2' => 13.34,
                'precio' => round($cuartos * 13.34, 2)
            ];

            $appliedRules[] = [
                'titulo' => 'Regla ISSFA 66984',
                'detalle' => sprintf('Solo modificador 999999 por %d cuartos de hora', $cuartos),
            ];

            // Regla adicional: si edad ≥ 70, agregar también 99100
            if ($edad !== null && $edad >= 70) {
                $preview['anestesia'][] = [
                    'codigo' => '99100',
                    'nombre' => 'ANESTESIA POR EDAD EXTREMA',
                    'tiempo' => 1,
                    'valor2' => 13.34,
                    'precio' => round(1 * 13.34, 2)
                ];

                $appliedRules[] = [
                    'titulo' => 'Anestesia por edad',
                    'detalle' => 'Paciente ≥ 70 años aplica código 99100',
                ];
            }
        } elseif ($afiliacion === "ISSFA") {
            $cantidad99149 = ($cuartos >= 2) ? 1 : $cuartos;
            $cantidad99150 = ($cuartos > 2) ? $cuartos - 2 : 0;

            if ($cantidad99149 > 0) {
                $preview['anestesia'][] = [
                    'codigo' => '99149',
                    'nombre' => 'SEDACIÓN INICIAL 30 MIN',
                    'tiempo' => $cantidad99149,
                    'valor2' => 13.34,
                    'precio' => round($cantidad99149 * 13.34, 2)
                ];

                $appliedRules[] = [
                    'titulo' => 'Sedación inicial',
                    'detalle' => sprintf('99149 por %d bloque(s) inicial(es)', $cantidad99149),
                ];
            }
            if ($cantidad99150 > 0) {
                $preview['anestesia'][] = [
                    'codigo' => '99150',
                    'nombre' => 'SEDACIÓN ADICIONAL 15 MIN',
                    'tiempo' => $cantidad99150,
                    'valor2' => 13.34,
                    'precio' => round($cantidad99150 * 13.34, 2)
                ];

                $appliedRules[] = [
                    'titulo' => 'Sedación adicional',
                    'detalle' => sprintf('99150 por %d bloque(s) adicionales', $cantidad99150),
                ];
            }

            $preview['anestesia'][] = [
                'codigo' => $codigoAnestesiaBase,
                'nombre' => 'MODIFICADOR POR TIEMPO DE ANESTESIA',
                'tiempo' => $cuartos,
                'valor2' => 13.34,
                'precio' => round($cuartos * 13.34, 2)
            ];

            $appliedRules[] = [
                'titulo' => 'Modificador de anestesia',
                'detalle' => sprintf('999999 por %d cuartos de hora', $cuartos),
            ];

            if ($edad !== null && $edad >= 70) {
                $preview['anestesia'][] = [
                    'codigo' => '99100',
                    'nombre' => 'ANESTESIA POR EDAD EXTREMA',
                    'tiempo' => 1,
                    'valor2' => 13.34,
                    'precio' => round(1 * 13.34, 2)
                ];

                $appliedRules[] = [
                    'titulo' => 'Anestesia por edad',
                    'detalle' => 'Paciente ≥ 70 años aplica código 99100',
                ];
            }
        } else {
            $preview['anestesia'][] = [
                'codigo' => $codigoAnestesiaBase,
                'nombre' => 'MODIFICADOR POR TIEMPO DE ANESTESIA',
                'tiempo' => $cuartos,
                'valor2' => 13.34,
                'precio' => round($cuartos * 13.34, 2)
            ];

            $appliedRules[] = [
                'titulo' => 'Modificador de anestesia',
                'detalle' => sprintf('999999 por %d cuartos de hora', $cuartos),
            ];

            if ($edad !== null && $edad >= 70) {
                $preview['anestesia'][] = [
                    'codigo' => '99100',
                    'nombre' => 'ANESTESIA POR EDAD EXTREMA',
                    'tiempo' => 1,
                    'valor2' => 13.34,
                    'precio' => round(1 * 13.34, 2)
                ];

                $appliedRules[] = [
                    'titulo' => 'Anestesia por edad',
                    'detalle' => 'Paciente ≥ 70 años aplica código 99100',
                ];
            }
        }

        $preview['reglas'] = $appliedRules;

        return $preview;
    }

    private function sanitizeImagenNombre(string $nombre): string
    {
        $clean = trim($nombre);
        $clean = preg_replace('/^Imagenes\\s*-\\s*/i', '', $clean) ?? $clean;
        $clean = preg_replace('/^Dia-\\d+\\s*-\\s*/i', '', $clean) ?? $clean;
        return trim($clean);
    }

    private function extraerProcedimientoImagen(string $texto): ?array
    {
        $nombre = $this->sanitizeImagenNombre($texto);
        preg_match_all('/\\d{3,}/', $nombre, $allCodes);
        $codes = $allCodes[0] ?? [];
        if (empty($codes)) {
            $this->logPreviewDebug('No se encontraron códigos en texto de imagen', ['texto' => $texto, 'normalizado' => $nombre]);
            return null;
        }

        // Preferimos el último código con longitud >=5 (p. ej., 281032); si no, el último encontrado
        $codigo = null;
        foreach (array_reverse($codes) as $code) {
            if (strlen($code) >= 5) {
                $codigo = $code;
                break;
            }
        }
        $codigo ??= end($codes);

        // Quitar el prefijo hasta el código elegido para dejar el detalle limpio
        $detalle = preg_replace('/^.*?' . preg_quote($codigo, '/') . '\\s*-\\s*/', '', $nombre) ?? $nombre;

        return [
            'codigo' => $codigo,
            'detalle' => trim($detalle),
        ];
    }

    private function obtenerPrecioPorAfiliacion(string $codigo, string $afiliacion, ?int $id = null): ?float
    {
        $afiliacionUpper = strtoupper($afiliacion);
        $iessVariants = [
            'IESS',
            'CONTRIBUYENTE VOLUNTARIO',
            'CONYUGE',
            'CONYUGE PENSIONISTA',
            'SEGURO CAMPESINO',
            'SEGURO CAMPESINO JUBILADO',
            'SEGURO GENERAL',
            'SEGURO GENERAL JUBILADO',
            'SEGURO GENERAL POR MONTEPIO',
            'SEGURO GENERAL TIEMPO PARCIAL',
            'HIJOS DEPENDIENTES',
        ];

        if (in_array($afiliacionUpper, $iessVariants, true)) {
            $campoPrecio = 'precio_iess';
        } else {
            $campoPrecio = match ($afiliacionUpper) {
                'ISSPOL' => 'precio_isspol',
                'ISSFA' => 'precio_issfa',
                'MSP' => 'precio_msp',
                default => 'precio_base',
            };
        }

        if ($id) {
            $stmt = $this->db->prepare("SELECT {$campoPrecio} FROM insumos WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $id]);
        } else {
            $stmt = $this->db->prepare("SELECT {$campoPrecio} FROM insumos WHERE codigo_isspol = :codigo OR codigo_issfa = :codigo OR codigo_iess = :codigo OR codigo_msp = :codigo LIMIT 1");
            $stmt->execute(['codigo' => $codigo]);
        }

        $precio = $stmt->fetchColumn();

        return $precio !== false ? (float) $precio : null;
    }

    /**
     * @return array<int, array{grupo:string,codigo:string,detalle:string,precioAfiliacion:float}>
     */
    private function obtenerDerechoPorDuracion(int $duracionMinutos): array
    {
        $stmt = $this->db->prepare("
            SELECT id, codigo, descripcion, valor_facturar_nivel1
            FROM tarifario_2014
            WHERE descripcion LIKE 'DESDE%' OR descripcion LIKE 'HASTA%'
            ORDER BY id ASC
        ");
        $stmt->execute();
        $derechos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $grupoA = [];
        $grupoB = [];

        foreach ($derechos as $d) {
            if ((int) $d['codigo'] >= 394200 && (int) $d['codigo'] < 394400) {
                $grupoA[] = $d;
            } elseif ((int) $d['codigo'] >= 396200 && (int) $d['codigo'] < 396400) {
                $grupoB[] = $d;
            }
        }

        $resultado = [];
        foreach (['A' => $grupoA, 'B' => $grupoB] as $grupo => $items) {
            foreach ($items as $d) {
                if (preg_match('/DESDE (\d+) MIN.*HASTA ?(\d+) MIN/i', $d['descripcion'], $m)) {
                    $desde = (int) $m[1];
                    $hasta = (int) $m[2];
                    if ($duracionMinutos >= $desde && $duracionMinutos <= $hasta) {
                        $resultado[] = [
                            'grupo' => $grupo,
                            'codigo' => $d['codigo'],
                            'detalle' => $d['descripcion'],
                            'precioAfiliacion' => (float) $d['valor_facturar_nivel1'],
                        ];
                        break;
                    }
                } elseif (preg_match('/HASTA ?(\d+)MIN/i', $d['descripcion'], $m)) {
                    $hasta = (int) $m[1];
                    if ($duracionMinutos <= $hasta) {
                        $resultado[] = [
                            'grupo' => $grupo,
                            'codigo' => $d['codigo'],
                            'detalle' => $d['descripcion'],
                            'precioAfiliacion' => (float) $d['valor_facturar_nivel1'],
                        ];
                        break;
                    }
                }
            }
        }

        $stmtFijo = $this->db->prepare("SELECT codigo, descripcion, valor_facturar_nivel1 FROM tarifario_2014 WHERE codigo = '395281' LIMIT 1");
        $stmtFijo->execute();
        $fijo = $stmtFijo->fetch(PDO::FETCH_ASSOC);
        if ($fijo !== false) {
            $resultado[] = [
                'grupo' => 'FIJO',
                'codigo' => $fijo['codigo'],
                'detalle' => $fijo['descripcion'],
                'precioAfiliacion' => (float) $fijo['valor_facturar_nivel1'],
            ];
        }

        return $resultado;
    }

    private function obtenerTarifaCodigo(\PDOStatement $tarifarioStmt, string $codigo): array
    {
        $tarifarioStmt->execute([
            'codigo' => $codigo,
            'codigo_sin_0' => ltrim($codigo, '0')
        ]);
        $row = $tarifarioStmt->fetch(PDO::FETCH_ASSOC);

        return [
            'precio' => $row ? (float)($row['valor_facturar_nivel3'] ?? 0) : 0.0,
            'descripcion' => $row['descripcion'] ?? null,
            'sinResultado' => $row === false,
        ];
    }

    private function logPreviewDebug(string $mensaje, array $context = []): void
    {
        error_log('[PreviewImagen] ' . $mensaje . ' ' . json_encode($context));
    }

    private function obtenerCodigoConsultaOftalmo(string $texto): ?string
    {
        $t = strtoupper(trim($texto));
        // tolera sufijos como "... ojo derecho" etc.
        if (str_starts_with($t, 'SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT-003 - CONSULTA OFTALMOLOGICA NUEVO PACIENTE')) {
            return '92002';
        }

        if (
            str_starts_with($t, 'SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT-004 - CONSULTA OFTALMOLOGICA CITA MEDICA')
            || str_starts_with($t, 'SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT-005 - CONSULTA OFTALMOLOGICA DE CONTROL')
            || str_starts_with($t, 'SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT-006 - CONSULTA OFTALMOLOGICA INTERCONSULTA')
            || str_starts_with($t, 'SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT-007 - REVISION DE EXAMENES')
        ) {
            return '92012';
        }

        return null;
    }

    private function detalleConsultaOftalmo(string $texto): string
    {
        // Mantener el texto original como detalle (sin alterar), solo trim.
        return trim($texto);
    }
}
