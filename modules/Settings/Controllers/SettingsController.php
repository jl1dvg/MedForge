<?php

namespace Modules\Settings\Controllers;

use Core\BaseController;
use Helpers\SettingsHelper;
use Models\SettingsRepository;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

class SettingsController extends BaseController
{
    private array $definitions;
    private ?SettingsRepository $repository = null;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->definitions = SettingsHelper::definitions();
    }

    public function index(): void
    {
        $this->requireAuth();

        $status = $_GET['status'] ?? null;
        $active = $_GET['section'] ?? array_key_first($this->definitions);
        if (!isset($this->definitions[$active])) {
            $active = array_key_first($this->definitions);
        }

        $error = null;
        $repository = null;
        try {
            $repository = $this->repository();
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $postedSection = $_POST['section'] ?? $active;
            if (isset($this->definitions[$postedSection])) {
                $active = $postedSection;
                if ($repository instanceof SettingsRepository) {
                    $payload = SettingsHelper::extractSectionPayload($this->definitions[$postedSection], $_POST);
                    try {
                        $affected = $repository->updateOptions($payload);
                        $status = $affected > 0 ? 'updated' : 'unchanged';
                    } catch (PDOException $exception) {
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
        if ($repository instanceof SettingsRepository) {
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

    private function repository(): SettingsRepository
    {
        if (!($this->repository instanceof SettingsRepository)) {
            $this->repository = new SettingsRepository($this->pdo);
        }

        return $this->repository;
    }
}
