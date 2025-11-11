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

        $payload = $_POST;

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
            $this->json([
                'success' => false,
                'message' => 'Excepción capturada al guardar el protocolo.',
                'error' => $exception->getMessage(),
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
        ], $contexto));
    }

    private function redirectWithError(): void
    {
        header('Location: /protocolos?error=1');
        exit;
    }
}
