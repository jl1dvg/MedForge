<?php

namespace Controllers;

use PDO;
use Models\ProtocoloModel;
use Services\BillingService;
use Services\PreviewService;
use Services\ExportService;

class BillingController
{
    private $db;
    private ProtocoloModel $protocoloModel;
    private BillingService $billingService;
    private PreviewService $previewService;
    private ExportService $exportService;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
        $this->protocoloModel = new ProtocoloModel($pdo);
        $this->billingService = new BillingService($pdo);
        $this->previewService = new PreviewService($pdo);
        $this->exportService = new ExportService($pdo);
    }

    // 👉 Delegación limpia
    public function guardar(array $data): array
    {
        return $this->billingService->guardar($data);
    }

    public function obtenerDatos(string $formId): ?array
    {
        return $this->billingService->obtenerDatos($formId);
    }

    public function obtenerValorAnestesia(string $codigo): ?float
    {
        $stmt = $this->db->prepare("SELECT anestesia_nivel3 FROM tarifario_2014 WHERE codigo = :codigo OR codigo = :codigo_sin_0 LIMIT 1");
        $stmt->execute([
            'codigo' => $codigo,
            'codigo_sin_0' => ltrim($codigo, '0')
        ]);

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        return $resultado ? (float)$resultado['anestesia_nivel3'] : null;
    }

    /**
     * Resuelve la ruta del archivo de plantilla para generar Excel según el grupo de afiliación.
     * Intenta varias convenciones de nombre para tolerar diferencias.
     */
    public function generarExcel(string $formId, string $grupoAfiliacion, string $modo = 'individual'): void
    {
        $datos = $this->obtenerDatos($formId);
        $this->exportService->generarExcel($datos, $formId, $grupoAfiliacion, $modo);
    }

    public function generarExcelAArchivo(string $formId, string $destino, string $grupoAfiliacion): bool
    {
        $datos = $this->obtenerDatos($formId);
        return $this->exportService->generarExcelAArchivo($datos, $formId, $destino, $grupoAfiliacion);
    }

    public function exportarPlanillasPorMes(string $mes, string $grupoAfiliacion): void
    {
        $this->exportService->exportarPlanillasPorMes($mes, $grupoAfiliacion, fn($formId) => $this->obtenerDatos($formId));
    }

    public function obtenerFacturasDisponibles(?string $mes = null): array
    {
        $query = "
            SELECT 
                bm.id, 
                bm.form_id, 
                bm.hc_number, 
                COALESCE(pd.fecha_inicio, pp.fecha) AS fecha_ordenada
            FROM billing_main bm
            LEFT JOIN protocolo_data pd ON bm.form_id = pd.form_id
            LEFT JOIN procedimiento_proyectado pp ON bm.form_id = pp.form_id
        ";

        if ($mes) {
            $startDate = $mes . '-01';
            $endDate = date('Y-m-t', strtotime($startDate));
            $query .= " WHERE COALESCE(pd.fecha_inicio, pp.fecha) BETWEEN :startDate AND :endDate";
        }

        $query .= " ORDER BY fecha_ordenada DESC";

        $stmt = $this->db->prepare($query);
        if ($mes) {
            $stmt->execute(['startDate' => $startDate, 'endDate' => $endDate]);
        } else {
            $stmt->execute();
        }

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }


    public function obtenerDerivacionPorFormId($formId)
    {
        $stmt = $this->db->prepare("SELECT * FROM derivaciones_form_id WHERE form_id = ?");
        $stmt->execute([$formId]);
        return $stmt->fetch(PDO::FETCH_ASSOC); // Devuelve un array con todas las columnas
    }

    function abreviarAfiliacion($afiliacion)
    {
        $mapa = [
            'contribuyente voluntario' => 'CV',
            'conyuge' => 'CY',
            'conyuge pensionista' => 'CP',
            'seguro campesino' => 'SC',
            'seguro campesino jubilado' => 'CJ',
            'seguro general' => 'SG',
            'seguro general jubilado' => 'JU',
            'seguro general por montepio' => 'MO',
            'seguro general tiempo parcial' => 'TP'
        ];
        $normalizado = strtolower(trim($afiliacion));
        return $mapa[$normalizado] ?? strtoupper($afiliacion);
    }

    public function obtenerFormIdsFacturados(): array
    {
        $stmt = $this->db->query("SELECT form_id FROM billing_main");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function obtenerBillingIdPorFormId($formId)
    {
        $stmt = $this->db->prepare("SELECT id FROM billing_main WHERE form_id = ?");
        $stmt->execute([$formId]);
        return $stmt->fetchColumn();
    }

    public function esCirugiaPorFormId($formId): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM protocolo_data WHERE form_id = ? LIMIT 1");
        $stmt->execute([$formId]);
        return $stmt->fetchColumn() !== false;
    }

    public function obtenerFechasIngresoYEgreso(array $formIds): array
    {
        $fechas = [];

        foreach ($formIds as $formId) {
            // 1. Buscar en protocolo_data
            $stmt = $this->db->prepare("SELECT fecha_inicio FROM protocolo_data WHERE form_id = ?");
            $stmt->execute([$formId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!empty($row['fecha_inicio']) && $row['fecha_inicio'] !== '0000-00-00') {
                $fechas[] = $row['fecha_inicio'];
                continue;
            }

            // 2. Si no hay en protocolo_data, buscar en procedimiento_proyectado
            $stmt = $this->db->prepare("SELECT fecha FROM procedimiento_proyectado WHERE form_id = ?");
            $stmt->execute([$formId]);
            while ($procRow = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($procRow['fecha']) && $procRow['fecha'] !== '0000-00-00') {
                    $fechas[] = $procRow['fecha'];
                }
            }
        }

        if (empty($fechas)) {
            return ['ingreso' => null, 'egreso' => null];
        }

        usort($fechas, fn($a, $b) => strtotime($a) <=> strtotime($b));

        return [
            'ingreso' => $fechas[0],
            'egreso' => end($fechas),
        ];
    }

    public function procedimientosNoFacturadosClasificados()
    {
        // Unifica quirúrgicos (con protocolo_data) y no quirúrgicos (sin protocolo_data)
        $afiliaciones = [
            'contribuyente voluntario', 'conyuge', 'conyuge pensionista', 'seguro campesino',
            'seguro campesino jubilado', 'seguro general', 'seguro general jubilado',
            'seguro general por montepío', 'seguro general tiempo parcial', 'iess'
        ];
        // 1. Procedimientos quirúrgicos (con protocolo_data)
        $queryQuirurgicos = "
            SELECT 
                pr.form_id,
                pr.hc_number,
                pd.fecha_inicio AS fecha,
                pd.status,
                pr.procedimiento_proyectado AS nombre_procedimiento,
                pa.afiliacion,
                pa.fname,
                pa.mname,
                pa.lname,
                pa.lname2
            FROM protocolo_data pd
            JOIN procedimiento_proyectado pr ON pr.form_id = pd.form_id
            JOIN patient_data pa ON pa.hc_number = pd.hc_number
            WHERE pd.form_id NOT IN (
                SELECT form_id FROM billing_main
            )
            AND pd.fecha_inicio >= '2024-11-01'
            AND LOWER(pa.afiliacion) IN (" . implode(', ', array_map(fn($a) => $this->db->quote($a), $afiliaciones)) . ")
        ";
        // 2. Procedimientos NO quirúrgicos (sin protocolo_data)
        $queryNoQuirurgicos = "
            SELECT 
                pr.form_id,
                pr.hc_number,
                pr.fecha AS fecha,
                pr.procedimiento_proyectado AS nombre_procedimiento,
                pa.afiliacion,
                pa.fname,
                pa.mname,
                pa.lname,
                pa.lname2
            FROM procedimiento_proyectado pr
            LEFT JOIN protocolo_data pd ON pd.form_id = pr.form_id
            JOIN patient_data pa ON pa.hc_number = pr.hc_number
            WHERE pr.form_id NOT IN (
                SELECT form_id FROM billing_main
            )
            AND pd.form_id IS NULL
            AND pr.fecha >= '2024-11-01'
            AND LOWER(pa.afiliacion) IN (" . implode(', ', array_map(fn($a) => $this->db->quote($a), $afiliaciones)) . ")
        ";
        // Ejecutar ambos y unificar
        $quirurgicosRaw = $this->db->query($queryQuirurgicos)->fetchAll(PDO::FETCH_ASSOC);
        $noQuirurgicosRaw = $this->db->query($queryNoQuirurgicos)->fetchAll(PDO::FETCH_ASSOC);
        $todos = array_merge($quirurgicosRaw, $noQuirurgicosRaw);

        // Clasificar quirúrgicos y no quirúrgicos
        $quirurgicosRevisados = [];
        $quirurgicosNoRevisados = [];
        $noQuirurgicos = [];
        foreach ($todos as $r) {
            if (stripos(trim($r['nombre_procedimiento']), 'CIRUGIAS -') === 0) {
                if ((int)($r['status'] ?? 0) === 1) {
                    $quirurgicosRevisados[] = $r;
                } else {
                    $quirurgicosNoRevisados[] = $r;
                }
            } else {
                $noQuirurgicos[] = $r;
            }
        }
        // Ordenar por fecha descendente
        $sortByFecha = fn($a, $b) => strtotime($b['fecha'] ?? '1970-01-01') <=> strtotime($a['fecha'] ?? '1970-01-01');

        usort($quirurgicosRevisados, $sortByFecha);
        usort($quirurgicosNoRevisados, $sortByFecha);
        usort($noQuirurgicos, $sortByFecha);

        return [
            'quirurgicos_revisados' => $quirurgicosRevisados,
            'quirurgicos_no_revisados' => $quirurgicosNoRevisados,
            'no_quirurgicos' => $noQuirurgicos
        ];
    }

    public function prepararPreviewFacturacion(string $formId, string $hcNumber): array
    {
        return $this->previewService->prepararPreviewFacturacion($formId, $hcNumber);
    }

}