<?php

namespace Modules\Settings\Controllers;

use Core\BaseController;
use Helpers\SettingsHelper;
use Models\SettingsModel;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

class SettingsController extends BaseController
{
    private array $definitions;
    private ?SettingsModel $settingsModel = null;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->definitions = SettingsHelper::definitions();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requirePermission(['settings.manage', 'administrativo']);

        $status = $_GET['status'] ?? null;
        $active = $_GET['section'] ?? array_key_first($this->definitions);
        if (!isset($this->definitions[$active])) {
            $active = array_key_first($this->definitions);
        }

        $error = null;
        $repository = null;
        try {
            $repository = $this->settings();
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $postedSection = $_POST['section'] ?? $active;
            if (isset($this->definitions[$postedSection])) {
                $active = $postedSection;
                if ($repository instanceof SettingsModel) {
                    $payload = SettingsHelper::extractSectionPayload($this->definitions[$postedSection], $_POST);
                    try {
                        $payload = $this->applyUploadedFiles($this->definitions[$postedSection], $payload);
                        $affected = $repository->updateOptions($payload, $postedSection);
                        $status = $affected > 0 ? 'updated' : 'unchanged';
                    } catch (PDOException|RuntimeException $exception) {
                        $status = 'error';
                        $_SESSION['settings_error'] = $exception->getMessage();
                    }
                } else {
                    $status = 'error';
                    $_SESSION['settings_error'] = $error ?? 'No fue posible guardar los ajustes.';
                }

                header('Location: /settings?section=' . urlencode($active) . '&status=' . $status);
                exit;
            }
        }

        $options = [];
        if ($repository instanceof SettingsModel) {
            try {
                $keys = SettingsHelper::collectOptionKeys($this->definitions);
                $options = $repository->getOptions($keys);
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }
        }

        $sections = SettingsHelper::populateSections($this->definitions, $options);
        $errorMessage = $error ?? $_SESSION['settings_error'] ?? null;
        unset($_SESSION['settings_error']);

        $this->render(BASE_PATH . '/modules/Settings/views/index.php', [
            'pageTitle' => 'Configuración',
            'sections' => $sections,
            'activeSection' => $active,
            'status' => $status,
            'error' => $errorMessage,
        ]);
    }

    private function settings(): SettingsModel
    {
        if (!($this->settingsModel instanceof SettingsModel)) {
            $this->settingsModel = new SettingsModel($this->pdo);
        }

        return $this->settingsModel;
    }

    /**
     * @param array<string,mixed> $section
     * @param array<string,string> $payload
     * @return array<string,string>
     */
    private function applyUploadedFiles(array $section, array $payload): array
    {
        foreach ($section['groups'] as $group) {
            foreach ($group['fields'] as $field) {
                if (($field['type'] ?? '') !== 'file') {
                    continue;
                }

                $key = (string) $field['key'];
                $input = $key . '_file';
                $file = $_FILES[$input] ?? null;
                if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                $payload[$key] = $this->storeSettingsImage($file, $key);
            }
        }

        return $payload;
    }

    /**
     * @param array<string,mixed> $file
     */
    private function storeSettingsImage(array $file, string $key): string
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('No se pudo subir el archivo de configuración.');
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('El archivo subido no es válido.');
        }

        $originalName = (string) ($file['name'] ?? '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'];
        if (!in_array($extension, $allowed, true)) {
            throw new RuntimeException('El logo debe ser PNG, JPG, WEBP, GIF o SVG.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > 3 * 1024 * 1024) {
            throw new RuntimeException('El logo no puede superar 3MB.');
        }

        $safeKey = preg_replace('/[^a-z0-9_-]+/i', '_', $key) ?: 'setting';
        $filename = date('YmdHis') . '_' . $safeKey . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $relativeDir = '/uploads/company';
        $absoluteDir = rtrim(PUBLIC_PATH, DIRECTORY_SEPARATOR) . $relativeDir;
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            throw new RuntimeException('No se pudo crear la carpeta de logos de empresa.');
        }

        $destination = $absoluteDir . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($tmpName, $destination)) {
            throw new RuntimeException('No se pudo guardar el logo de empresa.');
        }

        return $relativeDir . '/' . $filename;
    }
}
