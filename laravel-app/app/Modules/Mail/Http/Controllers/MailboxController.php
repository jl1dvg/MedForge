<?php

namespace App\Modules\Mail\Http\Controllers;

use App\Modules\Mail\Services\MailboxService;
use App\Modules\Mail\Services\MailProfileService;
use App\Modules\Mail\Services\NotificationMailer;
use App\Modules\Shared\Support\LegacyCurrentUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Throwable;

class MailboxController
{
    private function pdo(): PDO
    {
        return DB::connection()->getPdo();
    }

    private function mailboxService(): MailboxService
    {
        return new MailboxService($this->pdo());
    }

    public function index(Request $request): View|RedirectResponse
    {
        if (!Auth::check()) {
            return redirect('/auth/login?auth_required=1');
        }

        $mailbox = $this->mailboxService();
        $config = $mailbox->getConfig();

        $defaultLimit = (int) ($config['limit'] ?? 50);
        $filters = [
            'limit' => (int) $request->query('limit', (string) $defaultLimit),
            'query' => $request->query('q') !== null ? trim((string) $request->query('q')) : null,
            'sources' => $request->query('source'),
        ];

        $feed = $mailbox->getFeed($filters);

        $flashMessage = $request->session()->pull('flash_mailbox');

        return view('mail.index', [
            'pageTitle' => 'Mailbox',
            'mailbox' => [
                'feed' => $feed,
                'contacts' => $mailbox->getContacts($feed),
                'stats' => $mailbox->getStats($feed),
                'contexts' => $mailbox->buildContextOptions($feed),
                'filters' => $filters,
                'config' => $config,
            ],
            'currentUser' => LegacyCurrentUser::resolve($request),
            'flashMessage' => $flashMessage,
        ]);
    }

