<?php

namespace Modules\EditorProtocolos\Controllers;

use Core\BaseController;
use Modules\EditorProtocolos\Services\ProtocoloTemplateService;
use PDO;
use Throwable;

class EditorController extends BaseController
{
    private ProtocoloTemplateService $service;
    private array $vias = ['INTRAVENOSA', 'VIA INFILTRATIVA', 'SUBCONJUNTIVAL', 'TOPICA', 'INTRAVITREA'];
    private array $responsables = ['Asistente', 'Anestesiólogo', 'Cirujano Principal'];

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->service = new ProtocoloTemplateService($pdo);
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requirePermission(['protocolos.templates.view', 'protocolos.manage', 'administrativo']);

        $canManage = $this->hasPermission(['protocolos.templates.manage', 'protocolos.manage', 'administrativo']);

        $procedimientos = $this->service->obtenerProcedimientosAgrupados();
        $mensajeExito = null;
        $mensajeError = null;

        if (isset($_GET['deleted'])) {
            $mensajeExito = 'Protocolo eliminado correctamente.';
        }

        if (isset($_GET['saved'])) {
            $mensajeExito = 'Protocolo guardado correctamente.';
        }

        if (isset($_GET['error'])) {
            $mensajeError = 'No se pudo completar la operación solicitada.';
        }

