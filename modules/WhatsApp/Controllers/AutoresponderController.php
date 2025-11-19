<?php

namespace Modules\WhatsApp\Controllers;

use Core\BaseController;
use Modules\Flowmaker\Repositories\FlowRepository;
use PDO;

class AutoresponderController extends BaseController
{
    private FlowRepository $flowmaker;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->flowmaker = new FlowRepository($pdo);
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requirePermission(['whatsapp.autoresponder.manage', 'whatsapp.manage', 'settings.manage', 'administrativo']);
        $this->redirectToBuilder();
    }

    public function update(): void
    {
        $this->requireAuth();
        $this->requirePermission(['whatsapp.autoresponder.manage', 'whatsapp.manage', 'settings.manage', 'administrativo']);
        $this->redirectToBuilder();
    }

    private function redirectToBuilder(): void
    {
        $flow = $this->flowmaker->getOrCreateAutoresponderFlow();
        if (!headers_sent()) {
            header('Location: /flowmaker/builder/' . (int) $flow['id']);
        }
        exit;
    }
}
