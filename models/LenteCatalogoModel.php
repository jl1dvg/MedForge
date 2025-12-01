<?php

namespace Models;

use PDO;

class LenteCatalogoModel
{
    private PDO $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    public function listar(): array
    {
        $stmt = $this->db->query("SELECT id, marca, modelo, nombre, poder, observacion FROM lentes_catalogo ORDER BY marca, modelo, nombre");
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

        if ($marca === '' || $modelo === '' || $nombre === '') {
            return ['success' => false, 'message' => 'Marca, modelo y nombre son obligatorios'];
        }

        if ($id) {
            $stmt = $this->db->prepare("UPDATE lentes_catalogo SET marca = ?, modelo = ?, nombre = ?, poder = ?, observacion = ? WHERE id = ?");
            $stmt->execute([$marca, $modelo, $nombre, $poder ?: null, $observacion ?: null, $id]);
        } else {
            $stmt = $this->db->prepare("INSERT INTO lentes_catalogo (marca, modelo, nombre, poder, observacion) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$marca, $modelo, $nombre, $poder ?: null, $observacion ?: null]);
            $id = (int)$this->db->lastInsertId();
        }

        return ['success' => true, 'id' => $id];
    }

    public function eliminar(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM lentes_catalogo WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