        $this->render('modules/EditorProtocolos/views/index.php', [
            'pageTitle' => 'Editor de Protocolos',
            'procedimientosPorCategoria' => $procedimientos,
            'mensajeExito' => $mensajeExito,
            'mensajeError' => $mensajeError,
            'canManage' => $canManage,
            'csrfToken' => $this->csrfToken(),
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();
        $this->requirePermission(['protocolos.templates.manage', 'protocolos.manage', 'administrativo']);

        $categoria = isset($_GET['categoria']) ? (string)$_GET['categoria'] : null;
        $protocolo = $this->service->crearProtocoloVacio($categoria);

        $this->renderFormulario($protocolo, [
            'pageTitle' => 'Nuevo protocolo',
            'esNuevo' => true,
        ]);
    }

    public function edit(): void
    {
        $this->requireAuth();
        $this->requirePermission(['protocolos.templates.manage', 'protocolos.manage', 'administrativo']);

        $duplicarId = isset($_GET['duplicar']) ? (string)$_GET['duplicar'] : null;
        $id = isset($_GET['id']) ? (string)$_GET['id'] : null;

        if ($duplicarId) {
            $original = $this->service->obtenerProtocoloPorId($duplicarId);
            if (!$original) {
                $this->redirectWithError();
                return;
            }

            $protocolo = $original;
            $protocolo['id'] = '';
            $protocolo['codigos'] = $this->service->obtenerCodigosDeProcedimiento($duplicarId);
            $protocolo['staff'] = $this->service->obtenerStaffDeProcedimiento($duplicarId);
            $protocolo['insumos'] = $this->service->obtenerInsumosDeProtocolo($duplicarId);
            $protocolo['medicamentos'] = $this->service->obtenerMedicamentosDeProtocolo($duplicarId);

            $this->renderFormulario($protocolo, [
                'pageTitle' => 'Duplicar protocolo',
                'duplicando' => true,
                'duplicarId' => $duplicarId,
            ]);
            return;
        }

        if ($id) {
            $protocolo = $this->service->obtenerProtocoloPorId($id);
            if (!$protocolo) {
                $this->redirectWithError();
                return;
            }

            $protocolo['codigos'] = $this->service->obtenerCodigosDeProcedimiento($id);
            $protocolo['staff'] = $this->service->obtenerStaffDeProcedimiento($id);
            $protocolo['insumos'] = $this->service->obtenerInsumosDeProtocolo($id);
            $protocolo['medicamentos'] = $this->service->obtenerMedicamentosDeProtocolo($id);

            $this->renderFormulario($protocolo, [
                'pageTitle' => 'Editar protocolo',
            ]);
            return;
        }

        $this->redirectWithError();
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->requirePermission(['protocolos.templates.manage', 'protocolos.manage', 'administrativo']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json([
                'success' => false,
                'message' => 'Método no permitido.',
            ], 405);
            return;
        }

        if (!$this->isValidCsrfToken($_POST['csrf_token'] ?? null)) {
            $this->json([
                'success' => false,
                'message' => 'Token de seguridad inválido. Recarga la página e intenta nuevamente.',
            ], 419);
            return;
        }

        $payload = $this->normalizePayload($_POST);

        if (!$this->isValidPayload($payload, $validationError)) {
            $this->json([
                'success' => false,
                'message' => $validationError,
            ], 422);
            return;
        }

        if (empty($payload['id']) && !empty($payload['cirugia'])) {
            $payload['id'] = $this->service->generarIdUnicoDesdeCirugia($payload['cirugia']);
        }

        try {
            $resultado = $this->service->actualizarProcedimiento($payload);
            $this->json([
                'success' => $resultado,
                'message' => $resultado ? 'Protocolo actualizado exitosamente.' : 'Error al actualizar el protocolo.',
                'generated_id' => $payload['id'] ?? null,
            ], $resultado ? 200 : 500);
        } catch (Throwable $exception) {
            error_log('❌ Error en EditorController::store: ' . $exception->getMessage());
            $this->json([
                'success' => false,
                'message' => 'Excepción capturada al guardar el protocolo.',
            ], 500);
        }
    }

    public function delete(): void
    {
        $this->requireAuth();
        $this->requirePermission(['protocolos.templates.manage', 'protocolos.manage', 'administrativo']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Método no permitido';
            return;
        }

        if (!$this->isValidCsrfToken($_POST['csrf_token'] ?? null)) {
            http_response_code(419);
            echo 'Token de seguridad inválido.';
            return;
        }

        $id = isset($_POST['id']) ? (string)$_POST['id'] : '';
        if ($id === '') {
            $this->redirectWithError();
            return;
        }

        $resultado = $this->service->eliminarProtocolo($id);
        if ($resultado) {
            header('Location: /protocolos?deleted=1');
            exit;
        }

        $this->redirectWithError();
    }

    private function renderFormulario(array $protocolo, array $contexto = []): void
    {
        $this->render('modules/EditorProtocolos/views/edit.php', array_merge([
            'pageTitle' => $contexto['pageTitle'] ?? 'Editor de protocolos',
            'protocolo' => $protocolo,
            'medicamentos' => $protocolo['medicamentos'] ?? [],
            'opcionesMedicamentos' => $this->service->obtenerOpcionesMedicamentos(),
            'insumosDisponibles' => $this->service->obtenerInsumosDisponibles(),
            'insumosPaciente' => $protocolo['insumos'] ?? ['equipos' => [], 'quirurgicos' => [], 'anestesia' => []],
            'codigos' => $protocolo['codigos'] ?? [],
            'staff' => $protocolo['staff'] ?? [],
            'vias' => $this->vias,
            'responsables' => $this->responsables,
            'duplicando' => $contexto['duplicando'] ?? false,
            'esNuevo' => $contexto['esNuevo'] ?? false,
            'duplicarId' => $contexto['duplicarId'] ?? null,
            'csrfToken' => $this->csrfToken(),
        ], $contexto));
    }

    private function redirectWithError(): void
    {
        header('Location: /protocolos?error=1');
        exit;
    }

    private function csrfToken(): string
    {
        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    private function isValidCsrfToken(?string $token): bool
    {
        $sessionToken = $_SESSION['csrf_token'] ?? null;

        if (!is_string($token) || $token === '') {
            return false;
        }

        if (!is_string($sessionToken) || $sessionToken === '') {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    private function normalizePayload(array $input): array
    {
        $payload = $input;

        $stringFields = [
            'id',
            'cirugia',
            'categoriaQX',
            'membrete',
            'dieresis',
            'exposicion',
            'hallazgo',
            'horas',
            'imagen_link',
            'operatorio',
            'pre_evolucion',
            'pre_indicacion',
            'post_evolucion',
            'post_indicacion',
            'alta_evolucion',
            'alta_indicacion',
            'insumos',
            'medicamentos',
        ];

        foreach ($stringFields as $field) {
            $payload[$field] = isset($payload[$field]) ? trim((string)$payload[$field]) : '';
        }

        $arrayFields = [
            'codigos',
            'lateralidades',
            'selectores_codigos',
            'funciones',
            'trabajadores',
            'nombres_staff',
            'selectores_staff',
        ];

        foreach ($arrayFields as $field) {
            if (!isset($payload[$field]) || !is_array($payload[$field])) {
                $payload[$field] = [];
            }
        }

        return $payload;
    }

    private function isValidPayload(array $payload, ?string &$error = null): bool
    {
        if ($payload['cirugia'] === '') {
            $error = 'Debes ingresar el nombre corto del procedimiento.';
            return false;
        }

        if ($payload['membrete'] === '') {
            $error = 'Debes ingresar el título del protocolo.';
            return false;
        }

        if ($payload['categoriaQX'] === '') {
            $error = 'Debes seleccionar una categoría.';
            return false;
        }

        if ($payload['horas'] !== '' && !is_numeric($payload['horas'])) {
            $error = 'La duración estimada debe ser numérica.';
            return false;
        }

        if ($payload['imagen_link'] !== '' && filter_var($payload['imagen_link'], FILTER_VALIDATE_URL) === false) {
            $error = 'El enlace de imagen no tiene un formato válido.';
            return false;
        }

        $error = null;
        return true;
    }
}
