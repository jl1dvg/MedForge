<?php

namespace Modules\WhatsApp\Controllers;

use Core\BaseController;
use Modules\WhatsApp\Config\WhatsAppSettings;
use Modules\WhatsApp\Services\TemplateManager;
use PDO;
use RuntimeException;
use Throwable;

class TemplateController extends BaseController
{
    private TemplateManager $templates;
    private WhatsAppSettings $settings;
    private ?array $bodyCache = null;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->templates = new TemplateManager($pdo);
        $this->settings = new WhatsAppSettings($pdo);
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requirePermission(['whatsapp.templates.manage', 'whatsapp.manage', 'settings.manage', 'administrativo']);

        $config = $this->settings->get();
        $categories = TemplateManager::availableCategories();
        $languages = [];
        $integrationErrors = [];
        $integrationWarnings = [];

        if (!$config['enabled']) {
            $integrationErrors[] = 'WhatsApp Cloud API no está habilitado. Verifica los ajustes en Configuración > WhatsApp.';
        }

        if (trim((string) $config['business_account_id']) === '') {
            $integrationErrors[] = 'Falta el Business Account ID de WhatsApp Cloud API.';
        }

        if (trim((string) $config['access_token']) === '') {
            $integrationErrors[] = 'Falta el token de acceso de WhatsApp Cloud API.';
        }

        if (empty($integrationErrors)) {
            try {
                $languages = $this->templates->listLanguages();
            } catch (RuntimeException $exception) {
                $integrationWarnings[] = $exception->getMessage();
            } catch (Throwable $exception) {
                $integrationWarnings[] = 'No fue posible cargar los idiomas disponibles: ' . $exception->getMessage();
            }
        }

        $isIntegrationReady = ($config['enabled'] ?? false) && empty($integrationErrors);

        $bootstrap = [
            'config' => [
                'enabled' => $config['enabled'],
                'brand' => $config['brand'],
                'business_account_id' => $config['business_account_id'],
                'phone_number_id' => $config['phone_number_id'],
            ],
            'categories' => $categories,
            'languages' => $languages,
        ];

        $this->render(BASE_PATH . '/modules/WhatsApp/views/templates.php', [
            'pageTitle' => 'Plantillas de WhatsApp',
            'config' => $config,
            'categories' => $categories,
            'languages' => $languages,
            'integrationErrors' => $integrationErrors,
            'integrationWarnings' => $integrationWarnings,
            'bootstrap' => $bootstrap,
            'isIntegrationReady' => $isIntegrationReady,
            'scripts' => ['js/pages/whatsapp-templates.js'],
        ]);
    }

    public function listTemplates(): void
    {
        $this->requireAuth();
        $this->requirePermission(['whatsapp.templates.manage', 'whatsapp.manage', 'settings.manage', 'administrativo']);

        try {
            $filters = [
                'search' => $this->getQuery('search'),
                'status' => $this->getQuery('status'),
                'category' => $this->getQuery('category'),
                'language' => $this->getQuery('language'),
            ];

            if (($limit = $this->getQueryInt('limit')) !== null) {
                $filters['limit'] = $limit;
            }

            $result = $this->templates->listTemplates($filters);
            $this->json([
                'ok' => true,
                'data' => $result['data'],
                'meta' => [
                    'paging' => $result['paging'] ?? null,
                ],
            ]);
        } catch (RuntimeException $exception) {
            $this->json(['ok' => false, 'error' => $exception->getMessage()], 400);
        } catch (Throwable $exception) {
            $this->json(['ok' => false, 'error' => 'No fue posible cargar las plantillas: ' . $exception->getMessage()], 500);
        }
    }

    public function createTemplate(): void
    {
        $this->requireAuth();
        $this->requirePermission(['whatsapp.templates.manage', 'whatsapp.manage', 'settings.manage', 'administrativo']);

        try {
            $payload = $this->getBody();
            $response = $this->templates->createTemplate($payload);
            $this->json(['ok' => true, 'data' => $response], 201);
        } catch (RuntimeException $exception) {
            $this->json(['ok' => false, 'error' => $exception->getMessage()], 400);
        } catch (Throwable $exception) {
            $this->json(['ok' => false, 'error' => 'No fue posible crear la plantilla: ' . $exception->getMessage()], 500);
        }
    }

    public function updateTemplate(string $templateId): void
    {
        $this->requireAuth();
        $this->requirePermission(['whatsapp.templates.manage', 'whatsapp.manage', 'settings.manage', 'administrativo']);

        try {
            $payload = $this->getBody();
            $response = $this->templates->updateTemplate($templateId, $payload);
            $this->json(['ok' => true, 'data' => $response]);
        } catch (RuntimeException $exception) {
            $this->json(['ok' => false, 'error' => $exception->getMessage()], 400);
        } catch (Throwable $exception) {
            $this->json(['ok' => false, 'error' => 'No fue posible actualizar la plantilla: ' . $exception->getMessage()], 500);
        }
    }

    public function deleteTemplate(string $templateId): void
    {
        $this->requireAuth();
        $this->requirePermission(['whatsapp.templates.manage', 'whatsapp.manage', 'settings.manage', 'administrativo']);

        try {
            $this->templates->deleteTemplate($templateId);
            $this->json(['ok' => true]);
        } catch (RuntimeException $exception) {
            $this->json(['ok' => false, 'error' => $exception->getMessage()], 400);
        } catch (Throwable $exception) {
            $this->json(['ok' => false, 'error' => 'No fue posible eliminar la plantilla: ' . $exception->getMessage()], 500);
        }
    }

    private function getBody(): array
    {
        if ($this->bodyCache !== null) {
            return $this->bodyCache;
        }

        $data = $_POST;
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (stripos($contentType, 'application/json') !== false) {
            $decoded = json_decode((string) file_get_contents('php://input'), true);
            $this->bodyCache = is_array($decoded) ? $decoded : [];

            return $this->bodyCache;
        }

        if (!empty($data)) {
            $this->bodyCache = $data;

            return $this->bodyCache;
        }

        $decoded = json_decode((string) file_get_contents('php://input'), true);
        $this->bodyCache = is_array($decoded) ? $decoded : [];

        return $this->bodyCache;
    }

    private function getQuery(string $key): ?string
    {
        if (!isset($_GET[$key])) {
            return null;
        }

        $value = trim((string) $_GET[$key]);

        return $value === '' ? null : $value;
    }

    private function getQueryInt(string $key): ?int
    {
        if (!isset($_GET[$key])) {
            return null;
        }

        if ($_GET[$key] === '' || $_GET[$key] === null) {
            return null;
        }

        return (int) $_GET[$key];
    }
}
