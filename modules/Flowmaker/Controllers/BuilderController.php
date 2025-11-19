<?php

namespace Modules\Flowmaker\Controllers;

use Core\BaseController;
use Modules\Flowmaker\Repositories\FlowRepository;
use Modules\Flowmaker\Services\FlowToAutoresponderConverter;
use Modules\WhatsApp\Config\WhatsAppSettings;
use Modules\WhatsApp\Repositories\AutoresponderFlowRepository;
use PDO;

class BuilderController extends BaseController
{
    private FlowRepository $flows;
    private string $assetPath;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->flows = new FlowRepository($pdo);
        $this->assetPath = BASE_PATH . '/modules/Flowmaker/public/build/assets';
    }

    public function show(int $flowId): void
    {
        $this->requireAuth();

        $flow = $this->flows->find($flowId);
        if (!$flow) {
            $default = $this->flows->ensureDefault();
            $this->redirectToBuilder((int) $default['id']);
            return;
        }

        $data = [
            'flow' => [
                'id' => (int) $flow['id'],
                'name' => $flow['name'],
                'description' => $flow['description'],
                'flow_data' => $flow['flow_data'] ?? null,
            ],
            'variables' => $this->defaultVariables(),
            'templates' => $this->defaultTemplates(),
        ];

        $this->render('modules/Flowmaker/views/builder.php', [
            'pageTitle' => 'Flowmaker',
            'flowmakerPayload' => $data,
            'scripts' => ['/flowmaker/script'],
            'styles' => ['/flowmaker/css'],
        ]);
    }

    public function update(int $flowId): void
    {
        $this->requireAuth();

        $flow = $this->flows->find($flowId);
        if (!$flow) {
            $this->json(['status' => 'error', 'message' => 'Flujo no encontrado'], 404);
            return;
        }

        $payload = $this->decodeJsonBody();
        if (!is_array($payload)) {
            $this->json(['status' => 'error', 'message' => 'Formato inválido'], 400);
            return;
        }

        $this->flows->updateFlowData($flowId, $payload, $_SESSION['user_id'] ?? null);
        $this->syncAutoresponderFlow($payload);
        $this->json(['status' => 'ok']);
    }

    public function uploadMedia(): void
    {
        $this->requireAuth();

        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->json(['error' => 'Método no permitido'], 405);
            return;
        }

        $type = $_POST['type'] ?? null;
        if (!is_string($type) || !in_array($type, ['image', 'video', 'pdf', 'document'], true)) {
            $this->json(['error' => 'Tipo inválido'], 400);
            return;
        }

        if (!isset($_FILES['file'])) {
            $this->json(['error' => 'Archivo requerido'], 400);
            return;
        }

        $file = $_FILES['file'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->json(['error' => 'No se pudo recibir el archivo'], 400);
            return;
        }

        $maxSize = match ($type) {
            'video' => 50 * 1024 * 1024,
            'pdf', 'document' => 20 * 1024 * 1024,
            default => 10 * 1024 * 1024,
        };

        if (($file['size'] ?? 0) > $maxSize) {
            $this->json(['error' => 'El archivo excede el tamaño permitido'], 400);
            return;
        }

        $destinationDir = $this->resolveUploadDirectory($type);
        if (!is_dir($destinationDir) && !mkdir($destinationDir, 0775, true) && !is_dir($destinationDir)) {
            $this->json(['error' => 'No se pudo preparar el directorio de carga'], 500);
            return;
        }

        $extension = pathinfo($file['name'] ?? '', PATHINFO_EXTENSION) ?: 'bin';
        $filename = $this->randomFilename($extension);
        $destinationPath = rtrim($destinationDir, '/') . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destinationPath)) {
            $this->json(['error' => 'No se pudo guardar el archivo'], 500);
            return;
        }

        $publicUrl = '/uploads/flowmaker/' . $type . '/' . $filename;
        $this->json([
            'status' => 'success',
            'url' => $publicUrl,
            'type' => $type,
        ]);
    }

    public function script(): void
    {
        $this->requireAuth();
        $file = $this->latestAsset('js');
        if (!$file) {
            $this->respondNotFound('No se encontró el bundle JS de Flowmaker.');
            return;
        }

        $this->outputFile($file, 'application/javascript');
    }

    public function css(): void
    {
        $this->requireAuth();
        $file = $this->latestAsset('css');
        if (!$file) {
            $this->respondNotFound('No se encontró el bundle CSS de Flowmaker.');
            return;
        }

        $this->outputFile($file, 'text/css');
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function defaultVariables(): array
    {
        return [
            ['label' => 'Nombre del contacto', 'value' => 'contact_name', 'category' => 'Contacto'],
            ['label' => 'Teléfono', 'value' => 'contact_phone', 'category' => 'Contacto'],
            ['label' => 'Correo', 'value' => 'contact_email', 'category' => 'Contacto'],
            ['label' => 'Último mensaje', 'value' => 'contact_last_message', 'category' => 'Contacto'],
        ];
    }

    /**
     * Placeholder for template catalog while we conect real data sources.
     *
     * @return array<int, array<string, mixed>>
     */
    private function defaultTemplates(): array
    {
        return [];
    }

    private function decodeJsonBody(): mixed
    {
        $raw = file_get_contents('php://input');
        if (!$raw) {
            return null;
        }

        return json_decode($raw, true);
    }

    private function resolveUploadDirectory(string $type): string
    {
        return BASE_PATH . '/public/uploads/flowmaker/' . $type;
    }

    private function randomFilename(string $extension): string
    {
        try {
            $prefix = bin2hex(random_bytes(8));
        } catch (\Throwable) {
            $prefix = bin2hex(openssl_random_pseudo_bytes(8));
        }

        $extension = ltrim($extension, '.');

        return date('YmdHis') . '_' . $prefix . '.' . $extension;
    }

    private function latestAsset(string $extension): ?string
    {
        $pattern = sprintf('%s/*.%s', $this->assetPath, $extension);
        $files = glob($pattern);

        if (!$files) {
            return null;
        }

        usort($files, static fn($a, $b) => filemtime($b) <=> filemtime($a));

        return $files[0] ?? null;
    }

    private function outputFile(string $path, string $mime): void
    {
        if (!is_file($path)) {
            $this->respondNotFound('Archivo no disponible.');
            return;
        }

        if (!headers_sent()) {
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . filesize($path));
        }

        readfile($path);
    }

    private function respondNotFound(string $message): void
    {
        if (!headers_sent()) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=UTF-8');
        }

        echo $message;
    }

    private function redirectToBuilder(int $flowId): void
    {
        if (!headers_sent()) {
            header('Location: /flowmaker/builder/' . $flowId);
        }
        exit;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function syncAutoresponderFlow(array $payload): void
    {
        try {
            $settings = new WhatsAppSettings($this->pdo);
            $config = $settings->get();
            $brand = (string) ($config['brand'] ?? 'MedForge');

            $converter = new FlowToAutoresponderConverter();
            $convertedFlow = $converter->convert($payload, $brand);

            $repository = new AutoresponderFlowRepository($this->pdo);
            $repository->save($convertedFlow);
        } catch (\Throwable $exception) {
            error_log('No fue posible sincronizar el autorespondedor con Flowmaker: ' . $exception->getMessage());
        }
    }
}
