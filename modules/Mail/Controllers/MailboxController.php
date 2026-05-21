<?php

namespace Modules\Mail\Controllers;

use Core\BaseController;
use Modules\Examenes\Services\ExamenMailLogService;
use Modules\Mail\Services\MailboxService;
use Modules\Mail\Services\NotificationMailer;
use Modules\Mail\Services\MailProfileService;
use PDO;
use RuntimeException;
use Throwable;

class MailboxController extends BaseController
{
    private MailboxService $mailbox;
    private ExamenMailLogService $examenMailLog;
    private NotificationMailer $mailer;
    /** @var array<string, mixed> */
    private array $mailboxConfig = [];
    private ?array $bodyCache = null;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->mailbox = new MailboxService($pdo);
        $this->examenMailLog = new ExamenMailLogService($pdo);
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
            $notificationResult = null;
            switch ($targetType) {
                case 'solicitud':
                    $notaTexto = trim(strip_tags((string) $message));
                    if ($notaTexto !== '') {
                        $stmtNota = $this->pdo->prepare(
                            'INSERT INTO solicitud_crm_notas (solicitud_id, autor_id, nota, created_at) VALUES (?, ?, ?, NOW())'
                        );
                        $stmtNota->execute([$targetId, $this->getCurrentUserId(), $notaTexto]);
                    }
                    $link = '/solicitudes/' . $targetId . '/crm';
                    $emailContext = null;
                    $stmtCtx = $this->pdo->prepare(
                        "SELECT CONCAT(TRIM(pd.fname), ' ', TRIM(pd.lname)) AS name,
                                scd.contacto_email AS email,
                                sp.hc_number,
                                sp.procedimiento AS descripcion
                         FROM solicitud_procedimiento sp
                         LEFT JOIN patient_data pd ON pd.hc_number = sp.hc_number
                         LEFT JOIN solicitud_crm_detalles scd ON scd.solicitud_id = sp.id
                         WHERE sp.id = ?
                         LIMIT 1"
                    );
                    $stmtCtx->execute([$targetId]);
                    $ctxRow = $stmtCtx->fetch(\PDO::FETCH_ASSOC);
                    if ($ctxRow !== false && $ctxRow !== null) {
                        $emailContext = array_filter($ctxRow, static fn($v) => $v !== null && $v !== '');
                        if ($emailContext === []) {
                            $emailContext = null;
                        }
                    }
                    break;
                case 'examen':
                    $notaTexto = trim(strip_tags($message));
                    if ($notaTexto !== '') {
                        $stmtNota = $this->pdo->prepare(
                            'INSERT INTO examen_crm_notas (examen_id, autor_id, nota) VALUES (:examen_id, :autor_id, :nota)'
                        );
                        $stmtNota->bindValue(':examen_id', $targetId, \PDO::PARAM_INT);
                        $autorId = $this->getCurrentUserId();
                        $stmtNota->bindValue(':autor_id', $autorId, $autorId !== null ? \PDO::PARAM_INT : \PDO::PARAM_NULL);
                        $stmtNota->bindValue(':nota', $notaTexto, \PDO::PARAM_STR);
                        $stmtNota->execute();
                    }
                    $link = '/examenes/' . $targetId . '/crm';
                    $stmtCtxEx = $this->pdo->prepare(
                        "SELECT
                            CONCAT(TRIM(pd.fname), ' ', TRIM(pd.mname), ' ', TRIM(pd.lname), ' ', TRIM(pd.lname2)) AS name,
                            detalles.contacto_email AS email,
                            ce.hc_number,
                            ce.form_id,
                            ce.examen_nombre AS descripcion
                         FROM consulta_examenes ce
                         INNER JOIN patient_data pd ON ce.hc_number = pd.hc_number
                         LEFT JOIN examen_crm_detalles detalles ON detalles.examen_id = ce.id
                         WHERE ce.id = ?
                         LIMIT 1"
                    );
                    $stmtCtxEx->execute([$targetId]);
                    $rowEx = $stmtCtxEx->fetch(\PDO::FETCH_ASSOC);
                    if ($rowEx !== false && $rowEx !== null) {
                        $emailContext = array_filter($rowEx, static fn($v) => $v !== null && $v !== '');
                        if ($emailContext === []) {
                            $emailContext = null;
                        }
                    }
                    break;
                case 'ticket':
                    $this->createTicketMessage($targetId, $message);
                    $link = '/crm?ticket=' . $targetId;
                    break;
                default:
                    throw new RuntimeException('El destino seleccionado no está soportado.');
            }

