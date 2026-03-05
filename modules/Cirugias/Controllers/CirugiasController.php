<?php

namespace Modules\Cirugias\Controllers;

use Controllers\IplPlanificadorController;
use Core\BaseController;
use Modules\Cirugias\Services\CirugiaService;
use Modules\Pacientes\Services\PacienteService;
use PDO;
use Throwable;

class CirugiasController extends BaseController
{
    private CirugiaService $service;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->service = new CirugiaService($pdo);
    }

    public function index(): void
    {
        $this->requireAuth();

        $fechaFinDefault = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $fechaInicioDefault = (new \DateTimeImmutable('today'))->modify('-30 days')->format('Y-m-d');

        $this->render('modules/Cirugias/views/index.php', [
            'pageTitle' => 'Reporte de Cirugías',
            'afiliacionOptions' => $this->service->obtenerAfiliacionOptions(),
            'afiliacionCategoriaOptions' => $this->service->obtenerAfiliacionCategoriaOptions(),
            'sedeOptions' => $this->service->obtenerSedeOptions(),
            'fechaInicioDefault' => $fechaInicioDefault,
            'fechaFinDefault' => $fechaFinDefault,
        ]);
    }

    public function datatable(): void
    {
        if (!$this->isAuthenticated()) {
            $this->json([
                'draw' => isset($_POST['draw']) ? (int)$_POST['draw'] : 1,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Sesion expirada',
            ], 401);
            return;
        }

        try {
            $draw = isset($_POST['draw']) ? (int)$_POST['draw'] : 1;
            $start = isset($_POST['start']) ? (int)$_POST['start'] : 0;
            $length = isset($_POST['length']) ? (int)$_POST['length'] : 25;
            $search = (string)($_POST['search']['value'] ?? '');
            $orderColumnIndex = isset($_POST['order'][0]['column']) ? (int)$_POST['order'][0]['column'] : 4;
            $orderDir = (string)($_POST['order'][0]['dir'] ?? 'desc');

            $columnMap = [
                0 => 'form_id',
                1 => 'hc_number',
                2 => 'full_name',
                3 => 'afiliacion',
                4 => 'fecha_inicio',
                5 => 'membrete',
            ];
            $orderColumn = $columnMap[$orderColumnIndex] ?? 'fecha_inicio';

            $result = $this->service->obtenerCirugiasPaginadas(
                $start,
                $length,
                $search,
                $orderColumn,
                strtoupper($orderDir),
                [
                    'fecha_inicio' => (string)($_POST['fecha_inicio'] ?? ''),
                    'fecha_fin' => (string)($_POST['fecha_fin'] ?? ''),
                    'afiliacion' => (string)($_POST['afiliacion'] ?? ''),
                    'afiliacion_categoria' => (string)($_POST['afiliacion_categoria'] ?? ''),
                    'sede' => (string)($_POST['sede'] ?? ''),
                ]
            );

            $data = array_map(fn(array $row): array => $this->buildDatatableRow($row), $result['data']);

            $this->json([
                'draw' => $draw,
                'recordsTotal' => (int)($result['recordsTotal'] ?? 0),
                'recordsFiltered' => (int)($result['recordsFiltered'] ?? 0),
                'data' => $data,
            ]);
        } catch (Throwable $exception) {
            error_log('Cirugias datatable error: ' . $exception->getMessage());
            $errorDetail = trim($exception->getMessage()) !== ''
                ? ('No se pudo cargar la tabla de cirugias: ' . $exception->getMessage())
                : 'No se pudo cargar la tabla de cirugias';
            $this->json([
                'draw' => isset($_POST['draw']) ? (int)$_POST['draw'] : 1,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $errorDetail,
            ], 500);
        }
    }

    public function wizard(): void
    {
        $this->requireAuth();

        $formId = $_GET['form_id'] ?? $_POST['form_id'] ?? null;
        $hcNumber = $_GET['hc_number'] ?? $_POST['hc_number'] ?? null;

        if (!$formId || !$hcNumber) {
            http_response_code(400);
            $this->render('modules/Cirugias/views/wizard_missing.php', [
                'pageTitle' => 'Protocolo no encontrado',
            ]);
            return;
        }

        $cirugia = $this->service->obtenerCirugiaPorId($formId, $hcNumber);

        if (!$cirugia) {
            http_response_code(404);
            $this->render('modules/Cirugias/views/wizard_missing.php', [
                'pageTitle' => 'Protocolo no encontrado',
                'formId' => $formId,
                'hcNumber' => $hcNumber,
            ]);
            return;
        }

        $insumosDisponibles = $this->service->obtenerInsumosDisponibles($cirugia->afiliacion ?? '');
        foreach ($insumosDisponibles as &$grupo) {
            uasort($grupo, fn(array $a, array $b) => strcmp($a['nombre'], $b['nombre']));
        }
        unset($grupo);

        $insumosSeleccionados = $this->service->obtenerInsumosPorProtocolo($cirugia->procedimiento_id ?? null, $cirugia->insumos ?? null);
        $categorias = array_keys($insumosDisponibles);

        $medicamentosSeleccionados = $this->service->obtenerMedicamentosConfigurados($cirugia->medicamentos ?? null, $cirugia->procedimiento_id ?? null);
        $opcionesMedicamentos = $this->service->obtenerOpcionesMedicamentos();

        $pacienteService = new PacienteService($this->pdo);
        $cirujanos = $pacienteService->obtenerStaffPorEspecialidad();
        $verificacionController = new IplPlanificadorController($this->pdo);

        $this->render('modules/Cirugias/views/wizard.php', [
            'pageTitle' => 'Editar protocolo quirúrgico',
            'cirugia' => $cirugia,
            'insumosDisponibles' => $insumosDisponibles,
            'insumosSeleccionados' => $insumosSeleccionados,
            'categoriasInsumos' => $categorias,
            'medicamentosSeleccionados' => $medicamentosSeleccionados,
            'opcionesMedicamentos' => $opcionesMedicamentos,
            'viasDisponibles' => ['INTRAVENOSA', 'VIA INFILTRATIVA', 'SUBCONJUNTIVAL', 'TOPICA', 'INTRAVITREA'],
            'responsablesMedicamentos' => ['Asistente', 'Anestesiólogo', 'Cirujano Principal'],
            'cirujanos' => $cirujanos,
            'pacienteService' => $pacienteService,
            'verificacionController' => $verificacionController,
        ]);
    }

    public function guardar(): void
    {
        $this->requireAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Método no permitido'], 405);
            return;
        }

        $exito = $this->service->guardar($_POST);

        $statusCode = $exito ? 200 : 500;
        $response = [
            'success' => $exito,
            'message' => $exito
                ? 'Operación completada.'
                : ($this->service->getLastError() ?? 'No se pudo guardar la información del protocolo.'),
        ];

        if ($exito && !empty($_POST['form_id'])) {
            $protocoloId = $this->service->obtenerProtocoloIdPorFormulario($_POST['form_id'], $_POST['hc_number'] ?? null);
            if ($protocoloId !== null) {
                $response['protocolo_id'] = $protocoloId;
            }

            if (!empty($_POST['status']) && (int) $_POST['status'] === 1) {
                $this->service->actualizarStatus($_POST['form_id'], $_POST['hc_number'] ?? '', 1, $this->currentUserId());
            }
        }

        $this->json($response, $statusCode);
    }

    public function autosave(): void
    {
        $this->requireAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Método no permitido'], 405);
            return;
        }

        $formId = $_POST['form_id'] ?? null;
        $hcNumber = $_POST['hc_number'] ?? null;

        if (!$formId || !$hcNumber) {
            $this->json(['success' => false, 'message' => 'Faltan parámetros'], 400);
            return;
        }

        $insumos = $_POST['insumos'] ?? null;
        $medicamentos = $_POST['medicamentos'] ?? null;

        $success = $this->service->guardarAutosave($formId, $hcNumber, $insumos, $medicamentos);

        if (!$success) {
            $this->json([
                'success' => false,
                'message' => $this->service->getLastError() ?? 'No se pudo guardar el autosave.',
            ], 500);
            return;
        }

        $this->json(['success' => true]);
    }

    public function protocolo(): void
    {
        $this->requireAuth();

        $formId = $_GET['form_id'] ?? null;
        $hcNumber = $_GET['hc_number'] ?? null;

        if (!$formId || !$hcNumber) {
            $this->json(['error' => 'Faltan parámetros'], 400);
            return;
        }

        $cirugia = $this->service->obtenerCirugiaPorId($formId, $hcNumber);

        if (!$cirugia) {
            $this->json(['error' => 'No se encontró el protocolo'], 404);
            return;
        }

        $diagnosticosRaw = json_decode($cirugia->diagnosticos ?? '[]', true) ?: [];
        $diagnosticos = array_map(static function (array $d): array {
            $cie10 = '';
            $detalle = '';

            if (isset($d['idDiagnostico'])) {
                $partes = explode(' - ', $d['idDiagnostico'], 2);
                $cie10 = trim($partes[0] ?? '');
                $detalle = trim($partes[1] ?? '');
            }

            return [
                'cie10' => $cie10,
                'detalle' => $detalle,
            ];
        }, $diagnosticosRaw);

        $procedimientosRaw = json_decode($cirugia->procedimientos ?? '[]', true) ?: [];
        $procedimientos = array_map(static function (array $p): array {
            $codigo = '';
            $nombre = '';
            $codigoStr = $p['codigo'] ?? $p['procInterno'] ?? '';

            if ($codigoStr) {
                if (preg_match('/-\s*(\d+)\s*-\s*(.*)/', $codigoStr, $match)) {
                    $codigo = trim($match[1] ?? '');
                    $nombre = trim($match[2] ?? '');
                } else {
                    $partes = explode(' - ', $codigoStr, 3);
                    $codigo = trim($partes[1] ?? '');
                    $nombre = trim($partes[2] ?? '');
                }
            }

            return [
                'codigo' => $codigo,
                'nombre' => $nombre,
            ];
        }, $procedimientosRaw);

        $staff = [
            'Cirujano principal' => $cirugia->cirujano_1,
            'Instrumentista' => $cirugia->instrumentista,
            'Cirujano 2' => $cirugia->cirujano_2,
            'Circulante' => $cirugia->circulante,
            'Primer ayudante' => $cirugia->primer_ayudante,
            'Segundo ayudante' => $cirugia->segundo_ayudante,
            'Tercer ayudante' => $cirugia->tercer_ayudante,
            'Anestesiólogo' => $cirugia->anestesiologo,
            'Ayudante anestesia' => $cirugia->ayudante_anestesia,
        ];

        $duracion = '';
        if ($cirugia->hora_inicio && $cirugia->hora_fin) {
            $inicio = strtotime($cirugia->hora_inicio);
            $fin = strtotime($cirugia->hora_fin);
            if ($inicio && $fin && $fin > $inicio) {
                $diff = $fin - $inicio;
                $duracion = floor($diff / 3600) . 'h ' . floor(($diff % 3600) / 60) . 'm';
            }
        }

        $this->json([
            'fecha_inicio' => $cirugia->fecha_inicio,
            'hora_inicio' => $cirugia->hora_inicio,
            'hora_fin' => $cirugia->hora_fin,
            'duracion' => $duracion,
            'dieresis' => $cirugia->dieresis,
            'exposicion' => $cirugia->exposicion,
            'hallazgo' => $cirugia->hallazgo,
            'operatorio' => $cirugia->operatorio,
            'comentario' => $cirugia->complicaciones_operatorio,
            'diagnosticos' => $diagnosticos,
            'procedimientos' => $procedimientos,
            'staff' => $staff,
        ]);
    }

    public function togglePrinted(): void
    {
        $this->requireAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Método no permitido'], 405);
            return;
        }

        $formId = $_POST['form_id'] ?? null;
        $hcNumber = $_POST['hc_number'] ?? null;
        $printed = isset($_POST['printed']) ? (int)$_POST['printed'] : null;

        if ($formId === null || $hcNumber === null || $printed === null) {
            $this->json(['success' => false, 'message' => 'Faltan parámetros'], 400);
            return;
        }

        $ok = $this->service->actualizarPrinted($formId, $hcNumber, $printed);
        $this->json(['success' => $ok]);
    }

    public function updateStatus(): void
    {
        $this->requireAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Método no permitido'], 405);
            return;
        }

        $formId = $_POST['form_id'] ?? null;
        $hcNumber = $_POST['hc_number'] ?? null;
        $status = isset($_POST['status']) ? (int)$_POST['status'] : null;

        if ($formId === null || $hcNumber === null || $status === null) {
            $this->json(['success' => false, 'message' => 'Faltan parámetros'], 400);
            return;
        }

        $ok = $this->service->actualizarStatus($formId, $hcNumber, $status, $this->currentUserId());
        $this->json(['success' => $ok]);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, string>
     */
    private function buildDatatableRow(array $row): array
    {
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        $cirugia = new \Modules\Cirugias\Models\Cirugia($row);
        $estado = $cirugia->getEstado();
        $printed = (int)($row['printed'] ?? 0);

        $badgeEstado = match ($estado) {
            'revisado' => "<span class='badge bg-success'><i class='fa fa-check'></i></span>",
            'no revisado' => "<span class='badge bg-warning'><i class='fa fa-exclamation-triangle'></i></span>",
            default => "<span class='badge bg-danger'><i class='fa fa-times'></i></span>",
        };
        $badgePrinted = $printed ? "<span class='badge bg-success'><i class='fa fa-check'></i></span>" : '';

        $formId = (string)($row['form_id'] ?? '');
        $hcNumber = (string)($row['hc_number'] ?? '');
        $formIdEsc = $esc($formId);
        $hcNumberEsc = $esc($hcNumber);
        $formIdJs = json_encode($formId, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        $hcNumberJs = json_encode($hcNumber, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

        $protocoloHtml = '<a href="#" class="btn btn-app btn-info" '
            . 'title="Ver protocolo quirurgico" '
            . 'data-bs-toggle="modal" data-bs-target="#resultModal" '
            . 'data-form-id="' . $formIdEsc . '" '
            . 'data-hc-number="' . $hcNumberEsc . '" '
            . 'onclick="loadProtocolData(this)">'
            . $badgeEstado . '<i class="mdi mdi-file-document"></i> Protocolo</a>';

        $descansoOnClick = 'emitirCertificadoDescanso(' . $formIdJs . ', ' . $hcNumberJs . ')';
        $descansoHtml = '<a class="btn btn-app btn-warning" '
            . 'title="Generar certificado de descanso postquirurgico" '
            . 'onclick="' . $esc($descansoOnClick) . '">'
            . '<i class="mdi mdi-file-document-box"></i> Descanso</a>';

        $printOnClick = $estado === 'revisado'
            ? 'togglePrintStatus(' . $formIdJs . ', ' . $hcNumberJs . ', this, ' . $printed . ')'
            : "Swal.fire({ icon: 'warning', title: 'Pendiente revision', text: 'Debe revisar el protocolo antes de imprimir.' })";

        $imprimirHtml = '<a class="btn btn-app btn-primary ' . ($printed ? 'active' : '') . '" '
            . 'title="Imprimir protocolo" onclick="' . $esc($printOnClick) . '">'
            . $badgePrinted . '<i class="fa fa-print"></i> Imprimir</a>';

        $fechaInicioRaw = (string)($row['fecha_inicio'] ?? '');
        $fechaInicio = '';
        if ($fechaInicioRaw !== '') {
            $ts = strtotime($fechaInicioRaw);
            $fechaInicio = $ts ? date('d/m/Y', $ts) : $fechaInicioRaw;
        }

        $afiliacion = trim((string)($row['afiliacion_label'] ?? $row['afiliacion'] ?? ''));
        if ($afiliacion === '') {
            $afiliacion = 'Sin convenio';
        }
        $categoria = trim((string)($row['afiliacion_categoria'] ?? ''));
        $afiliacionHtml = $esc($afiliacion);
        if ($categoria !== '') {
            $afiliacionHtml .= '<span class="d-block text-muted fs-11">Categoria: ' . $esc(ucfirst($categoria)) . '</span>';
        }

        return [
            'form_id' => $esc((string)($row['form_id'] ?? '')),
            'hc_number' => $esc((string)($row['hc_number'] ?? '')),
            'full_name' => $esc($cirugia->getNombreCompleto()),
            'afiliacion_html' => $afiliacionHtml,
            'fecha_inicio' => $esc($fechaInicio),
            'membrete' => $esc((string)($row['membrete'] ?? '')),
            'protocolo_html' => $protocoloHtml,
            'descanso_html' => $descansoHtml,
            'imprimir_html' => $imprimirHtml,
        ];
    }
}
