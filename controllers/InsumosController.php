<?php

namespace Controllers;

use PDO;

class InsumosController
{
    private $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    public function guardar($data)
    {
        $campos = [
            'nombre', 'categoria', 'codigo_issfa', 'codigo_isspol', 'codigo_iess', 'codigo_msp',
            'producto_issfa', 'precio_base', 'iva_15', 'gestion_10', 'precio_total', 'precio_isspol'
        ];

        // Validación básica
        foreach ($campos as $campo) {
            if (!isset($data[$campo])) {
                return ['success' => false, 'message' => "Campo faltante: $campo"];
            }
        }

        foreach (['precio_base', 'iva_15', 'gestion_10', 'precio_total', 'precio_isspol'] as $campo) {
            $data[$campo] = $data[$campo] === '' ? null : $data[$campo];
        }

        $id = $data['id'] ?? null;

        if ($id) {
            $sql = "UPDATE insumos SET nombre=?, categoria=?, codigo_issfa=?, codigo_isspol=?, codigo_iess=?, codigo_msp=?, 
                    producto_issfa=?, precio_base=?, iva_15=?, gestion_10=?, precio_total=?, precio_isspol=? WHERE id=?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['nombre'], $data['categoria'], $data['codigo_issfa'], $data['codigo_isspol'], $data['codigo_iess'], $data['codigo_msp'],
                $data['producto_issfa'], $data['precio_base'], $data['iva_15'], $data['gestion_10'], $data['precio_total'], $data['precio_isspol'],
                $id
            ]);
        } else {
            $sql = "INSERT INTO insumos (
                        nombre, categoria, codigo_issfa, codigo_isspol, codigo_iess, codigo_msp,
                    producto_issfa, precio_base, iva_15, gestion_10, precio_total, precio_isspol)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['nombre'], $data['categoria'], $data['codigo_issfa'], $data['codigo_isspol'], $data['codigo_iess'], $data['codigo_msp'],
                $data['producto_issfa'], $data['precio_base'], $data['iva_15'], $data['gestion_10'], $data['precio_total'], $data['precio_isspol']
            ]);
            $id = $this->db->lastInsertId();
        }

        return ['success' => true, 'message' => 'Insumo guardado correctamente.', 'id' => $id];
    }

    public function guardarMedicamento($data)
    {
        $campos = [
            'medicamento', 'via_administracion'
        ];

        // Validación básica
        foreach ($campos as $campo) {
            if (empty($data[$campo])) {
                return ['success' => false, 'message' => "El campo '$campo' es obligatorio."];
            }
        }

        // Sanitización
        $data['medicamento'] = trim($data['medicamento']);
        $data['via_administracion'] = trim($data['via_administracion']);

        $id = $data['id'] ?? null;

        try {
            if ($id) {
                $sql = "UPDATE medicamentos SET medicamento=?, via_administracion=? WHERE id=?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    $data['medicamento'], $data['via_administracion'], $id
                ]);
            } else {
                $sql = "INSERT INTO medicamentos (
                        medicamento, via_administracion)
                    VALUES (?, ?)";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    $data['medicamento'], $data['via_administracion']
                ]);
                $id = $this->db->lastInsertId();
            }

            return ['success' => true, 'message' => 'Insumo guardado correctamente.', 'id' => $id];
        } catch (\PDOException $e) {
            return ['success' => false, 'message' => 'Error al guardar el insumo: ' . $e->getMessage()];
        }
    }

    public function listarTodos()
    {
        $stmt = $this->db->query("SELECT * FROM insumos ORDER BY categoria, nombre");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarMedicamentos()
    {
        $stmt = $this->db->query("SELECT * FROM medicamentos ORDER BY medicamento");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

