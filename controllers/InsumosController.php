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

    public function listarTodos()
    {
        $stmt = $this->db->query("SELECT * FROM insumos ORDER BY categoria, nombre");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

