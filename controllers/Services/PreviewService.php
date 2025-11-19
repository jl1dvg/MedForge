<?php

namespace Services;

use PDO;
use Models\BillingInsumosModel;
use Helpers\FacturacionHelper;

class PreviewService
{
    private PDO $db;
    private BillingInsumosModel $billingInsumosModel;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
        $this->billingInsumosModel = new BillingInsumosModel($pdo);
    }

    public function prepararPreviewFacturacion(string $formId, string $hcNumber): array
    {
        $preview = [
            'procedimientos' => [],
            'insumos' => [],
            'derechos' => [],
            'oxigeno' => [],
            'anestesia' => []
        ];

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
                    if (isset($p['procInterno']) && preg_match('/- (\d{5}) - (.+)$/', $p['procInterno'], $matches)) {
                        $codigo = $matches[1];
                        $detalle = $matches[2];

                        $tarifarioStmt->execute([
                            'codigo' => $codigo,
                            'codigo_sin_0' => ltrim($codigo, '0')
                        ]);
                        $row = $tarifarioStmt->fetch(PDO::FETCH_ASSOC);
                        $precio = $row ? (float)$row['valor_facturar_nivel3'] : 0;

                        $preview['procedimientos'][] = [
                            'procCodigo' => $codigo,
                            'procDetalle' => $detalle,
                            'procPrecio' => $precio
                        ];
                    }
                }
            }
        }

        // 2. Insumos y derechos (desde API)
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

            foreach (['quirurgicos', 'anestesia'] as $categoria) {
                if (!empty($insumosDecodificados[$categoria])) {
                    foreach ($insumosDecodificados[$categoria] as $i) {
                        if (!empty($i['codigo'])) {
                            $precio = $this->billingInsumosModel->obtenerPrecioPorAfiliacion(
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
                        }
                    }
                }
            }

            if (!empty($insumosDecodificados['equipos'])) {
                foreach ($insumosDecodificados['equipos'] as $equipo) {
                    if (!empty($equipo['codigo'])) {
                        $precio = $this->billingInsumosModel->obtenerPrecioPorAfiliacion(
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

                $derechos = FacturacionHelper::obtenerDerechoPorDuracion($this->db, $duracionMin);
                foreach ($derechos as $d) {
                    $preview['derechos'][] = [
                        'codigo' => $d['codigo'],
                        'detalle' => $d['detalle'],
                        'cantidad' => 1,
                        'iva' => 0,
                        'precioAfiliacion' => $d['precioAfiliacion']
                    ];
                }
            }
        } catch (\Throwable $e) {
            error_log("❌ Error en obtenerDerechoPorDuracion: " . $e->getMessage());
        }

        // Determinar código de anestesia y agregar entradas según edad y afiliación
        $codigoAnestesiaBase = '999999';

        if ($afiliacion === "ISSFA" && $codigoCirugia === "66984") {
            $preview['anestesia'][] = [
                'codigo' => $codigoAnestesiaBase,
                'nombre' => 'MODIFICADOR POR TIEMPO DE ANESTESIA',
                'tiempo' => $cuartos,
                'valor2' => 13.34,
                'precio' => round($cuartos * 13.34, 2)
            ];

            if ($edad !== null && $edad >= 70) {
                $preview['anestesia'][] = [
                    'codigo' => '99100',
                    'nombre' => 'ANESTESIA POR EDAD EXTREMA',
                    'tiempo' => 1,
                    'valor2' => 13.34,
                    'precio' => round(1 * 13.34, 2)
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
            }
            if ($cantidad99150 > 0) {
                $preview['anestesia'][] = [
                    'codigo' => '99150',
                    'nombre' => 'SEDACIÓN ADICIONAL 15 MIN',
                    'tiempo' => $cantidad99150,
                    'valor2' => 13.34,
                    'precio' => round($cantidad99150 * 13.34, 2)
                ];
            }

            $preview['anestesia'][] = [
                'codigo' => $codigoAnestesiaBase,
                'nombre' => 'MODIFICADOR POR TIEMPO DE ANESTESIA',
                'tiempo' => $cuartos,
                'valor2' => 13.34,
                'precio' => round($cuartos * 13.34, 2)
            ];

            if ($edad !== null && $edad >= 70) {
                $preview['anestesia'][] = [
                    'codigo' => '99100',
                    'nombre' => 'ANESTESIA POR EDAD EXTREMA',
                    'tiempo' => 1,
                    'valor2' => 13.34,
                    'precio' => round(1 * 13.34, 2)
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

            if ($edad !== null && $edad >= 70) {
                $preview['anestesia'][] = [
                    'codigo' => '99100',
                    'nombre' => 'ANESTESIA POR EDAD EXTREMA',
                    'tiempo' => 1,
                    'valor2' => 13.34,
                    'precio' => round(1 * 13.34, 2)
                ];
            }
        }

        return $preview;
    }
}