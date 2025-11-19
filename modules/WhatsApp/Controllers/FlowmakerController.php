<?php

namespace Modules\WhatsApp\Controllers;

use Core\BaseController;
use Modules\WhatsApp\Config\WhatsAppSettings;
use Modules\WhatsApp\Repositories\AutoresponderFlowRepository;
use Modules\WhatsApp\Support\AutoresponderFlow;
use PDO;
use function file_get_contents;
use function getenv;
use function implode;
use function is_array;
use function json_decode;
use function json_encode;
use function parse_url;
use function trim;

class FlowmakerController extends BaseController
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

        $storedFlow = $this->flowRepository->load();
        $resolvedFlow = AutoresponderFlow::resolve($brand, $storedFlow);

        $flowmakerUrl = $this->resolveEmbedUrl($config);
        $flowmakerOrigin = $this->resolveEmbedOrigin($flowmakerUrl);

        $status = $_SESSION['whatsapp_flowmaker_status'] ?? null;
        if (!is_array($status)) {
            $status = null;
        } else {
            unset($_SESSION['whatsapp_flowmaker_status']);
        }

        $this->render(BASE_PATH . '/modules/WhatsApp/views/flowmaker.php', [
            'pageTitle' => 'Flowmaker de WhatsApp',
            'config' => $config,
            'brand' => $brand,
            'flow' => $resolvedFlow,
            'flowmakerUrl' => $flowmakerUrl,
            'flowmakerOrigin' => $flowmakerOrigin,
            'status' => $status,
            'scripts' => ['js/pages/whatsapp-flowmaker.js'],
            'styles' => ['css/pages/whatsapp-flowmaker.css'],
        ]);
    }

    public function publish(): void
    {
        $this->requireAuth();
        $this->requirePermission(['whatsapp.autoresponder.manage', 'whatsapp.manage', 'settings.manage', 'administrativo']);

        $this->respondJsonHeader();

        $rawBody = (string) file_get_contents('php://input');
        $decoded = $rawBody !== '' ? json_decode($rawBody, true) : null;
        if (!is_array($decoded)) {
            $this->respondJson(400, [
                'status' => 'error',
                'message' => 'No fue posible interpretar la configuración recibida desde Flowmaker.',
            ]);

            return;
        }

        $flowPayload = $decoded['flow'] ?? $decoded;
        if (!is_array($flowPayload)) {
            $this->respondJson(422, [
                'status' => 'error',
                'message' => 'Flowmaker no envió un flujo válido para publicar.',
            ]);

            return;
        }

        $config = $this->settings->get();
        $brand = (string) ($config['brand'] ?? 'MedForge');

        $result = AutoresponderFlow::sanitizeSubmission($flowPayload, $brand);
        if (!empty($result['errors'])) {
            $this->respondJson(422, [
                'status' => 'error',
                'message' => implode(' ', $result['errors']),
                'errors' => $result['errors'],
            ]);

            return;
        }

        if (!$this->flowRepository->save($result['flow'])) {
            $this->respondJson(500, [
                'status' => 'error',
                'message' => 'No fue posible guardar el flujo publicado. Revisa los registros para más detalles.',
            ]);

            return;
        }

        $this->respondJson(200, [
            'status' => 'ok',
            'message' => 'El flujo se publicó correctamente desde Flowmaker.',
            'flow' => $result['flow'],
            'resolved' => $result['resolved'] ?? $flowPayload,
        ]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function resolveEmbedUrl(array $config): string
    {
        $envValue = (string) (
            $_ENV['WHATSAPP_FLOWMAKER_EMBED_URL']
            ?? getenv('WHATSAPP_FLOWMAKER_EMBED_URL')
            ?? ''
        );

        $configured = trim((string) ($config['flowmaker_embed_url'] ?? ''));
        $candidate = $configured !== '' ? $configured : $envValue;

        if ($candidate === '') {
            $candidate = 'https://flowmaker.whatsbox.app/embed';
        }

        return $candidate;
    }

    private function resolveEmbedOrigin(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return '*';
        }

        $origin = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }

        return $origin;
    }

    private function respondJsonHeader(): void
    {
        header('Content-Type: application/json; charset=utf-8');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function respondJson(int $status, array $payload): void
    {
        http_response_code($status);
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE);
        echo $encoded === false ? '{}' : $encoded;
    }
}
