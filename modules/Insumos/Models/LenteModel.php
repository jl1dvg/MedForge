<?php

namespace Modules\Insumos\Models;

use PDO;

class LenteModel
{
    private PDO $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    public function listar(): array
    {
        $stmt = $this->db->query("SELECT id, marca, modelo, nombre, poder, observacion, rango_desde, rango_hasta, rango_paso, rango_inicio_incremento, rango_texto, constante_a, constante_a_us, tipo_optico FROM lentes_catalogo ORDER BY marca, modelo, nombre");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function guardar(array $data): array
    {
        $id = isset($data['id']) ? (int)$data['id'] : null;
        $marca = trim($data['marca'] ?? '');
        $modelo = trim($data['modelo'] ?? '');
        $nombre = trim($data['nombre'] ?? '');
        $poder = trim($data['poder'] ?? '');
        $observacion = trim($data['observacion'] ?? '');
        $rangoDesde = $this->toDecimal($data['rango_desde'] ?? null);
        $rangoHasta = $this->toDecimal($data['rango_hasta'] ?? null);
        $rangoPaso = $this->toDecimal($data['rango_paso'] ?? null);
        $rangoInicioInc = $this->toDecimal($data['rango_inicio_incremento'] ?? null);
        $rangoTexto = trim($data['rango_texto'] ?? '');
        $constanteA = $this->toDecimal($data['constante_a'] ?? null);
        $constanteAUs = $this->toDecimal($data['constante_a_us'] ?? null);
        $tipoOptico = trim($data['tipo_optico'] ?? '');

        if ($marca === '' || $modelo === '' || $nombre === '') {
            return ['success' => false, 'message' => 'Marca, modelo y nombre son obligatorios'];
        }

        if ($tipoOptico && !in_array($tipoOptico, ['una_pieza', 'multipieza'], true)) {
            $tipoOptico = null;
        }

        if ($id) {
            $stmt = $this->db->prepare("UPDATE lentes_catalogo 
                SET marca = ?, modelo = ?, nombre = ?, poder = ?, observacion = ?, 
                    rango_desde = ?, rango_hasta = ?, rango_paso = ?, rango_inicio_incremento = ?, rango_texto = ?,
                    constante_a = ?, constante_a_us = ?, tipo_optico = ?
                WHERE id = ?");
            $stmt->execute([
                $marca, $modelo, $nombre, $poder ?: null, $observacion ?: null,
                $rangoDesde, $rangoHasta, $rangoPaso, $rangoInicioInc, $rangoTexto ?: null,
                $constanteA, $constanteAUs, $tipoOptico ?: null,
                $id
            ]);
        } else {
            $stmt = $this->db->prepare("INSERT INTO lentes_catalogo 
                (marca, modelo, nombre, poder, observacion, rango_desde, rango_hasta, rango_paso, rango_inicio_incremento, rango_texto, constante_a, constante_a_us, tipo_optico) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $marca, $modelo, $nombre, $poder ?: null, $observacion ?: null,
                $rangoDesde, $rangoHasta, $rangoPaso, $rangoInicioInc, $rangoTexto ?: null,
                $constanteA, $constanteAUs, $tipoOptico ?: null
            ]);
            $id = (int)$this->db->lastInsertId();
        }

        return ['success' => true, 'id' => $id];
    }

    private function toDecimal($value): ?float
    {
        if ($value === '' || $value === null) {
            return null;
        }
        $v = str_replace(',', '.', (string)$value);
        return is_numeric($v) ? (float)$v : null;
    }

    public function eliminar(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM lentes_catalogo WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
