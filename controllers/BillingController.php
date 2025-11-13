<?php

namespace Controllers;

use PDO;
use Models\ProtocoloModel;
use Services\BillingService;
use Services\PreviewService;
use Services\ExportService;

class BillingController
{
    /** @var PDO */
    private $db;

    /** @var ProtocoloModel */
    private $protocoloModel;

    /** @var BillingService */
    private $billingService;

    /** @var PreviewService */
    private $previewService;

    /** @var ExportService */
    private $exportService;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
        $this->protocoloModel = new ProtocoloModel($pdo);
        $this->billingService = new BillingService($pdo);
        $this->previewService = new PreviewService($pdo);
        $this->exportService = new ExportService($pdo);
    }

    // 👉 Delegación limpia
    /**
     * @param array $data
     * @return array
     */
    public function guardar(array $data)
    {
        return $this->billingService->guardar($data);
    }

    /**
     * @param string $formId
     * @return array|null
     */
    public function obtenerDatos($formId)
    {
        return $this->billingService->obtenerDatos($formId);
    }

    /**
     * @param string|null $mes
     * @return array
     */
    public function obtenerResumenConsolidado($mes = null)
    {
        return $this->billingService->obtenerResumenConsolidado($mes);
    }

    /**
     * @param string $codigo
     * @return float|null
     */
    public function obtenerValorAnestesia($codigo)
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
    /**
     * @param string $formIdParam
     * @param string $grupoAfiliacion
     * @param string $modo
     * @return void
     */
    public function generarExcel($formIdParam, $grupoAfiliacion, $modo = 'individual')
    {
        $formIds = array_map('trim', explode(',', $formIdParam));

        if (count($formIds) > 1) {
            // Consolidado
            $datosMultiples = [];
            foreach ($formIds as $formId) {
                $datos = $this->obtenerDatos($formId);
                if ($datos) {
                    $datosMultiples[] = $datos;
                }
            }

            if (empty($datosMultiples)) {
                throw new \RuntimeException("❌ No se encontraron datos para los form_id proporcionados");
            }

            $this->exportService->generarExcelMultiple($datosMultiples, $grupoAfiliacion);
        } else {
            // Individual
            $formId = $formIds[0];
            $datos = $this->obtenerDatos($formId);
            if (!$datos) {
                throw new \RuntimeException("❌ No se encontraron datos para form_id $formId");
            }
            $this->exportService->generarExcel($datos, $formId, $grupoAfiliacion, 'individual');
        }
    }

    /**
     * @param string $formId
     * @param string $destino
     * @param string $grupoAfiliacion
     * @return bool
     */
    public function generarExcelAArchivo($formId, $destino, $grupoAfiliacion)
    {
        $datos = $this->obtenerDatos($formId);
        return $this->exportService->generarExcelAArchivo($datos, $formId, $destino, $grupoAfiliacion);
    }

    /**
     * @param string $mes
     * @param string $grupoAfiliacion
     * @return void
     */
    public function exportarPlanillasPorMes($mes, $grupoAfiliacion)
    {
        $this->exportService->exportarPlanillasPorMes(
            $mes,
            $grupoAfiliacion,
            function ($formId) {
                return $this->obtenerDatos($formId);
            }
        );
    }

    /**
     * @param string|null $mes
     * @return array
     */
    public function obtenerFacturasDisponibles($mes = null)
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
            'contribuyente voluntario' => 'SV',
            'conyuge' => 'CY',
            'conyuge pensionista' => 'CJ',
            'seguro campesino' => 'CA',
            'seguro campesino jubilado' => 'CJ',
            'seguro general' => 'SG',
            'seguro general jubilado' => 'JU',
            'seguro general por montepio' => 'MO',
            'seguro general tiempo parcial' => 'SG'
        ];
        $normalizado = strtolower(trim($afiliacion));
        return $mapa[$normalizado] ?? strtoupper($afiliacion);
    }

    /**
     * @return array
     */
    public function obtenerFormIdsFacturados()
    {
        $stmt = $this->db->query("SELECT form_id FROM billing_main");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * @param string $formId
     * @return mixed
     */
    public function obtenerBillingIdPorFormId($formId)
    {
        $stmt = $this->db->prepare("SELECT id FROM billing_main WHERE form_id = ?");
        $stmt->execute([$formId]);
        return $stmt->fetchColumn();
    }

    /**
     * @param string $formId
     * @return bool
     */
    public function esCirugiaPorFormId($formId)
    {
        $stmt = $this->db->prepare("SELECT 1 FROM protocolo_data WHERE form_id = ? LIMIT 1");
        $stmt->execute([$formId]);
        return $stmt->fetchColumn() !== false;
    }

    /**
     * @param array $formIds
     * @return array
     */
    public function obtenerFechasIngresoYEgreso(array $formIds)
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

        usort($fechas, function ($a, $b) {
            return strtotime($a) <=> strtotime($b);
        });

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
            'seguro general por montepio', 'seguro general tiempo parcial', 'iess', 'hijos dependientes'
        ];
        $afiliacionesList = implode(', ', array_map(function ($a) {
            return $this->db->quote($a);
        }, $afiliaciones));

        // 1. Procedimientos quirúrgicos (con protocolo_data)
        $queryQuirurgicos = "
            SELECT 
                pr.form_id,
                pr.hc_number,
                pd.fecha_inicio AS fecha,
                pd.status,
                pd.membrete,
                pd.lateralidad,
                pr.procedimiento_proyectado AS nombre_procedimiento,
                pa.afiliacion,
                pa.fname,
                pa.mname,
                pa.lname,
                pa.lname2
            FROM protocolo_data pd
            JOIN procedimiento_proyectado pr ON pr.form_id = pd.form_id
            JOIN patient_data pa ON pa.hc_number = pd.hc_number
            WHERE NOT EXISTS (
                SELECT 1 FROM billing_main bm WHERE bm.form_id = pd.form_id
            )
            AND pd.fecha_inicio >= '2024-12-01'
            AND pa.afiliacion COLLATE utf8mb4_unicode_ci IN ($afiliacionesList)
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
            JOIN patient_data pa ON pa.hc_number = pr.hc_number
            WHERE NOT EXISTS (
                SELECT 1 FROM billing_main bm WHERE bm.form_id = pr.form_id
            )
            AND NOT EXISTS (
                SELECT 1 FROM protocolo_data pd WHERE pd.form_id = pr.form_id
            )
            AND pr.fecha >= '2024-12-01'
            AND pa.afiliacion COLLATE utf8mb4_unicode_ci IN ($afiliacionesList)
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
            $tieneProtocolo = isset($r['status']);

            if ($tieneProtocolo) {
                if ((int)$r['status'] === 1) {
                    $quirurgicosRevisados[] = $r;
                } else {
                    $quirurgicosNoRevisados[] = $r;
                }
            } else {
                $noQuirurgicos[] = $r;
            }
        }
        // Ordenar por fecha descendente
        $sortByFecha = function ($a, $b) {
            $fechaA = isset($a['fecha']) ? $a['fecha'] : '1970-01-01';
            $fechaB = isset($b['fecha']) ? $b['fecha'] : '1970-01-01';

            return strtotime($fechaB) <=> strtotime($fechaA);
        };

        usort($quirurgicosRevisados, $sortByFecha);
        usort($quirurgicosNoRevisados, $sortByFecha);
        usort($noQuirurgicos, $sortByFecha);

        return [
            'quirurgicos_revisados' => $quirurgicosRevisados,
            'quirurgicos_no_revisados' => $quirurgicosNoRevisados,
            'no_quirurgicos' => $noQuirurgicos
        ];
    }

    /**
     * @param string $formId
     * @param string $hcNumber
     * @return array
     */
    public function prepararPreviewFacturacion($formId, $hcNumber)
    {
        return $this->previewService->prepararPreviewFacturacion($formId, $hcNumber);
    }

}
