<?php
require_once __DIR__ . '/../../../bootstrap.php';

use Controllers\BillingController;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formId = $_POST['form_id'] ?? null;
    $hcNumber = $_POST['hc_number'] ?? null;

    if (!$formId || !$hcNumber) {
        die("Faltan parámetros.");
    }

    $billingModel = new \Models\BillingMainModel($pdo);
    $existe = $billingModel->findByFormId($formId);

    if ($existe) {
        header("Location: /views/billing/no_facturado.php?form_id=" . urlencode($formId));
        exit;
    }

    $billingId = $billingModel->insert($hcNumber, $formId);

    // Corregir fecha de creación con fecha del protocolo
    $stmtFecha = $pdo->prepare("SELECT fecha_inicio FROM protocolo_data WHERE form_id = ?");
    $stmtFecha->execute([$formId]);
    $fechaInicio = $stmtFecha->fetchColumn();
    if ($fechaInicio) {
        $billingModel->updateFechaCreacion($billingId, $fechaInicio);
    }

    // Obtener procedimientos del protocolo
    $stmt = $pdo->prepare("SELECT procedimientos FROM protocolo_data WHERE form_id = ?");
    $stmt->execute([$formId]);
    $json = $stmt->fetchColumn();

    if ($json) {
        $procedimientos = json_decode($json, true);
        if (is_array($procedimientos)) {
            $tarifarioStmt = $pdo->prepare("SELECT valor_facturar_nivel3, descripcion FROM tarifario_2014 WHERE codigo = :codigo OR codigo = :codigo_sin_0 LIMIT 1");

            $procedimientosModel = new \Models\BillingProcedimientosModel($pdo);
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

                    $procedimientosModel->insertar($billingId, [
                        'id' => null,
                        'procCodigo' => $codigo,
                        'procDetalle' => $detalle,
                        'procPrecio' => $precio
                    ]);

                    // Obtener insumos del protocolo
                    $stmtInsumos = $pdo->prepare("SELECT insumos FROM protocolo_data WHERE form_id = ?");
                    $stmtInsumos->execute([$formId]);
                    $jsonInsumos = $stmtInsumos->fetchColumn();

                    if ($jsonInsumos) {
                        $insumosDecodificados = json_decode($jsonInsumos, true);
                        if (is_array($insumosDecodificados)) {
                            $insumosModel = new \Models\BillingInsumosModel($pdo);
                            foreach ($insumosDecodificados as $grupo) {
                                foreach ($grupo as $i) {
                                    if (isset($i['id'], $i['codigo'], $i['nombre'], $i['cantidad'])) {
                                        $insumosModel->insertar($billingId, [
                                            'id' => $i['id'],
                                            'codigo' => $i['codigo'],
                                            'nombre' => $i['nombre'],
                                            'cantidad' => $i['cantidad'],
                                            'precio' => $i['precio'] ?? 0,
                                            'iva' => $i['iva'] ?? 1
                                        ]);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    header("Location: /views/billing/no_facturado.php?form_id=" . urlencode($formId));
    exit;
}