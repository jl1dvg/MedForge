<?php

namespace App\Modules\Billing\Services;

use PDO;

class BillingSoamAdapter
{
    private BillingInformeDataService $dataService;

    public function __construct(private readonly PDO $db)
    {
        $this->dataService = new BillingInformeDataService($db, new BillingInformePacienteService($db));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function obtenerDatos(string $formId): ?array
    {
        return $this->dataService->obtenerDatos($formId);
    }

    /**
     * @param array<int, string> $formIds
     * @return array{ingreso:string|null,egreso:string|null}
     */
    public function obtenerFechasIngresoYEgreso(array $formIds): array
    {
        $fechas = [];

        foreach ($formIds as $formId) {
            $formId = trim((string) $formId);
            if ($formId === '') {
                continue;
            }

            $stmt = $this->db->prepare('SELECT fecha_inicio FROM protocolo_data WHERE form_id = ?');
            $stmt->execute([$formId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $fechaInicio = (string) ($row['fecha_inicio'] ?? '');
            if ($fechaInicio !== '' && $fechaInicio !== '0000-00-00') {
                $fechas[] = $fechaInicio;
                continue;
            }

            $stmt = $this->db->prepare('SELECT fecha FROM procedimiento_proyectado WHERE form_id = ?');
            $stmt->execute([$formId]);
            while ($procRow = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $fecha = (string) ($procRow['fecha'] ?? '');
                if ($fecha !== '' && $fecha !== '0000-00-00') {
                    $fechas[] = $fecha;
                }
            }
        }

        if ($fechas === []) {
            return ['ingreso' => null, 'egreso' => null];
        }

        usort($fechas, static fn(string $a, string $b): int => strtotime($a) <=> strtotime($b));

        return [
            'ingreso' => $fechas[0],
            'egreso' => end($fechas) ?: $fechas[0],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function obtenerDerivacionPorFormId(string $formId): array
    {
        return $this->dataService->obtenerDerivacionPorFormId($formId);
    }

    public function abreviarAfiliacion(string $afiliacion): string
    {
        $mapa = [
            'contribuyente voluntario' => 'SV',
            'conyuge' => 'CY',
            'conyuge pensionista' => 'CJ',
            'seguro campesino' => 'CA',
            'seguro campesino jubilado' => 'JC',
            'seguro general' => 'SG',
            'seguro general jubilado' => 'JU',
            'seguro general por montepio' => 'MO',
            'seguro general tiempo parcial' => 'SG',
        ];

        $normalizado = strtolower(trim($afiliacion));
        return $mapa[$normalizado] ?? strtoupper($afiliacion);
    }

    public function esCirugiaPorFormId(string $formId): bool
    {
        return $this->dataService->esCirugiaPorFormId($formId);
    }

    public function obtenerValorAnestesia(string $codigo): ?float
    {
        return $this->dataService->obtenerValorAnestesia($codigo);
    }
}

