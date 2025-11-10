<?php

namespace Modules\WhatsApp\Controllers;

use Core\BaseController;
use Modules\WhatsApp\Config\WhatsAppSettings;
use Modules\WhatsApp\Repositories\AutoresponderFlowRepository;
use Modules\WhatsApp\Support\AutoresponderFlow;
use Modules\WhatsApp\Services\TemplateManager;
use PDO;
use Throwable;
use function bin2hex;
use function hash_equals;
use function is_array;
use function is_string;
use function json_decode;
use function random_bytes;
use function uniqid;

class AutoresponderController extends BaseController
{
    private const CSRF_SESSION_KEY = 'whatsapp_autoresponder_csrf';

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
        $this->requirePermission(['settings.manage', 'administrativo']);

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

        $templates = [];
        $templatesError = null;
        try {
            $templateManager = new TemplateManager($this->pdo);
            $templateResult = $templateManager->listTemplates(['limit' => 250]);
            $templates = $templateResult['data'] ?? [];
        } catch (Throwable $exception) {
            $templatesError = $exception->getMessage();
        }

        $csrfToken = $this->generateCsrfToken();

        $this->render(BASE_PATH . '/modules/WhatsApp/views/autoresponder.php', [
            'pageTitle' => 'Flujo de autorespuesta de WhatsApp',
            'config' => $config,
            'flow' => $flow,
            'editorFlow' => $resolvedFlow,
            'status' => $status,
            'templates' => $templates,
            'templatesError' => $templatesError,
            'csrfToken' => $csrfToken,
            'scripts' => ['js/pages/whatsapp-autoresponder.js'],
            'styles' => ['css/pages/whatsapp-autoresponder.css'],
        ]);
    }

    public function update(): void
    {
        $this->requireAuth();
        $this->requirePermission(['settings.manage', 'administrativo']);

        if (!$this->validateCsrfToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['whatsapp_autoresponder_status'] = [
                'type' => 'danger',
                'message' => 'La sesión de seguridad expiró. Refresca la página e inténtalo nuevamente.',
            ];
            header('Location: /whatsapp/autoresponder');

            return;
        }

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

    private function generateCsrfToken(): string
    {
        try {
            $token = bin2hex(random_bytes(32));
        } catch (Throwable) {
            try {
                $token = bin2hex(random_bytes(16));
            } catch (Throwable) {
                $token = bin2hex(uniqid('', true));
            }
        }

        $_SESSION[self::CSRF_SESSION_KEY] = $token;

        return $token;
    }

    private function validateCsrfToken(string $submittedToken): bool
    {
        $storedToken = $_SESSION[self::CSRF_SESSION_KEY] ?? null;
        unset($_SESSION[self::CSRF_SESSION_KEY]);

        if (!is_string($storedToken) || $storedToken === '') {
            return false;
        }

        return is_string($submittedToken)
            && $submittedToken !== ''
            && hash_equals($storedToken, $submittedToken);
    }
}
