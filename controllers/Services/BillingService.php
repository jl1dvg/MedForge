<?php

namespace Services;

use PDO;
use Exception;
use Models\BillingMainModel;
use Models\BillingProcedimientosModel;
use Models\BillingDerechosModel;
use Models\BillingInsumosModel;
use Models\BillingOxigenoModel;
use Models\BillingAnestesiaModel;
use Models\ProtocoloModel;
use Helpers\FacturacionHelper;
use Modules\Pacientes\Services\PacienteService;

class BillingService
{
    private PDO $db;
    private BillingMainModel $billingMainModel;
    private BillingProcedimientosModel $billingProcedimientosModel;
    private BillingDerechosModel $billingDerechosModel;
    private BillingInsumosModel $billingInsumosModel;
    private BillingOxigenoModel $billingOxigenoModel;
    private BillingAnestesiaModel $billingAnestesiaModel;
    private ProtocoloModel $protocoloModel;
    private PacienteService $pacienteService;
    /** @var array<string, bool>|null */
    private ?array $medicamentoNombreLookup = null;
    /** @var array<int, string>|null */
    private ?array $medicamentoNombreList = null;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
        $this->billingMainModel = new BillingMainModel($pdo);
        $this->billingProcedimientosModel = new BillingProcedimientosModel($pdo);
        $this->billingDerechosModel = new BillingDerechosModel($pdo);
        $this->billingInsumosModel = new BillingInsumosModel($pdo);
        $this->billingOxigenoModel = new BillingOxigenoModel($pdo);
        $this->billingAnestesiaModel = new BillingAnestesiaModel($pdo);
        $this->protocoloModel = new ProtocoloModel($pdo);
        $this->pacienteService = new PacienteService($pdo);
    }

    /**
     * Guarda todos los datos de facturación en la base.
     *
     * @param array $data Datos del formulario (procedimientos, insumos, etc.)
     * @return array Resultado con éxito o error
     */
    public function guardar(array $data): array
    {
        try {
            $this->db->beginTransaction();

            $userId = isset($data['user_id']) ? (int)$data['user_id'] : (int)($_SESSION['user_id'] ?? 0);

            // Billing main
            $billing = $this->billingMainModel->findByFormId($data['form_id']);
            if ($billing) {
                $billingId = $billing['id'];
                $this->borrarDetalles($billingId);
                $this->billingMainModel->update($data['hcNumber'], $billingId);
                $this->billingMainModel->assignFacturador($billingId, $userId ?: null);
            } else {
                $billingId = $this->billingMainModel->insert($data['hcNumber'], $data['form_id'], $userId ?: null);
            }

            // Actualizar fecha de creación si existe en protocolo
            if (!empty($data['fecha_inicio'])) {
                $this->billingMainModel->updateFechaCreacion($billingId, $data['fecha_inicio']);
            }

            // Procedimientos
            foreach ($data['procedimientos'] ?? [] as $p) {
                $this->billingProcedimientosModel->insertar($billingId, $p);
            }

            // Derechos
            foreach ($data['derechos'] ?? [] as $d) {
                $this->billingDerechosModel->insertar($billingId, $d);
            }

            // Insumos
            foreach ($data['insumos'] ?? [] as $i) {
                $this->billingInsumosModel->insertar($billingId, $i);
            }

            // Oxígeno
            foreach ($data['oxigeno'] ?? [] as $o) {
                $this->billingOxigenoModel->insertar($billingId, $o);
            }

            // Anestesia
            foreach ($data['anestesia'] ?? [] as $a) {
                $this->billingAnestesiaModel->insertar($billingId, $a);
            }

            $this->db->commit();
            return ["success" => true, "message" => "Billing guardado correctamente", "billing_id" => $billingId];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ["success" => false, "message" => "Error al guardar billing: " . $e->getMessage()];
        }
    }

    /**
     * Borra detalles de un billing antes de volver a insertarlos.
     */
    private function borrarDetalles(int $billingId): void
    {
        $tablas = [
            'billing_procedimientos',
            'billing_derechos',
            'billing_insumos',
            'billing_oxigeno',
            'billing_anestesia'
        ];

        foreach ($tablas as $tabla) {
            $stmt = $this->db->prepare("DELETE FROM $tabla WHERE billing_id = ?");
            $stmt->execute([$billingId]);
        }
    }

    /**
     * Obtiene todos los datos de facturación asociados a un form_id
     */
    public function obtenerDatos(string $formId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM billing_main WHERE form_id = ?");
        $stmt->execute([$formId]);
        $billing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$billing) return null;

        $billingId = $billing['id'];
        $facturador = $this->resolverFacturador($billing);
        $billing['facturador_id'] = $facturador['id'] ?? null;
        $billing['facturador_nombre'] = $facturador['nombre'] ?? null;

        // Dependencias
        require_once __DIR__ . '/../GuardarProyeccionController.php';
        $guardarProyeccionController = new \Controllers\GuardarProyeccionController($this->db);

        $pacienteInfo = $this->pacienteService->getPatientDetails($billing['hc_number']);
        $formDetails = $this->pacienteService->getDetalleSolicitud($billing['hc_number'], $formId);
        $visita = $guardarProyeccionController->obtenerDatosPacientePorFormId($formId);
        $protocoloExtendido = $this->protocoloModel->obtenerProtocoloTiny($formId, $billing['hc_number']);

        // Detalles de billing
        $procedimientos = $this->billingProcedimientosModel->obtenerPorBillingId($billingId);
        $derechos = $this->billingDerechosModel->obtenerPorBillingId($billingId);
        $insumos = $this->billingInsumosModel->obtenerPorBillingId($billingId);
        $afiliacion = (string)($pacienteInfo['afiliacion'] ?? '');
        $splitInsumos = $this->splitInsumosPorTipo($insumos);
        $insumosConIVA = $splitInsumos['insumos'];
        $medicamentosSinIVA = $this->ajustarMedicamentosPorAfiliacionLista($splitInsumos['medicamentos'], $afiliacion);

        $oxigeno = $this->billingOxigenoModel->obtenerPorBillingId($billingId);
        $anestesia = $this->billingAnestesiaModel->obtenerPorBillingId($billingId);

        if (
            empty($procedimientos)
            || empty($derechos)
            || empty($oxigeno)
            || empty($anestesia)
            || empty($insumosConIVA)
            || empty($medicamentosSinIVA)
        ) {
            $preview = $this->buildPreviewFallbackData($formId, (string)$billing['hc_number'], $protocoloExtendido);

            if (empty($procedimientos)) {
                $procedimientos = $this->normalizePreviewProcedimientos($preview['procedimientos'] ?? []);
            }

            if (empty($derechos)) {
                $derechos = $this->normalizePreviewDerechos($preview['derechos'] ?? []);
            }

            if (empty($oxigeno)) {
                $oxigeno = $this->normalizePreviewCollection($preview['oxigeno'] ?? []);
            }

            if (empty($anestesia)) {
                $anestesia = $this->normalizePreviewCollection($preview['anestesia'] ?? []);
            }

            if (empty($insumosConIVA) || empty($medicamentosSinIVA)) {
                $previewSplit = $this->splitInsumosPorTipo($this->normalizePreviewCollection($preview['insumos'] ?? []));
                $previewMedicamentos = $this->ajustarMedicamentosPorAfiliacionLista($previewSplit['medicamentos'], $afiliacion);

                if (empty($insumosConIVA) && !empty($previewSplit['insumos'])) {
                    $insumosConIVA = $previewSplit['insumos'];
                }

                if (empty($medicamentosSinIVA) && !empty($previewMedicamentos)) {
                    $medicamentosSinIVA = $previewMedicamentos;
                }
            }
        }

        return [
            'billing' => $billing,
            'procedimientos' => $procedimientos,
            'derechos' => $derechos,
            'insumos' => $insumosConIVA,
            'medicamentos' => $medicamentosSinIVA,
            'oxigeno' => $oxigeno,
            'anestesia' => $anestesia,
            'paciente' => $pacienteInfo,
            'visita' => $visita,
            'formulario' => $formDetails,
            'protocoloExtendido' => $protocoloExtendido,
        ];
    }

    private function resolverFacturador(array $billing): ?array
    {
        $userId = !empty($billing['facturado_por']) ? (int)$billing['facturado_por'] : null;
        $formId = trim((string)($billing['form_id'] ?? ''));

        if (!$userId) {
            if ($formId !== '' && $this->esFacturacionImagenAutomatica($formId)) {
                return [
                    'id' => null,
                    'nombre' => 'Imagenes',
                ];
            }
            return null;
        }

        $stmt = $this->db->prepare(
            "SELECT COALESCE(NULLIF(nombre, ''), NULLIF(username, '')) AS nombre FROM users WHERE id = ?"
        );
        $stmt->execute([$userId]);
        $nombre = $stmt->fetchColumn();

        if (!$nombre && $formId !== '' && $this->esFacturacionImagenAutomatica($formId)) {
            return [
                'id' => null,
                'nombre' => 'Imagenes',
            ];
        }

        return [
            'id' => $userId,
            'nombre' => $nombre ?: null,
        ];
    }

    private function esFacturacionImagenAutomatica(string $formId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM imagenes_informes WHERE form_id = ? LIMIT 1');
        $stmt->execute([$formId]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{insumos: array<int, array<string, mixed>>, medicamentos: array<int, array<string, mixed>>}
     */
    private function splitInsumosPorTipo(array $rows): array
    {
        $insumosConIVA = [];
        $medicamentosSinIVA = [];

        foreach ($rows as $insumo) {
            if (!is_array($insumo)) {
                continue;
            }

            $esMedicamento = $insumo['es_medicamento'] ?? null;
            $categoria = strtolower(trim((string)($insumo['categoria'] ?? '')));
            if ($esMedicamento === null) {
                $esMedicamento = isset($insumo['iva']) && (int)$insumo['iva'] === 0 ? 1 : 0;
            } else {
                $esMedicamento = (int)$esMedicamento;
            }

            if ($esMedicamento !== 1 && $this->looksLikeMedicamento($insumo, $categoria)) {
                $esMedicamento = 1;
            }

            if ($esMedicamento === 1) {
                $medicamentosSinIVA[] = $insumo;
                continue;
            }

            $insumosConIVA[] = $insumo;
        }

        return [
            'insumos' => $insumosConIVA,
            'medicamentos' => $medicamentosSinIVA,
        ];
    }

    /**
     * @param array<string, mixed> $insumo
     */
    private function looksLikeMedicamento(array $insumo, string $categoria): bool
    {
        if ($categoria !== '' && (
            str_contains($categoria, 'medic') ||
            str_contains($categoria, 'farm')
        )) {
            return true;
        }

        $nombre = $this->normalizeInventoryName((string)($insumo['nombre'] ?? ''));
        if ($nombre === '') {
            return false;
        }

        $lookup = $this->medicamentoNombreLookup();
        if (isset($lookup[$nombre])) {
            return true;
        }

        foreach ($this->medicamentoNombreList() as $medicamento) {
            if (strlen($medicamento) < 4) {
                continue;
            }
            if (
                str_starts_with($nombre, $medicamento . ' ') ||
                str_starts_with($nombre, $medicamento . '(') ||
                str_contains($nombre, $medicamento . ' liquido') ||
                str_contains($nombre, $medicamento . ' solido')
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, bool>
     */
    private function medicamentoNombreLookup(): array
    {
        if ($this->medicamentoNombreLookup !== null) {
            return $this->medicamentoNombreLookup;
        }

        try {
            $stmt = $this->db->query('SELECT nombre FROM insumos WHERE es_medicamento = 1');
            $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []) : [];
        } catch (\Throwable) {
            $rows = [];
        }

        $lookup = [];
        foreach ($rows as $row) {
            $normalized = $this->normalizeInventoryName((string)$row);
            if ($normalized !== '') {
                $lookup[$normalized] = true;
            }
        }

        $this->medicamentoNombreLookup = $lookup;
        return $lookup;
    }

    /**
     * @return array<int, string>
     */
    private function medicamentoNombreList(): array
    {
        if ($this->medicamentoNombreList !== null) {
            return $this->medicamentoNombreList;
        }

        $list = array_keys($this->medicamentoNombreLookup());
        usort($list, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));
        $this->medicamentoNombreList = $list;
        return $list;
    }

    private function normalizeInventoryName(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        return preg_replace('/[^a-z0-9 ]+/i', '', $value) ?? $value;
    }

    /**
     * @param array<int, array<string, mixed>> $medicamentos
     * @return array<int, array<string, mixed>>
     */
    private function ajustarMedicamentosPorAfiliacionLista(array $medicamentos, string $afiliacion): array
    {
        if (empty($medicamentos)) {
            return [];
        }

        $codigos = [];
        foreach ($medicamentos as $medicamento) {
            $codigo = trim((string)($medicamento['codigo'] ?? ''));
            if ($codigo !== '') {
                $codigos[] = $codigo;
            }
        }
        $codigos = array_values(array_unique($codigos));

        if (empty($codigos)) {
            return $medicamentos;
        }

        try {
            $placeholders = implode(',', array_fill(0, count($codigos), '?'));
            $stmt = $this->db->prepare("SELECT codigo_isspol, codigo_issfa, codigo_msp, codigo_iess, nombre 
                                        FROM insumos WHERE codigo_isspol IN ($placeholders)");
            $stmt->execute($codigos);
            $insumosReferencia = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return $medicamentos;
        }

        $referenciaMap = [];
        foreach ($insumosReferencia as $referencia) {
            $codigoClave = (string)($referencia['codigo_isspol'] ?? '');
            if ($codigoClave !== '') {
                $referenciaMap[$codigoClave] = $referencia;
            }
        }

        foreach ($medicamentos as &$medicamento) {
            $medicamento = $this->ajustarCodigoPorAfiliacion($medicamento, $afiliacion, $referenciaMap);
        }
        unset($medicamento);

        return $medicamentos;
    }

    /**
     * @param array<string, mixed>|null $protocoloExtendido
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildPreviewFallbackData(string $formId, string $hcNumber, ?array $protocoloExtendido): array
    {
        $preview = [
            'procedimientos' => [],
            'insumos' => [],
            'derechos' => [],
            'oxigeno' => [],
            'anestesia' => [],
            'reglas' => [],
        ];

        if ($formId !== '' && $hcNumber !== '') {
            try {
                $previewService = new PreviewService($this->db);
                $previewData = $previewService->prepararPreviewFacturacion($formId, $hcNumber);
                if (is_array($previewData)) {
                    foreach (array_keys($preview) as $key) {
                        if (isset($previewData[$key]) && is_array($previewData[$key])) {
                            $preview[$key] = $previewData[$key];
                        }
                    }
                }
            } catch (\Throwable) {
                // Mantener lectura del informe aunque falle el preview remoto.
            }
        }

        if (empty($preview['procedimientos'])) {
            $preview['procedimientos'] = $this->buildProcedimientosPreviewRows($formId, $protocoloExtendido);
        }

        return $preview;
    }

    /**
     * @param array<int, mixed> $rows
     * @return array<int, array<string, mixed>>
     */
    private function normalizePreviewCollection(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $result[] = $row;
            }
        }

        return $result;
    }

    /**
     * @param array<int, mixed> $rows
     * @return array<int, array<string, mixed>>
     */
    private function normalizePreviewProcedimientos(array $rows): array
    {
        $result = [];
        $seen = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $codigo = trim((string)($row['proc_codigo'] ?? $row['procCodigo'] ?? ''));
            $detalle = trim((string)($row['proc_detalle'] ?? $row['procDetalle'] ?? ''));
            if ($codigo === '' || $detalle === '') {
                continue;
            }

            $key = $codigo . '|' . $detalle;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $precio = $row['proc_precio'] ?? $row['procPrecio'] ?? null;
            $result[] = [
                'proc_codigo' => $codigo,
                'proc_detalle' => $detalle,
                'proc_precio' => $precio !== null ? (float)$precio : $this->obtenerTarifaPorCodigo($codigo),
            ];
        }

        return $result;
    }

    /**
     * @param array<int, mixed> $rows
     * @return array<int, array<string, mixed>>
     */
    private function normalizePreviewDerechos(array $rows): array
    {
        $result = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $codigo = trim((string)($row['codigo'] ?? ''));
            $detalle = trim((string)($row['detalle'] ?? ''));
            if ($codigo === '' || $detalle === '') {
                continue;
            }

            $precioAfiliacion = $row['precio_afiliacion'] ?? $row['precioAfiliacion'] ?? 0;
            $result[] = [
                'codigo' => $codigo,
                'detalle' => $detalle,
                'cantidad' => (float)($row['cantidad'] ?? 1),
                'iva' => (int)($row['iva'] ?? 0),
                'precio_afiliacion' => (float)$precioAfiliacion,
            ];
        }

        return $result;
    }

    /**
     * @param array<string, mixed>|null $protocoloExtendido
     * @return array<int, array<string, mixed>>
     */
    private function buildProcedimientosPreviewRows(string $formId, ?array $protocoloExtendido): array
    {
        $rows = [];
        $seen = [];

        $json = is_array($protocoloExtendido) ? (string) ($protocoloExtendido['procedimientos'] ?? '') : '';
        if ($json !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                foreach ($decoded as $proc) {
                    if (!is_array($proc)) {
                        continue;
                    }

                    [$codigo, $detalle] = $this->parseCodigoDetalle((string) ($proc['procInterno'] ?? ''));
                    if ($codigo === '' || $detalle === '') {
                        continue;
                    }

                    $key = $codigo . '|' . $detalle;
                    if (isset($seen[$key])) {
                        continue;
                    }

                    $seen[$key] = true;
                    $rows[] = [
                        'proc_codigo' => $codigo,
                        'proc_detalle' => $detalle,
                        'proc_precio' => $this->obtenerTarifaPorCodigo($codigo),
                    ];
                }
            }
        }

        if ($rows !== []) {
            return $rows;
        }

        $raw = is_array($protocoloExtendido) ? (string) ($protocoloExtendido['procedimiento_proyectado'] ?? '') : '';
        if ($raw === '') {
            $stmt = $this->db->prepare('SELECT procedimiento_proyectado FROM procedimiento_proyectado WHERE form_id = ? LIMIT 1');
            $stmt->execute([$formId]);
            $raw = (string) ($stmt->fetchColumn() ?: '');
        }

        [$codigo, $detalle] = $this->parseCodigoDetalle($raw);
        if ($codigo === '' || $detalle === '') {
            return $this->buildProcedimientosFromPrefactura($formId);
        }

        return [[
            'proc_codigo' => $codigo,
            'proc_detalle' => $detalle,
            'proc_precio' => $this->obtenerTarifaPorCodigo($codigo),
        ]];
    }

    private function buildProcedimientosFromPrefactura(string $formId): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT prefactura_id
                 FROM prefactura_payload_audit
                 WHERE form_id = ?
                   AND prefactura_id IS NOT NULL
                 ORDER BY received_at DESC, id DESC
                 LIMIT 1"
            );
            $stmt->execute([$formId]);
            $prefacturaId = (int) ($stmt->fetchColumn() ?: 0);
        } catch (\Throwable) {
            return [];
        }

        if ($prefacturaId <= 0) {
            return [];
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT codigo, descripcion, proc_interno, precio_tarifado, precio_base
                 FROM prefactura_detalle_procedimientos
                 WHERE prefactura_id = ?
                 ORDER BY posicion ASC, id ASC"
            );
            $stmt->execute([$prefacturaId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $codigo = trim((string) ($row['codigo'] ?? ''));
            $detalle = trim((string) ($row['descripcion'] ?? ''));

            if ($codigo === '' || $detalle === '') {
                [$codigo, $detalle] = $this->parseCodigoDetalle((string) ($row['proc_interno'] ?? ''));
            }

            if ($codigo === '' || $detalle === '') {
                continue;
            }

            $precio = $row['precio_tarifado'] ?? $row['precio_base'] ?? null;
            $result[] = [
                'proc_codigo' => $codigo,
                'proc_detalle' => $detalle,
                'proc_precio' => $precio !== null ? (float) $precio : $this->obtenerTarifaPorCodigo($codigo),
            ];
        }

        return $result;
    }

    private function parseCodigoDetalle(string $raw): array
    {
        $text = trim($raw);
        if ($text === '') {
            return ['', ''];
        }

        if (preg_match('/-\s*(\d{5,6})\s*-\s*(.+)$/', $text, $matches) === 1) {
            return [trim($matches[1]), trim($matches[2])];
        }

        if (preg_match('/\b(\d{5,6})\b/', $text, $matches) === 1) {
            $codigo = trim($matches[1]);
            $detalle = trim(str_replace($codigo, '', $text));
            $detalle = trim(preg_replace('/\s+/', ' ', $detalle) ?? $detalle);
            return [$codigo, $detalle !== '' ? $detalle : $text];
        }

        return ['', ''];
    }

    private function obtenerTarifaPorCodigo(string $codigo): float
    {
        $stmt = $this->db->prepare('SELECT valor_facturar_nivel3 FROM tarifario_2014 WHERE codigo = ? OR codigo = ? LIMIT 1');
        $stmt->execute([$codigo, ltrim($codigo, '0')]);
        $precio = $stmt->fetchColumn();

        return $precio !== false ? (float) $precio : 0.0;
    }

    public function obtenerResumenConsolidado(?string $mes = null): array
    {
        $query = "
        SELECT 
            bm.form_id,
            bm.hc_number,
            COALESCE(pd.fecha_inicio, pp.fecha) AS fecha_orden,
            CONCAT(pa.fname, ' ', pa.mname, ' ', pa.lname, ' ', pa.lname2) AS paciente,
            pa.afiliacion,
            d.diagnostico,
            SUM(bp.proc_precio) AS total_facturado
        FROM billing_main bm
        LEFT JOIN protocolo_data pd ON pd.form_id = bm.form_id
        LEFT JOIN procedimiento_proyectado pp ON pp.form_id = bm.form_id
        LEFT JOIN patient_data pa ON pa.hc_number = bm.hc_number
        LEFT JOIN derivaciones_form_id d ON d.form_id = bm.form_id
        LEFT JOIN billing_procedimientos bp ON bp.billing_id = bm.id
    ";

        if ($mes) {
            $startDate = $mes . '-01';
            $endDate = date('Y-m-t', strtotime($startDate));
            $query .= " WHERE COALESCE(pd.fecha_inicio, pp.fecha) BETWEEN :startDate AND :endDate";
        }

        $query .= "
        GROUP BY bm.form_id
        ORDER BY fecha_orden DESC
    ";

        $stmt = $this->db->prepare($query);

        if ($mes) {
            $stmt->execute(['startDate' => $startDate, 'endDate' => $endDate]);
        } else {
            $stmt->execute();
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ajusta código/nombre de medicamentos según afiliación
     */
    private function ajustarCodigoPorAfiliacion(array $medicamento, string $afiliacion, array $referenciaMap): array
    {
        $codigoClave = $medicamento['codigo'] ?? '';
        $referencia = $referenciaMap[$codigoClave] ?? null;

        if ($referencia) {
            switch (strtoupper($afiliacion)) {
                case 'ISSFA':
                    $medicamento['codigo'] = $referencia['codigo_issfa'] ?? $codigoClave;
                    break;
                case 'MSP':
                    $medicamento['codigo'] = $referencia['codigo_msp'] ?? $codigoClave;
                    break;
                case 'IESS':
                    $medicamento['codigo'] = $referencia['codigo_iess'] ?? $codigoClave;
                    break;
                case 'ISSPOL':
                    $medicamento['codigo'] = $referencia['codigo_isspol'] ?? $codigoClave;
                    break;
            }
            $medicamento['nombre'] = $referencia['nombre'] ?? $medicamento['nombre'];
        }
        return $medicamento;
    }
}
