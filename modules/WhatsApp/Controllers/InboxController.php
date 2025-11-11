<?php

namespace Modules\WhatsApp\Controllers;

use Core\BaseController;
use Modules\WhatsApp\Repositories\InboxRepository;
use PDO;

use function array_reverse;

class InboxController extends BaseController
{
    private InboxRepository $repository;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->repository = new InboxRepository($pdo);
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requirePermission(['whatsapp.manage', 'settings.manage', 'administrativo']);

        $since = isset($_GET['since']) ? (int) $_GET['since'] : 0;
        $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
        if ($limit <= 0 || $limit > 200) {
            $limit = 50;
        }

        if ($since > 0) {
            $messages = $this->repository->fetchSince($since, $limit);
        } else {
            $messages = $this->repository->fetchRecent($limit);
            $messages = array_reverse($messages);
        }

        $this->json([
            'ok' => true,
            'messages' => $messages,
        ]);
    }
}
