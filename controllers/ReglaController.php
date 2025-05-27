<?php

namespace Controllers;

use PDO;

class ReglaController
{
    private PDO $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    public function crearRegla(array $data): int
    {
        $stmt = $this->db->prepare("INSERT INTO reglas (nombre, tipo, descripcion) VALUES (?, ?, ?)");
        $stmt->execute([$data['nombre'], $data['tipo'], $data['descripcion']]);
        return (int)$this->db->lastInsertId();
    }

    public function agregarCondiciones(int $reglaId, array $condiciones): void
    {
        $stmt = $this->db->prepare("INSERT INTO condiciones (regla_id, campo, operador, valor) VALUES (?, ?, ?, ?)");
        foreach ($condiciones as $cond) {
            $stmt->execute([$reglaId, $cond['campo'], $cond['operador'], $cond['valor']]);
        }
    }

    public function agregarAcciones(int $reglaId, array $acciones): void
    {
        $stmt = $this->db->prepare("INSERT INTO acciones (regla_id, tipo, parametro) VALUES (?, ?, ?)");
        foreach ($acciones as $acc) {
            $stmt->execute([$reglaId, $acc['tipo'], $acc['parametro']]);
        }
    }

    public function obtenerReglasActivas(): array
    {
        $stmt = $this->db->query("SELECT * FROM reglas WHERE activa = 1");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerCondiciones(int $reglaId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM condiciones WHERE regla_id = ?");
        $stmt->execute([$reglaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenerAcciones(int $reglaId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM acciones WHERE regla_id = ?");
        $stmt->execute([$reglaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function cumpleCondicion(array $contexto, array $condicion): bool
    {
        $campo = $condicion['campo'];
        $valorPaciente = strtolower(trim($contexto[$campo] ?? ''));
        $valorCondicion = strtolower(trim($condicion['valor']));
        $operador = $condicion['operador'];

        return match ($operador) {
            '=' => $valorPaciente === $valorCondicion,
            'LIKE' => strpos($valorPaciente, str_replace('%', '', $valorCondicion)) !== false,
            'IN' => in_array($valorPaciente, array_map('trim', explode(',', $valorCondicion))),
            default => false,
        };
    }

    public function evaluar(array $contexto): array
    {
        $accionesAplicables = [];
        $reglas = $this->obtenerReglasActivas();

        foreach ($reglas as $regla) {
            $condiciones = $this->obtenerCondiciones($regla['id']);
            $acciones = $this->obtenerAcciones($regla['id']);

            $cumple = true;
            foreach ($condiciones as $condicion) {
                if (!$this->cumpleCondicion($contexto, $condicion)) {
                    $cumple = false;
                    break;
                }
            }

            if ($cumple) {
                foreach ($acciones as $accion) {
                    $accionesAplicables[] = [
                        'regla' => $regla['nombre'],
                        'tipo' => $accion['tipo'],
                        'parametro' => $accion['parametro'],
                    ];
                }
            }
        }

        return $accionesAplicables;
    }

    public function obtenerReglaPorId(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM reglas WHERE id = ?");
        $stmt->execute([$id]);
        $regla = $stmt->fetch(PDO::FETCH_ASSOC);

        return $regla ?: null;
    }

    public function actualizarRegla(int $id, array $data): void
    {
        $stmt = $this->db->prepare("UPDATE reglas SET nombre = ?, tipo = ?, descripcion = ? WHERE id = ?");
        $stmt->execute([$data['nombre'], $data['tipo'], $data['descripcion'], $id]);
    }
}