<?php

namespace Modules\Autoresponder\Controllers;

use Core\BaseController;
use Modules\WhatsApp\Config\WhatsAppSettings;
use Modules\Autoresponder\Repositories\AutoresponderFlowRepository;
use Modules\WhatsApp\Repositories\InboxRepository;
use Modules\WhatsApp\Support\AutoresponderFlow;
use Modules\WhatsApp\Services\TemplateManager;
use Modules\Usuarios\Models\RolModel;
use PDO;
use function array_reverse;
use function is_array;
use function is_string;
use function json_decode;
use Throwable;

class AutoresponderController extends BaseController
{
    private WhatsAppSettings $settings;
    private AutoresponderFlowRepository $flowRepository;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->settings = new WhatsAppSettings($pdo);
        $this->flowRepository = new AutoresponderFlowRepository($pdo);
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requirePermission(['whatsapp.autoresponder.manage', 'whatsapp.manage', 'settings.manage', 'administrativo']);

        $config = $this->settings->get();
        $brand = (string) ($config['brand'] ?? 'MedForge');

        $status = $_SESSION['whatsapp_autoresponder_status'] ?? null;
        unset($_SESSION['whatsapp_autoresponder_status']);

        $draft = $_SESSION['whatsapp_autoresponder_draft'] ?? null;
        if (is_array($draft)) {
            unset($_SESSION['whatsapp_autoresponder_draft']);
        } else {
            $draft = null;
        }

        $storedFlow = $this->flowRepository->load();
        $editorSource = is_array($draft) ? $draft : $storedFlow;
        $resolvedFlow = AutoresponderFlow::resolve($brand, $editorSource);
        $flow = AutoresponderFlow::overview($brand, $editorSource);
        $contract = AutoresponderFlow::contract($brand);

        $templates = [];
        $templatesError = null;
        try {
            $templateManager = new TemplateManager($this->pdo);
            $templateResult = $templateManager->listTemplates(['limit' => 250]);
            $templates = $templateResult['data'] ?? [];
        } catch (Throwable $exception) {
            $templatesError = $exception->getMessage();
        }

        $inboxRepository = new InboxRepository($this->pdo);
        $inboxMessages = array_reverse($inboxRepository->fetchRecent(25));

        $roles = [];
        try {
            $roles = (new RolModel($this->pdo))->all();
        } catch (Throwable $exception) {
            $roles = [];
        }

        $this->render(BASE_PATH . '/modules/Autoresponder/views/autoresponder_flow.php', [
            'pageTitle' => 'Flujo de autorespuesta de WhatsApp',
            'config' => $config,
            'brand' => $brand,
            'flow' => $flow,
            'editorFlow' => $resolvedFlow,
            'contract' => $contract,
            'status' => $status,
            'templates' => $templates,
            'templatesError' => $templatesError,
            'inboxMessages' => $inboxMessages,
            'roles' => $roles,
            'scripts' => ['js/pages/whatsapp-autoresponder.js'],
            'styles' => ['css/pages/whatsapp-autoresponder.css'],
        ]);
    }

    public function update(): void
    {
        $this->requireAuth();
        $this->requirePermission(['whatsapp.autoresponder.manage', 'whatsapp.manage', 'settings.manage', 'administrativo']);

        $payload = $_POST['flow_payload'] ?? '';
        $decoded = is_string($payload) ? json_decode($payload, true) : null;

        if (!is_array($decoded)) {
            $_SESSION['whatsapp_autoresponder_status'] = [
                'type' => 'danger',
                'message' => 'No fue posible interpretar la configuración enviada. Inténtalo nuevamente.',
            ];
            header('Location: /whatsapp/autoresponder');

            return;
        }

        $config = $this->settings->get();
        $brand = (string) ($config['brand'] ?? 'MedForge');

        $result = AutoresponderFlow::sanitizeSubmission($decoded, $brand);
        if (!empty($result['errors'])) {
            $_SESSION['whatsapp_autoresponder_status'] = [
                'type' => 'danger',
                'message' => implode(' ', $result['errors']),
            ];
            $_SESSION['whatsapp_autoresponder_draft'] = $result['resolved'] ?? $decoded;
            header('Location: /whatsapp/autoresponder');

            return;
        }

        if ($this->flowRepository->save($result['flow'])) {
            $_SESSION['whatsapp_autoresponder_status'] = [
                'type' => 'success',
                'message' => 'El flujo de autorespuesta se actualizó correctamente.',
            ];
            unset($_SESSION['whatsapp_autoresponder_draft']);
        } else {
            $_SESSION['whatsapp_autoresponder_status'] = [
                'type' => 'danger',
                'message' => 'No fue posible guardar los cambios. Revisa los registros para más detalles.',
            ];
            $_SESSION['whatsapp_autoresponder_draft'] = $result['resolved'] ?? $decoded;
        }

        header('Location: /whatsapp/autoresponder');
    }
}