    public function feed(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['success' => false, 'error' => 'Sesión expirada'], 401);
        }

        $mailbox = $this->mailboxService();
        $config = $mailbox->getConfig();

        if (!($config['enabled'] ?? true)) {
            return response()->json(['success' => false, 'error' => 'Mailbox desactivado en Configuración.'], 403);
        }

        $defaultLimit = (int) ($config['limit'] ?? 50);
        $filters = [
            'limit' => (int) $request->query('limit', (string) $defaultLimit),
            'query' => $request->query('q') !== null ? trim((string) $request->query('q')) : null,
            'sources' => $request->query('source'),
            'contact' => $request->query('contact') !== null ? trim((string) $request->query('contact')) : null,
        ];

        $feed = $mailbox->getFeed($filters);

        return response()->json([
            'success' => true,
            'data' => [
                'feed' => $feed,
                'contacts' => $mailbox->getContacts($feed),
                'stats' => $mailbox->getStats($feed),
                'contexts' => $mailbox->buildContextOptions($feed),
                'config' => $config,
            ],
        ]);
    }

    public function compose(Request $request): JsonResponse|RedirectResponse
    {
        if (!Auth::check()) {
            return response()->json(['success' => false, 'error' => 'Sesión expirada'], 401);
        }

        $mailbox = $this->mailboxService();
        $config = $mailbox->getConfig();

        if (!($config['enabled'] ?? true)) {
            return $this->composeError($request, 'El Mailbox está desactivado desde Configuración.', 403);
        }

        if (!($config['compose_enabled'] ?? true)) {
            return $this->composeError($request, 'El formulario de notas se encuentra deshabilitado.', 403);
        }

        $payload = $request->isJson() ? (array) $request->json()->all() : $request->all();

        $reference = isset($payload['target_reference']) ? trim((string) $payload['target_reference']) : '';
        if ($reference !== '' && str_contains($reference, ':')) {
            [$payload['target_type'], $payload['target_id']] = explode(':', $reference, 2);
        }

        $targetType = isset($payload['target_type']) ? strtolower(trim((string) $payload['target_type'])) : '';
        $targetId = isset($payload['target_id']) ? (int) $payload['target_id'] : 0;
        $message = $this->resolveMessageBody($payload);

        if ($targetType === '' || $targetId <= 0) {
            return $this->composeError($request, 'Selecciona un destino válido.', 422);
        }

        if ($message === '') {
            return $this->composeError($request, 'El cuerpo del mensaje no puede estar vacío.', 422);
        }

        $pdo = $this->pdo();
        $userId = $this->currentUserId();

        try {
            $link = null;
            $emailContext = null;
            $shouldNotify = $this->shouldNotifyPatient($payload, $message);
            $notificationResult = null;
            switch ($targetType) {
                case 'solicitud':
                    $notaTexto = trim(strip_tags((string) $message));
                    if ($notaTexto !== '') {
                        $stmtNota = $pdo->prepare(
                            'INSERT INTO solicitud_crm_notas (solicitud_id, autor_id, nota, created_at) VALUES (?, ?, ?, NOW())'
                        );
                        $stmtNota->execute([$targetId, $userId, $notaTexto]);
                    }
                    $link = '/solicitudes/' . $targetId . '/crm';
                    $emailContext = null;
                    $stmtCtx = $pdo->prepare(
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
                    $ctxRow = $stmtCtx->fetch(PDO::FETCH_ASSOC);
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
                        $stmtNota = $pdo->prepare(
                            'INSERT INTO examen_crm_notas (examen_id, autor_id, nota) VALUES (:examen_id, :autor_id, :nota)'
                        );
                        $stmtNota->bindValue(':examen_id', $targetId, PDO::PARAM_INT);
                        $stmtNota->bindValue(':autor_id', $userId, $userId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
                        $stmtNota->bindValue(':nota', $notaTexto, PDO::PARAM_STR);
                        $stmtNota->execute();
                    }
                    $link = '/examenes/' . $targetId . '/crm';
                    $stmtCtxEx = $pdo->prepare(
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
                    $rowEx = $stmtCtxEx->fetch(PDO::FETCH_ASSOC);
                    if ($rowEx !== false && $rowEx !== null) {
                        $emailContext = array_filter($rowEx, static fn($v) => $v !== null && $v !== '');
                        if ($emailContext === []) {
                            $emailContext = null;
                        }
                    }
                    break;
                case 'ticket':
                    $this->createTicketMessage($pdo, $targetId, $message, $userId);
                    $link = '/crm?ticket=' . $targetId;
                    break;
                default:
                    throw new RuntimeException('El destino seleccionado no está soportado.');
            }

            if ($emailContext !== null && $shouldNotify) {
                $notificationResult = $this->notifyPatient($pdo, $emailContext, $targetType, $targetId, $message);
            }

            if ($targetType === 'examen' && is_array($notificationResult)) {
                $this->registrarExamenMailEvent($pdo, $targetId, $emailContext, $notificationResult, $userId);
            }

            return $this->composeSuccess($request, 'Mensaje registrado correctamente.', $link);
        } catch (Throwable $exception) {
            return $this->composeError($request, $exception->getMessage() ?: 'No se pudo registrar el mensaje.', 500);
        }
    }

    private function createTicketMessage(PDO $pdo, int $ticketId, string $message, ?int $userId): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO crm_ticket_messages (ticket_id, author_id, message) VALUES (:ticket_id, :author_id, :message)'
        );
        $stmt->execute([
            ':ticket_id' => $ticketId,
            ':author_id' => $userId,
            ':message' => $message,
        ]);
    }

    private function composeSuccess(Request $request, string $message, ?string $link = null): JsonResponse|RedirectResponse
    {
        if ($this->wantsJson($request)) {
            $payload = ['success' => true, 'message' => $message];
            if ($link) {
                $payload['redirect'] = $link;
            }

            return response()->json($payload);
        }

        $request->session()->put('flash_mailbox', $message);

        return redirect('/mailbox');
    }

    /**
     * @param array{name?:string,email?:string,hc_number?:string,form_id?:string,descripcion?:string}|null $context
     * @return array<string, mixed>
     */
    private function notifyPatient(PDO $pdo, ?array $context, string $targetType, int $targetId, string $body): array
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
            $profileService = new MailProfileService($pdo);
            $profileSlug = $profileService->getProfileSlugForContext('crm');
            $mailer = new NotificationMailer($pdo, $profileSlug);
            $result = $mailer->sendPatientUpdate(
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
    private function registrarExamenMailEvent(PDO $pdo, int $examenId, ?array $context, array $notification, ?int $userId): void
    {
        if (!($notification['attempted'] ?? false)) {
            return;
        }

        $toEmail = trim((string) ($notification['to_email'] ?? ($context['email'] ?? '')));
        if ($toEmail === '') {
            return;
        }

        try {
            $stmtLog = $pdo->prepare(
                "INSERT INTO examen_mail_log
                    (examen_id, form_id, hc_number, to_emails, subject, body_text, channel, sent_by_user_id, status, error_message, sent_at)
                 VALUES
                    (:examen_id, :form_id, :hc_number, :to_emails, :subject, :body_text, :channel, :sent_by_user_id, :status, :error_message, :sent_at)"
            );
            $stmtLog->bindValue(':examen_id', $examenId, PDO::PARAM_INT);
            $bindNullStr = static function (string $k, mixed $v) use ($stmtLog): void {
                $stmtLog->bindValue($k, ($v !== null && $v !== '') ? (string) $v : null,
                    ($v !== null && $v !== '') ? PDO::PARAM_STR : PDO::PARAM_NULL);
            };
            $bindNullStr(':form_id', $context['form_id'] ?? null);
            $bindNullStr(':hc_number', $context['hc_number'] ?? null);
            $stmtLog->bindValue(':to_emails', $toEmail, PDO::PARAM_STR);
            $stmtLog->bindValue(':subject', $notification['subject'] ?? ('Actualización de Examen #' . $examenId), PDO::PARAM_STR);
            $bindNullStr(':body_text', $notification['body_text'] ?? null);
            $stmtLog->bindValue(':channel', $notification['channel'] ?? 'email', PDO::PARAM_STR);
            $stmtLog->bindValue(':sent_by_user_id', $userId, $userId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmtLog->bindValue(':status', $notification['status'] ?? 'failed', PDO::PARAM_STR);
            $bindNullStr(':error_message', $notification['error'] ?? null);
            $bindNullStr(':sent_at', $notification['sent_at'] ?? null);
            $stmtLog->execute();
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

    private function composeError(Request $request, string $message, int $status): JsonResponse|RedirectResponse
    {
        if ($this->wantsJson($request)) {
            return response()->json(['success' => false, 'error' => $message], $status);
        }

        $request->session()->put('flash_mailbox', $message);

        return redirect('/mailbox');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolveMessageBody(array $payload): string
    {
        foreach (['message', 'body', 'nota', 'content'] as $key) {
            if (isset($payload[$key]) && trim((string) $payload[$key]) !== '') {
                return trim((string) $payload[$key]);
            }
        }

        return '';
    }

    private function wantsJson(Request $request): bool
    {
        if ($request->expectsJson()) {
            return true;
        }

        $accept = (string) $request->header('Accept', '');
        if (stripos($accept, 'application/json') !== false) {
            return true;
        }

        return strtolower((string) $request->header('X-Requested-With', '')) === 'xmlhttprequest';
    }

    private function currentUserId(): ?int
    {
        $authId = Auth::id();
        if (is_numeric($authId)) {
            return (int) $authId;
        }

        $sessionId = session('user_id');

        return is_numeric($sessionId) ? (int) $sessionId : null;
    }
}