            if ($emailContext !== null && $shouldNotify) {
                $notificationResult = $this->notifyPatient($emailContext, $targetType, $targetId, $message);
            }

            if ($targetType === 'examen' && is_array($notificationResult)) {
                $this->registrarExamenMailEvent($targetId, $emailContext, $notificationResult);
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
     * @param array{name?:string,email?:string,hc_number?:string,form_id?:string,descripcion?:string}|null $context
     * @return array<string, mixed>
     */
    private function notifyPatient(?array $context, string $targetType, int $targetId, string $body): array
    {
        $email = trim((string) ($context['email'] ?? ''));
        if ($email === '') {
            return ['attempted' => false];
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

        $bodyText = implode("\n", $messageLines);
        $resultPayload = [
            'attempted' => true,
            'channel' => 'email',
            'to_email' => $email,
            'subject' => $subject,
            'body_text' => $bodyText,
            'status' => 'failed',
            'error' => null,
            'sent_at' => null,
        ];

        try {
            $profileService = new MailProfileService($this->pdo);
            $profileSlug = $profileService->getProfileSlugForContext('crm');
            $result = $this->mailer->sendPatientUpdate(
                $email,
                $subject,
                $bodyText,
                [],
                [],
                false,
                $profileSlug
            );

            if (($result['success'] ?? false) === true) {
                $resultPayload['status'] = 'sent';
                $resultPayload['sent_at'] = date('Y-m-d H:i:s');
            } else {
                $message = $result['error'] ?? 'No se pudo enviar el correo de notificación';
                $resultPayload['error'] = (string) $message;
                error_log('No se pudo notificar al paciente (' . $targetType . ' #' . $targetId . ' a ' . $email . '): ' . $message);
            }
        } catch (Throwable $exception) {
            $resultPayload['error'] = $exception->getMessage();
            error_log('No se pudo notificar al paciente (' . $targetType . ' #' . $targetId . ' a ' . $email . '): ' . $exception->getMessage());
        }

        return $resultPayload;
    }

    /**
     * @param array{name?:string,email?:string,hc_number?:string,form_id?:string,descripcion?:string}|null $context
     * @param array<string, mixed> $notification
     */
    private function registrarExamenMailEvent(int $examenId, ?array $context, array $notification): void
    {
        if (!($notification['attempted'] ?? false)) {
            return;
        }

        $toEmail = trim((string) ($notification['to_email'] ?? ($context['email'] ?? '')));
        if ($toEmail === '') {
            return;
        }

        try {
            $this->examenMailLog->create([
                'examen_id' => $examenId,
                'form_id' => $context['form_id'] ?? null,
                'hc_number' => $context['hc_number'] ?? null,
                'to_emails' => $toEmail,
                'subject' => $notification['subject'] ?? ('Actualización de Examen #' . $examenId),
                'body_text' => $notification['body_text'] ?? null,
                'channel' => $notification['channel'] ?? 'email',
                'sent_by_user_id' => $this->getCurrentUserId(),
                'status' => $notification['status'] ?? 'failed',
                'error_message' => $notification['error'] ?? null,
                'sent_at' => $notification['sent_at'] ?? null,
            ]);
        } catch (Throwable $exception) {
            error_log('No se pudo registrar examen_mail_log para examen #' . $examenId . ': ' . $exception->getMessage());
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
