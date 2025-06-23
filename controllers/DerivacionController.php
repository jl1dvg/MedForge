<?php

namespace Controllers;

use PDO;

class DerivacionController
{
    private $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    public function guardarDerivacion($codDerivacion, $formId, $hcNumber = null, $fechaRegistro = null, $fechaVigencia = null, $referido = null, $diagnostico = null)


    {
        $stmt = $this->db->prepare("
            INSERT INTO derivaciones_form_id (cod_derivacion, form_id, hc_number, fecha_registro, fecha_vigencia, referido, diagnostico)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                fecha_registro = VALUES(fecha_registro),
                fecha_vigencia = VALUES(fecha_vigencia),
                cod_derivacion = VALUES(cod_derivacion),
                referido = VALUES(referido),
                diagnostico = VALUES(diagnostico)
        ");
        return $stmt->execute([$codDerivacion, $formId, $hcNumber, $fechaRegistro, $fechaVigencia, $referido, $diagnostico]);
    }

    public function verificarFormIds(array $form_ids): array
    {
        if (empty($form_ids)) {
            return [
                "success" => false,
                "message" => "No se enviaron form_ids.",
                "existentes" => [],
                "nuevos" => []
            ];
        }

        // Evita inyecciones SQL
        $placeholders = implode(',', array_fill(0, count($form_ids), '?'));
        $sql = "SELECT form_id FROM procedimiento_proyectado WHERE form_id IN ($placeholders)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($form_ids);
        $resultados = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $form_ids_existentes = array_map('strval', $resultados);
        $form_ids_todos = array_map('strval', $form_ids);

        $form_ids_nuevos = array_diff($form_ids_todos, $form_ids_existentes);

        return [
            "success" => true,
            "existentes" => $form_ids_existentes,
            "nuevos" => array_values($form_ids_nuevos)
        ];
    }

    public function crearFormIdsFaltantes(array $procedimientos)
    {
        if (empty($procedimientos)) {
            return [
                'creados' => [],
                'ya_existian' => []
            ];
        }
        $form_ids = array_filter(array_column($procedimientos, 'form_id'));
        if (empty($form_ids)) {
            return [
                'creados' => [],
                'ya_existian' => []
            ];
        }
        $placeholders = implode(',', array_fill(0, count($form_ids), '?'));
        $sql = "SELECT form_id FROM procedimiento_proyectado WHERE form_id IN ($placeholders)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($form_ids);
        $existentes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $existentes = array_map('strval', $existentes);

        $faltantes = array_filter($procedimientos, function ($proc) use ($existentes) {
            return !in_array((string)$proc['form_id'], $existentes, true);
        });

        $stmtInsert = $this->db->prepare("
            INSERT INTO procedimiento_proyectado (
                form_id, hc_number, procedimiento_proyectado, doctor, fecha, hora, sede_departamento, id_sede, afiliacion, estado_agenda
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($faltantes as $item) {
            $stmtInsert->execute([
                $item['form_id'],
                $item['hc_number'],
                $item['procedimiento_proyectado'] ?? '',
                $item['doctor'] ?? null,
                $item['fecha'] ?? null,
                $item['hora'] ?? null,
                $item['sede_departamento'] ?? null,
                $item['id_sede'] ?? null,
                $item['afiliacion'] ?? null,
                $item['estado_agenda'] ?? null,
            ]);
        }

        return [
            'creados' => array_column($faltantes, 'form_id'),
            'ya_existian' => $existentes
        ];
    }

    public function insertarBillingMainSiNoExiste(array $form_hc_data)
    {
        $db = $this->db;

        $form_ids = array_map(fn($item) => $item['form_id'], $form_hc_data);
        $placeholders = implode(',', array_fill(0, count($form_ids), '?'));
        $stmt = $db->prepare("SELECT form_id, id FROM billing_main WHERE form_id IN ($placeholders)");
        $stmt->execute($form_ids);
        $existentesAssoc = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $existentes = array_keys($existentesAssoc);
        $existentes = array_map('strval', $existentes);

        $nuevos = [];
        $procedimientosInsertados = [];
        $errores = [];

        foreach ($form_hc_data as $item) {
            if (!isset($item['form_id'], $item['hc_number'])) {
                continue;
            }
            $form_id = (string)$item['form_id'];
            $billing_id = $existentesAssoc[$form_id] ?? null;

            if (!$billing_id) {
                try {
                    $stmtInsert = $db->prepare("INSERT INTO billing_main (form_id, hc_number) VALUES (?, ?)");
                    $stmtInsert->execute([$form_id, $item['hc_number']]);
                    $billing_id = $db->lastInsertId();
                    $nuevos[] = $form_id;
                } catch (\PDOException $e) {
                    $errores[] = "Error insertando billing_main $form_id: " . $e->getMessage();
                    continue;
                }
            }

            if (!empty($item['codigo']) && !empty($item['detalle']) && $billing_id) {
                try {
                    $stmtPrecio = $db->prepare("SELECT valor_facturar_nivel3 FROM tarifario_2014 WHERE codigo = ?");
                    $stmtPrecio->execute([$item['codigo']]);
                    $precio = $stmtPrecio->fetchColumn() ?: 0;
                    $stmtProc = $db->prepare("INSERT INTO billing_procedimientos (billing_id, proc_codigo, proc_detalle, proc_precio) VALUES (?, ?, ?, ?)");
                    $stmtProc->execute([$billing_id, $item['codigo'], $item['detalle'], $precio]);
                    $procedimientosInsertados[] = $form_id;
                } catch (\PDOException $e) {
                    $errores[] = "Error insertando procedimiento $form_id: " . $e->getMessage();
                }
            }
        }

        return [
            'nuevos' => $nuevos,
            'existentes' => $existentes,
            'procedimientos_insertados' => $procedimientosInsertados,
            'errores' => $errores
        ];
    }
}

