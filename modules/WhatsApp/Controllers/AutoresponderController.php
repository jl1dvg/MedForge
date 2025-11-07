<?php

namespace Modules\WhatsApp\Controllers;

use Core\BaseController;
use Modules\WhatsApp\Config\WhatsAppSettings;
use Modules\WhatsApp\Support\AutoresponderFlow;
use PDO;

class AutoresponderController extends BaseController
{
    private WhatsAppSettings $settings;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->settings = new WhatsAppSettings($pdo);
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requirePermission(['settings.manage', 'administrativo']);

        $config = $this->settings->get();
        $brand = (string) ($config['brand'] ?? 'MedForge');
        $flow = AutoresponderFlow::overview($brand);

        $this->render(BASE_PATH . '/modules/WhatsApp/views/autoresponder.php', [
            'pageTitle' => 'Flujo de autorespuesta de WhatsApp',
            'config' => $config,
            'flow' => $flow,
            'scripts' => [],
        ]);
    }
}
