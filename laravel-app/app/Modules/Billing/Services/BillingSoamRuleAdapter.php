<?php

namespace App\Modules\Billing\Services;

use PDO;

class BillingSoamRuleAdapter
{
    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @param array<string, mixed> $contexto
     * @return array<int, array<string, mixed>>
     */
    public function evaluar(array $contexto): array
    {
        $accionesAplicables = [];
        $reglas = $this->obtenerReglasActivas();

        foreach ($reglas as $regla) {
            $reglaId = (int) ($regla['id'] ?? 0);
            if ($reglaId <= 0) {
                continue;
            }

            $condiciones = $this->obtenerCondiciones($reglaId);
            $acciones = $this->obtenerAcciones($reglaId);

            $cumple = true;
            foreach ($condiciones as $condicion) {
                if (!$this->cumpleCondicion($contexto, $condicion)) {
                    $cumple = false;
                    break;
                }
            }

            if (!$cumple) {
                continue;
            }

            foreach ($acciones as $accion) {
                $accionesAplicables[] = [
                    'regla' => (string) ($regla['nombre'] ?? ''),
                    'tipo' => (string) ($accion['tipo'] ?? ''),
                    'parametro' => (string) ($accion['parametro'] ?? ''),
                ];
            }
        }

        return $accionesAplicables;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function obtenerReglasActivas(): array
    {
        $stmt = $this->db->query('SELECT * FROM reglas WHERE activa = 1');
        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function obtenerCondiciones(int $reglaId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM condiciones WHERE regla_id = ?');
        $stmt->execute([$reglaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function obtenerAcciones(int $reglaId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM acciones WHERE regla_id = ?');
        $stmt->execute([$reglaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string, mixed> $contexto
     * @param array<string, mixed> $condicion
     */
    private function cumpleCondicion(array $contexto, array $condicion): bool
    {
        $campo = (string) ($condicion['campo'] ?? '');
        $valorPaciente = strtolower(trim((string) ($contexto[$campo] ?? '')));
        $valorCondicion = strtolower(trim((string) ($condicion['valor'] ?? '')));
        $operador = (string) ($condicion['operador'] ?? '');

        return match ($operador) {
            '=' => $valorPaciente === $valorCondicion,
            'LIKE' => str_contains($valorPaciente, str_replace('%', '', $valorCondicion)),
            'IN' => in_array($valorPaciente, array_map('trim', explode(',', $valorCondicion)), true),
            default => false,
        };
    }
}

