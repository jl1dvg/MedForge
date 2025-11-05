<?php

namespace Modules\Cirugias\Controllers;

use Controllers\IplPlanificadorController;
use Core\BaseController;
use Modules\Cirugias\Models\Cirugia;
use Modules\Cirugias\Services\CirugiaService;
use Modules\Pacientes\Services\PacienteService;
use PDO;

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

        $cirugias = $this->service->obtenerListaCirugias();
        $meses = $this->buildMesesDisponibles($cirugias);

        $this->render('modules/Cirugias/views/index.php', [
            'pageTitle' => 'Reporte de Cirugías',
            'cirugias' => $cirugias,
            'mesesDisponibles' => $meses,
        ]);
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

        $ok = $this->service->actualizarStatus($formId, $hcNumber, $status);
        $this->json(['success' => $ok]);
    }

    private function buildMesesDisponibles(array $cirugias): array
    {
        $meses = [];

        /** @var Cirugia $cirugia */
        foreach ($cirugias as $cirugia) {
            $fecha = $cirugia->fecha_inicio ?? null;
            if (!$fecha) {
                continue;
            }
            $mes = substr($fecha, 0, 7);
            if (!$mes) {
                continue;
            }
            $meses[$mes] = date('F Y', strtotime($mes . '-01'));
        }

        krsort($meses);

        return $meses;
    }
}
