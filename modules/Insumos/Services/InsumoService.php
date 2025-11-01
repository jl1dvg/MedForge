<?php

namespace Modules\Insumos\Services;

use PDO;
use PDOException;

class InsumoService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listarInsumos(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM insumos ORDER BY categoria, nombre');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarMedicamentos(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM medicamentos ORDER BY medicamento');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function guardar(array $payload): array
    {
        $campos = [
            'nombre',
            'categoria',
            'codigo_issfa',
            'codigo_isspol',
            'codigo_iess',
            'codigo_msp',
            'producto_issfa',
            'es_medicamento',
            'precio_base',
            'iva_15',
            'gestion_10',
            'precio_total',
            'precio_isspol',
        ];

        foreach ($campos as $campo) {
            if (!array_key_exists($campo, $payload)) {
                return [
                    'success' => false,
                    'message' => "Campo faltante: {$campo}",
                ];
            }
        }

        $numericFields = ['precio_base', 'iva_15', 'gestion_10', 'precio_total', 'precio_isspol'];
        foreach ($numericFields as $campo) {
            $valor = $payload[$campo];
            if ($valor === '' || $valor === null) {
                $payload[$campo] = null;
                continue;
            }

            if (!is_numeric($valor)) {
                return [
                    'success' => false,
                    'message' => "El campo {$campo} debe ser numérico.",
                ];
            }

            $payload[$campo] = (float) $valor;
        }

        $payload['es_medicamento'] = isset($payload['es_medicamento']) && $payload['es_medicamento'] !== ''
            ? (int) $payload['es_medicamento']
            : 0;

        $id = isset($payload['id']) && $payload['id'] !== '' ? (int) $payload['id'] : null;

        try {
            if ($id) {
                $sql = 'UPDATE insumos
                        SET nombre = ?, categoria = ?, codigo_issfa = ?, codigo_isspol = ?, codigo_iess = ?, codigo_msp = ?,
                            producto_issfa = ?, es_medicamento = ?, precio_base = ?, iva_15 = ?, gestion_10 = ?, precio_total = ?, precio_isspol = ?
                        WHERE id = ?';

                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    $payload['nombre'],
                    $payload['categoria'],
                    $payload['codigo_issfa'],
                    $payload['codigo_isspol'],
                    $payload['codigo_iess'],
                    $payload['codigo_msp'],
                    $payload['producto_issfa'],
                    $payload['es_medicamento'],
                    $payload['precio_base'],
                    $payload['iva_15'],
                    $payload['gestion_10'],
                    $payload['precio_total'],
                    $payload['precio_isspol'],
                    $id,
                ]);
            } else {
                $sql = 'INSERT INTO insumos (
                            nombre, categoria, codigo_issfa, codigo_isspol, codigo_iess, codigo_msp,
                            producto_issfa, es_medicamento, precio_base, iva_15, gestion_10, precio_total, precio_isspol
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    $payload['nombre'],
                    $payload['categoria'],
                    $payload['codigo_issfa'],
                    $payload['codigo_isspol'],
                    $payload['codigo_iess'],
                    $payload['codigo_msp'],
                    $payload['producto_issfa'],
                    $payload['es_medicamento'],
                    $payload['precio_base'],
                    $payload['iva_15'],
                    $payload['gestion_10'],
                    $payload['precio_total'],
                    $payload['precio_isspol'],
                ]);

                $id = (int) $this->pdo->lastInsertId();
            }

            return [
                'success' => true,
                'message' => 'Insumo guardado correctamente.',
                'id' => $id,
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Error al guardar el insumo: ' . $e->getMessage(),
            ];
        }
    }

    public function guardarMedicamento(array $payload): array
    {
        foreach (['medicamento', 'via_administracion'] as $campo) {
            if (empty($payload[$campo])) {
                return [
                    'success' => false,
                    'message' => "El campo '{$campo}' es obligatorio.",
                ];
            }
        }

        $nombre = trim((string) $payload['medicamento']);
        $via = trim((string) $payload['via_administracion']);
        $id = isset($payload['id']) && $payload['id'] !== '' ? (int) $payload['id'] : null;

        try {
            if ($id) {
                $stmt = $this->pdo->prepare('UPDATE medicamentos SET medicamento = ?, via_administracion = ? WHERE id = ?');
                $stmt->execute([$nombre, $via, $id]);
            } else {
                $stmt = $this->pdo->prepare('INSERT INTO medicamentos (medicamento, via_administracion) VALUES (?, ?)');
                $stmt->execute([$nombre, $via]);
                $id = (int) $this->pdo->lastInsertId();
            }

            return [
                'success' => true,
                'message' => 'Medicamento guardado correctamente.',
                'id' => $id,
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Error al guardar el medicamento: ' . $e->getMessage(),
            ];
        }
    }

    public function eliminarMedicamento(int $id): array
    {
        if ($id <= 0) {
            return [
                'success' => false,
                'message' => 'Identificador de medicamento inválido.',
            ];
        }

        try {
            $stmt = $this->pdo->prepare('DELETE FROM medicamentos WHERE id = ?');
            $stmt->execute([$id]);

            return [
                'success' => true,
                'message' => 'Medicamento eliminado correctamente.',
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Error al eliminar el medicamento: ' . $e->getMessage(),
            ];
        }
    }
}
