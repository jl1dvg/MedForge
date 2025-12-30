<?php

namespace Modules\Mail\Controllers;

use Core\BaseController;
use Modules\Examenes\Services\ExamenCrmService;
use Modules\Mail\Services\MailboxService;
use Modules\Mail\Services\NotificationMailer;
use Modules\Solicitudes\Services\SolicitudCrmService;
use PDO;
use RuntimeException;
use Throwable;

class MailboxController extends BaseController
{
    private MailboxService $mailbox;
    private SolicitudCrmService $solicitudCrm;
    private ExamenCrmService $examenCrm;
    private NotificationMailer $mailer;
    /** @var array<string, mixed> */
    private array $mailboxConfig = [];
    private ?array $bodyCache = null;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->mailbox = new MailboxService($pdo);
        $this->solicitudCrm = new SolicitudCrmService($pdo);
        $this->examenCrm = new ExamenCrmService($pdo);
        $this->mailer = new NotificationMailer($pdo);
        $this->mailboxConfig = $this->mailbox->getConfig();
    }

    public function index(): void
    {
        $this->requireAuth();

        $defaultLimit = (int) ($this->mailboxConfig['limit'] ?? 50);
        $filters = [
            'limit' => isset($_GET['limit']) ? (int) $_GET['limit'] : $defaultLimit,
            'query' => isset($_GET['q']) ? trim((string) $_GET['q']) : null,
            'sources' => $_GET['source'] ?? null,
        ];

        $feed = $this->mailbox->getFeed($filters);

        $data = [
            'pageTitle' => 'Mailbox',
            'mailbox' => [
                'feed' => $feed,
                'contacts' => $this->mailbox->getContacts($feed),
                'stats' => $this->mailbox->getStats($feed),
                'contexts' => $this->mailbox->buildContextOptions($feed),
                'filters' => $filters,
                'config' => $this->mailboxConfig,
            ],
            'scripts' => ['js/pages/mailbox.js'],
        ];

        if (isset($_SESSION['flash_mailbox'])) {
            $data['flashMessage'] = $_SESSION['flash_mailbox'];
            unset($_SESSION['flash_mailbox']);
        }

        $this->render(__DIR__ . '/../views/index.php', $data);
    }

    public function feed(): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'error' => 'Sesión expirada'], 401);
            return;
        }

        if (!($this->mailboxConfig['enabled'] ?? true)) {
            $this->json(['success' => false, 'error' => 'Mailbox desactivado en Configuración.'], 403);
            return;
        }

        $defaultLimit = (int) ($this->mailboxConfig['limit'] ?? 50);
        $filters = [
            'limit' => isset($_GET['limit']) ? (int) $_GET['limit'] : $defaultLimit,
            'query' => isset($_GET['q']) ? trim((string) $_GET['q']) : null,
            'sources' => $_GET['source'] ?? null,
            'contact' => isset($_GET['contact']) ? trim((string) $_GET['contact']) : null,
        ];

        $feed = $this->mailbox->getFeed($filters);

        $this->json([
            'success' => true,
            'data' => [
                'feed' => $feed,
                'contacts' => $this->mailbox->getContacts($feed),
                'stats' => $this->mailbox->getStats($feed),
                'contexts' => $this->mailbox->buildContextOptions($feed),
                'config' => $this->mailboxConfig,
            ],
        ]);
    }

    public function compose(): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'error' => 'Sesión expirada'], 401);
            return;
        }

        if (!($this->mailboxConfig['enabled'] ?? true)) {
            $this->respondComposeError('El Mailbox está desactivado desde Configuración.', 403);
            return;
        }

        if (!($this->mailboxConfig['compose_enabled'] ?? true)) {
            $this->respondComposeError('El formulario de notas se encuentra deshabilitado.', 403);
            return;
        }

        $payload = $this->getRequestBody();
        $reference = isset($payload['target_reference']) ? trim((string) $payload['target_reference']) : '';
        if ($reference !== '' && str_contains($reference, ':')) {
            [$payload['target_type'], $payload['target_id']] = explode(':', $reference, 2);
        }

        $targetType = isset($payload['target_type']) ? strtolower(trim((string) $payload['target_type'])) : '';
        $targetId = isset($payload['target_id']) ? (int) $payload['target_id'] : 0;
        $message = $this->resolveMessageBody($payload);

        if ($targetType === '' || $targetId <= 0) {
            $this->respondComposeError('Selecciona un destino válido.', 422);
            return;
        }

        if ($message === '') {
            $this->respondComposeError('El cuerpo del mensaje no puede estar vacío.', 422);
            return;
        }

        try {
            $link = null;
            $emailContext = null;
            $shouldNotify = $this->shouldNotifyPatient($payload, $message);
            switch ($targetType) {
                case 'solicitud':
                    $this->solicitudCrm->registrarNota($targetId, $message, $this->getCurrentUserId());
                    $link = '/solicitudes/' . $targetId . '/crm';
                    $emailContext = $this->solicitudCrm->obtenerContactoPaciente($targetId);
                    break;
                case 'examen':
                    $this->examenCrm->registrarNota($targetId, $message, $this->getCurrentUserId());
                    $link = '/examenes/' . $targetId . '/crm';
                    $emailContext = $this->examenCrm->obtenerContactoPaciente($targetId);
                    break;
                case 'ticket':
                    $this->createTicketMessage($targetId, $message);
                    $link = '/crm?ticket=' . $targetId;
                    break;
                default:
                    throw new RuntimeException('El destino seleccionado no está soportado.');
            }

            if ($emailContext !== null && $shouldNotify) {
                $this->notifyPatient($emailContext, $targetType, $targetId, $message);
            }

            $this->respondComposeSuccess('Mensaje registrado correctamente.', $link);
        } catch (Throwable $exception) {
            $this->respondComposeError($exception->getMessage() ?: 'No se pudo registrar el mensaje.', 500);
        }
    }

    private function createTicketMessage(int $ticketId, string $message): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO crm_ticket_messages (ticket_id, author_id, message) VALUES (:ticket_id, :author_id, :message)'
        );
        $stmt->execute([
            ':ticket_id' => $ticketId,
            ':author_id' => $this->getCurrentUserId(),
            ':message' => $message,
        ]);
    }

    private function respondComposeSuccess(string $message, ?string $link = null): void
    {
        if ($this->wantsJson()) {
            $payload = ['success' => true, 'message' => $message];
            if ($link) {
                $payload['redirect'] = $link;
            }
            $this->json($payload);
            return;
        }

        $_SESSION['flash_mailbox'] = $message;
        header('Location: /mailbox');
        exit;
    }

    /**
     * @param array{name?:string,email?:string,hc_number?:string,descripcion?:string}|null $context
     */
    private function notifyPatient(?array $context, string $targetType, int $targetId, string $body): void
    {
        $email = trim((string) ($context['email'] ?? ''));
        if ($email === '') {
            return;
        }

        $cleanBody = $this->stripPatientPrefix($body);

        $subjectParts = ['Actualización de ' . ucfirst($targetType) . ' #' . $targetId];
        if (!empty($context['descripcion'])) {
            $subjectParts[] = (string) $context['descripcion'];
        }

        $subject = implode(' · ', $subjectParts);

        $greeting = !empty($context['name'])
            ? 'Hola ' . $context['name'] . ','
            : 'Hola,';

        $messageLines = [$greeting, 'Tenemos una nueva actualización sobre tu caso:', '', $cleanBody];

        if (!empty($context['hc_number'])) {
            $messageLines[] = 'Historia clínica: ' . $context['hc_number'];
        }

        try {
            $this->mailer->sendPatientUpdate($email, $subject, implode("\n", $messageLines));
        } catch (Throwable $exception) {
            error_log('No se pudo notificar al paciente (' . $targetType . ' #' . $targetId . ' a ' . $email . '): ' . $exception->getMessage());
        }
    }

    private function shouldNotifyPatient(array $payload, string $message): bool
    {
        if (array_key_exists('notify_patient', $payload)) {
            return filter_var($payload['notify_patient'], FILTER_VALIDATE_BOOLEAN) === true;
        }

        $normalized = ltrim($message);

        return preg_match('/^\[?paciente\]?:?/i', $normalized) === 1;
    }

    private function stripPatientPrefix(string $message): string
    {
        $stripped = preg_replace('/^\s*\[?paciente\]?:?\s*/i', '', $message, 1);

        return $stripped !== null ? $stripped : $message;
    }

    private function respondComposeError(string $message, int $status): void
    {
        if ($this->wantsJson()) {
            $this->json(['success' => false, 'error' => $message], $status);
            return;
        }

        $_SESSION['flash_mailbox'] = $message;
        header('Location: /mailbox');
        exit;
    }

    /**
     * @return array<string, mixed>
     */
    private function getRequestBody(): array
    {
        if ($this->bodyCache !== null) {
            return $this->bodyCache;
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $decoded = json_decode(file_get_contents('php://input'), true);
            $this->bodyCache = is_array($decoded) ? $decoded : [];
            return $this->bodyCache;
        }

        if (!empty($_POST)) {
            $this->bodyCache = $_POST;
            return $this->bodyCache;
        }

        $decoded = json_decode(file_get_contents('php://input'), true);
        $this->bodyCache = is_array($decoded) ? $decoded : [];

        return $this->bodyCache;
    }

    private function resolveMessageBody(array $payload): string
    {
        foreach (['message', 'body', 'nota', 'content'] as $key) {
            if (isset($payload[$key]) && trim((string) $payload[$key]) !== '') {
                return trim((string) $payload[$key]);
            }
        }

        return '';
    }

    private function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

        if (stripos($accept, 'application/json') !== false) {
            return true;
        }

        return strtolower($requestedWith) === 'xmlhttprequest';
    }

    private function getCurrentUserId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }
}
