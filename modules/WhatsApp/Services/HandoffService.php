<?php

namespace Modules\WhatsApp\Services;

use Core\Permissions;
use DateTimeImmutable;
use Modules\Notifications\Services\PusherConfigService;
use Modules\WhatsApp\Config\WhatsAppSettings;
use Modules\WhatsApp\Repositories\ConversationRepository;
use Modules\WhatsApp\Repositories\HandoffRepository;
use Modules\WhatsApp\Support\PhoneNumberFormatter;
use PDO;
use Throwable;

class HandoffService
{
    private const DEFAULT_TTL_HOURS = 24;

    private PDO $pdo;
    private ConversationRepository $conversations;
    private HandoffRepository $handoffs;
    private WhatsAppSettings $settings;
    private Messenger $messenger;
    private ?PusherConfigService $pusher = null;
    /**
     * @var array<int, string>|null
     */
    private ?array $presenceMap = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->conversations = new ConversationRepository($pdo);
        $this->handoffs = new HandoffRepository($pdo);
        $this->settings = new WhatsAppSettings($pdo);
        $this->messenger = new Messenger($pdo);
        $this->pusher = new PusherConfigService($pdo);
    }

    public function requestHandoff(string $waNumber, ?string $notes = null, ?int $roleId = null, ?string $topic = null, string $priority = 'normal'): ?int
    {
        $normalized = $this->normalizeNumber($waNumber);
        if ($normalized === null) {
            return null;
        }

        $notes = $this->sanitizeNotes($notes);
        $conversationId = $this->conversations->upsertConversation($normalized);
        $active = $this->handoffs->findActiveByConversation($conversationId);
        $resolvedRoleId = $roleId;
        if ($resolvedRoleId === null && $active !== null && isset($active['handoff_role_id'])) {
            $resolvedRoleId = (int) $active['handoff_role_id'];
        }
        if ($resolvedRoleId !== null && $resolvedRoleId <= 0) {
            $resolvedRoleId = null;
        }

        if ($active !== null) {
            $isAssigned = (($active['status'] ?? null) === 'assigned') && !empty($active['assigned_agent_id']);
            $updates = [];
            if ($notes !== null && $notes !== '') {
                $updates['notes'] = $notes;
            }
            if ($roleId !== null && $roleId > 0) {
                $updates['handoff_role_id'] = $roleId;
            }
            if ($topic !== null && trim($topic) !== '') {
                $updates['topic'] = trim($topic);
            }
            if ($priority !== '') {
                $updates['priority'] = $priority;
            }
            if (!empty($updates)) {
                $this->handoffs->update((int) $active['id'], $updates);
            }

            if ($isAssigned) {
                $this->conversations->updateHandoffDetails($conversationId, $notes, $resolvedRoleId);
            } else {
                try {
                    $this->conversations->setHandoffFlag($conversationId, true, $notes, $resolvedRoleId);
                } catch (Throwable $exception) {
                    $this->handoffs->insertEvent((int) $active['id'], 'conversation_update_failed', null, $this->sanitizeNotes($exception->getMessage()));
                }
            }
            $this->handoffs->insertEvent((int) $active['id'], 'requested', null, $notes);
            if (!$isAssigned) {
                $this->dispatchHandoffNotifications($conversationId, $normalized, (int) $active['id'], $resolvedRoleId, $notes);
            }

            return (int) $active['id'];
        }

        $handoffId = $this->handoffs->create([
            'conversation_id' => $conversationId,
            'wa_number' => $normalized,
            'status' => 'queued',
            'priority' => $priority,
            'topic' => $topic,
            'handoff_role_id' => $resolvedRoleId,
            'queued_at' => date('Y-m-d H:i:s'),
            'notes' => $notes,
        ]);

        $this->handoffs->insertEvent($handoffId, 'queued', null, $notes);
        try {
            $this->conversations->setHandoffFlag($conversationId, true, $notes, $resolvedRoleId);
        } catch (Throwable $exception) {
            $this->handoffs->insertEvent($handoffId, 'conversation_update_failed', null, $this->sanitizeNotes($exception->getMessage()));
        }
        $this->dispatchHandoffNotifications($conversationId, $normalized, $handoffId, $resolvedRoleId, $notes);

        return $handoffId;
    }

    private function dispatchHandoffNotifications(int $conversationId, string $waNumber, int $handoffId, ?int $roleId, ?string $notes): void
    {
        $config = $this->settings->get();
        $this->handoffs->insertEvent($handoffId, 'notify_started', null, 'Iniciando notificación de handoff.');

        if (!empty($config['handoff_notify_in_app'])) {
            try {
                $this->notifyHandoff($conversationId);
            } catch (Throwable $exception) {
                $this->handoffs->insertEvent($handoffId, 'notify_realtime_failed', null, $this->sanitizeNotes($exception->getMessage()));
            }
        } else {
            $this->handoffs->insertEvent($handoffId, 'notify_realtime_skipped', null, 'Notificación in-app desactivada en Settings.');
        }

        if (!empty($config['handoff_notify_agents'])) {
            try {
                $this->notifyAgents($waNumber, $handoffId, $roleId, $notes, $conversationId);
            } catch (Throwable $exception) {
                $this->handoffs->insertEvent($handoffId, 'notify_failed', null, 'Error interno al notificar agentes: ' . $this->sanitizeNotes($exception->getMessage()));
            }
        } else {
            $this->handoffs->insertEvent($handoffId, 'notify_whatsapp_skipped', null, 'Notificación por WhatsApp personal desactivada.');
        }
    }

    public function assignConversation(int $conversationId, int $agentId, ?int $actorId = null): bool
    {
        if ($conversationId <= 0 || $agentId <= 0) {
            return false;
        }

        $conversation = $this->conversations->findConversationById($conversationId);
        if ($conversation === null) {
            return false;
        }

        $active = $this->handoffs->findActiveByConversation($conversationId);
        $assignedUntil = $this->resolveAssignedUntil();

        if ($active === null) {
            $handoffId = $this->handoffs->create([
                'conversation_id' => $conversationId,
                'wa_number' => $conversation['wa_number'],
                'status' => 'assigned',
                'handoff_role_id' => $conversation['handoff_role_id'] ?? null,
                'assigned_agent_id' => $agentId,
                'assigned_at' => date('Y-m-d H:i:s'),
                'assigned_until' => $assignedUntil,
                'queued_at' => date('Y-m-d H:i:s'),
                'notes' => $this->sanitizeNotes($conversation['handoff_notes'] ?? null),
            ]);
            $this->handoffs->insertEvent($handoffId, 'assigned', $actorId ?? $agentId, 'Asignación manual');
            $this->conversations->assignConversation($conversationId, $agentId);

            return true;
        }

        $activeId = (int) $active['id'];
        $currentAgent = isset($active['assigned_agent_id']) ? (int) $active['assigned_agent_id'] : null;
        if ($active['status'] === 'assigned' && $currentAgent !== null) {
            return $currentAgent === $agentId;
        }

        $assigned = $this->handoffs->assign($activeId, $agentId, $assignedUntil);
        if ($assigned) {
            $this->handoffs->insertEvent($activeId, 'assigned', $actorId ?? $agentId, null);
            $this->conversations->assignConversation($conversationId, $agentId);
        }

        return $assigned;
    }

    public function transferConversation(int $conversationId, int $agentId, ?string $note = null, ?int $actorId = null): bool
    {
        if ($conversationId <= 0 || $agentId <= 0) {
            return false;
        }

        $conversation = $this->conversations->findConversationById($conversationId);
        if ($conversation === null) {
            return false;
        }

        $assignedUntil = $this->resolveAssignedUntil();
        $note = $this->sanitizeNotes($note);
        $active = $this->handoffs->findActiveByConversation($conversationId);

        if ($active === null) {
            $handoffId = $this->handoffs->create([
                'conversation_id' => $conversationId,
                'wa_number' => $conversation['wa_number'],
                'status' => 'assigned',
                'handoff_role_id' => $conversation['handoff_role_id'] ?? null,
                'assigned_agent_id' => $agentId,
                'assigned_at' => date('Y-m-d H:i:s'),
                'assigned_until' => $assignedUntil,
                'queued_at' => date('Y-m-d H:i:s'),
                'notes' => $note,
            ]);
            $this->handoffs->insertEvent($handoffId, 'transferred', $actorId, $note);
            $this->conversations->transferConversation($conversationId, $agentId, $note);

            return true;
        }

        $handoffId = (int) $active['id'];
        $ok = $this->handoffs->transfer($handoffId, $agentId, $assignedUntil, $note);
        if ($ok) {
            $this->handoffs->insertEvent($handoffId, 'transferred', $actorId, $note);
            $this->conversations->transferConversation($conversationId, $agentId, $note);
        }

        return $ok;
    }

    public function resolveConversation(int $conversationId, ?int $actorId = null, ?string $note = null): bool
    {
        if ($conversationId <= 0) {
            return false;
        }

        $active = $this->handoffs->findActiveByConversation($conversationId);
        if ($active !== null) {
            $handoffId = (int) $active['id'];
            $this->handoffs->markResolved($handoffId, $this->sanitizeNotes($note));
            $this->handoffs->insertEvent($handoffId, 'resolved', $actorId, $note);
        }

        $this->conversations->clearHandoff($conversationId);

        return true;
    }

    /**
     * @return array{count:int,ids:array<int,int>}
     */
    public function requeueExpired(): array
    {
        $cutoff = date('Y-m-d H:i:s');
        $expired = $this->handoffs->findExpired($cutoff);
        $count = 0;
        $ids = [];

        foreach ($expired as $handoff) {
            $handoffId = (int) ($handoff['id'] ?? 0);
            if ($handoffId <= 0) {
                continue;
            }

            if (!$this->handoffs->requeue($handoffId)) {
                continue;
            }

            $count++;
            $ids[] = $handoffId;
            $note = $this->sanitizeNotes($handoff['notes'] ?? null);
            $this->handoffs->insertEvent($handoffId, 'expired', null, 'TTL vencido');
            $this->handoffs->insertEvent($handoffId, 'requeued', null, $note);

            $conversationId = isset($handoff['conversation_id']) ? (int) $handoff['conversation_id'] : 0;
            if ($conversationId > 0) {
                $roleId = isset($handoff['handoff_role_id']) ? (int) $handoff['handoff_role_id'] : null;
                $this->conversations->setHandoffFlag($conversationId, true, $note, $roleId);
                $this->notifyHandoff($conversationId);
                $waNumber = $handoff['wa_number'] ?? null;
                if (is_string($waNumber) && $waNumber !== '') {
                    $this->notifyAgents($waNumber, $handoffId, $roleId, $note, $conversationId);
                }
            }
        }

        return ['count' => $count, 'ids' => $ids];
    }

    public function handleAgentReply(string $sender, ?string $text): bool
    {
        $agent = $this->findAgentByWhatsapp($sender);
        if ($agent === null) {
            return false;
        }

        $text = $text !== null ? trim($text) : '';
        if ($text === '') {
            return true;
        }

        if (preg_match('/^TOMAR[\s_:-]*(\d+)$/iu', $text, $matches)) {
            $handoffId = (int) $matches[1];
            $this->processAgentClaim($agent, $handoffId);

            return true;
        }

        if (preg_match('/^IGNORAR[\s_:-]*(\d+)$/iu', $text, $matches)) {
            $handoffId = (int) $matches[1];
            $this->processAgentIgnore($agent, $handoffId);

            return true;
        }

        $this->sendAgentMessage($agent, 'Este número se usa solo para tomar handoffs. Usa los botones enviados por el sistema.');

        return true;
    }

    private function processAgentClaim(array $agent, int $handoffId): void
    {
        if ($handoffId <= 0) {
            $this->sendAgentMessage($agent, 'No se encontró la solicitud indicada.');

            return;
        }

        $handoff = $this->handoffs->findById($handoffId);
        if ($handoff === null) {
            $this->sendAgentMessage($agent, 'La solicitud ya no está disponible.');

            return;
        }

        $status = $handoff['status'] ?? null;
        $assignedAgentId = isset($handoff['assigned_agent_id']) ? (int) $handoff['assigned_agent_id'] : null;
        $agentId = (int) ($agent['id'] ?? 0);

        if ($status === 'assigned' && $assignedAgentId !== null) {
            if ($assignedAgentId === $agentId) {
                $this->sendAgentMessage($agent, 'Ya tienes este chat asignado.');

                return;
            }

            $assignedName = $this->resolveAgentName($assignedAgentId) ?? 'otro agente';
            $this->sendAgentMessage($agent, 'Este chat ya fue tomado por ' . $assignedName . '.');

            return;
        }

        if ($status !== 'queued') {
            $this->sendAgentMessage($agent, 'La solicitud ya no está disponible.');

            return;
        }

        $assignedUntil = $this->resolveAssignedUntil();
        if ($this->handoffs->assign($handoffId, $agentId, $assignedUntil)) {
            $this->handoffs->insertEvent($handoffId, 'assigned', $agentId, 'Asignado desde WhatsApp');
            $conversationId = isset($handoff['conversation_id']) ? (int) $handoff['conversation_id'] : 0;
            if ($conversationId > 0) {
                $this->conversations->assignConversation($conversationId, $agentId);
            }

            $label = $this->resolveConversationLabel($conversationId, $handoff['wa_number'] ?? null);
            $this->sendAgentMessage($agent, 'Listo ✅ Tomaste el chat de ' . $label . '.');

            return;
        }

        $fresh = $this->handoffs->findById($handoffId);
        if ($fresh !== null && !empty($fresh['assigned_agent_id'])) {
            $assignedName = $this->resolveAgentName((int) $fresh['assigned_agent_id']) ?? 'otro agente';
            $this->sendAgentMessage($agent, 'Este chat ya fue tomado por ' . $assignedName . '.');
        } else {
            $this->sendAgentMessage($agent, 'No fue posible tomar el chat. Intenta nuevamente.');
        }
    }

    private function processAgentIgnore(array $agent, int $handoffId): void
    {
        if ($handoffId <= 0) {
            $this->sendAgentMessage($agent, 'No se encontró la solicitud indicada.');

            return;
        }

        $handoff = $this->handoffs->findById($handoffId);
        if ($handoff === null) {
            $this->sendAgentMessage($agent, 'La solicitud ya no está disponible.');

            return;
        }

        $agentId = (int) ($agent['id'] ?? 0);
        $this->handoffs->insertEvent($handoffId, 'ignored', $agentId, 'Ignorado desde WhatsApp');
        $this->sendAgentMessage($agent, 'Solicitud ignorada.');
    }

    private function notifyAgents(string $waNumber, int $handoffId, ?int $roleId = null, ?string $notes = null, ?int $conversationId = null): void
    {
        $config = $this->settings->get();

        $selection = $this->listNotifiableAgentsWithDiagnostics($roleId);
        $agents = $selection['agents'];
        $this->handoffs->insertEvent(
            $handoffId,
            'notify_selection',
            null,
            $selection['note']
        );
        if (empty($agents)) {
            $this->handoffs->insertEvent($handoffId, 'notify_skipped', null, 'Sin agentes notificables para este rol.');

            return;
        }

        $label = $this->resolveConversationLabel($conversationId, $waNumber);
        $message = $this->buildAgentMessage($label, $notes, $handoffId, $config);

        $takeLabel = $this->resolveButtonLabel($config['handoff_button_take_label'] ?? 'Tomar', 'Tomar');
        $ignoreLabel = $this->resolveButtonLabel($config['handoff_button_ignore_label'] ?? 'Ignorar', 'Ignorar');

        $buttons = [
            ['id' => 'TOMAR_' . $handoffId, 'title' => $takeLabel],
            ['id' => 'IGNORAR_' . $handoffId, 'title' => $ignoreLabel],
        ];

        $recipients = [];
        foreach ($agents as $agent) {
            if (empty($agent['whatsapp_number'])) {
                continue;
            }
            $recipients[] = $agent['whatsapp_number'];
        }

        if (empty($recipients)) {
            $this->handoffs->insertEvent($handoffId, 'notify_skipped', null, 'Agentes sin número de WhatsApp configurado.');

            return;
        }

        $sent = $this->messenger->sendInteractiveButtons($recipients, $message, $buttons, [
            'skip_record' => true,
        ]);

        if ($sent) {
            $this->handoffs->insertEvent($handoffId, 'notified', null, 'Notificado a ' . count($recipients) . ' agentes.');

            return;
        }

        $error = $this->messenger->getLastTransportError();
        $note = $this->formatTransportError($error);
        $this->handoffs->insertEvent($handoffId, 'notify_failed', null, $note);
    }

    /**
     * @return array{agents: array<int, array{id:int,name:string,whatsapp_number:string,role_id:int|null,presence_status:string}>, note:string}
     */
    private function listNotifiableAgentsWithDiagnostics(?int $roleId = null): array
    {
        $sql = 'SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.nombre, u.permisos, u.role_id, u.whatsapp_number, u.whatsapp_notify, r.permissions AS role_permissions ' .
            'FROM users u LEFT JOIN roles r ON r.id = u.role_id';
        $params = [];
        if ($roleId !== null && $roleId > 0) {
            $sql .= ' WHERE u.role_id = :role_id';
            $params[':role_id'] = $roleId;
        }
        $sql .= ' ORDER BY u.first_name, u.last_name, u.username';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!is_array($rows)) {
            return [
                'agents' => [],
                'note' => 'No se pudo obtener usuarios candidatos para notificación.',
            ];
        }

        $agents = [];
        $excludedNoPermission = 0;
        $excludedNoNumber = 0;
        $excludedNotifyDisabled = 0;
        $excludedInvalidNumber = 0;
        $excludedUnavailable = 0;
        foreach ($rows as $row) {
            $permissions = Permissions::merge($row['permisos'] ?? [], $row['role_permissions'] ?? []);
            if (!Permissions::containsAny($permissions, ['whatsapp.chat.send', 'whatsapp.manage'])) {
                $excludedNoPermission++;
                continue;
            }

            $whatsappNumber = isset($row['whatsapp_number']) ? trim((string) $row['whatsapp_number']) : '';
            $notify = isset($row['whatsapp_notify']) ? (int) $row['whatsapp_notify'] : 0;
            if ($notify !== 1) {
                $excludedNotifyDisabled++;
                continue;
            }

            if ($whatsappNumber === '') {
                $excludedNoNumber++;
                continue;
            }

            $normalizedNumber = $this->normalizeNumber($whatsappNumber);
            if ($normalizedNumber === null) {
                $excludedInvalidNumber++;
                continue;
            }

            $presenceStatus = $this->resolvePresenceStatus((int) $row['id']);
            if ($presenceStatus !== 'available') {
                $excludedUnavailable++;
                continue;
            }

            $name = $this->buildUserName($row);
            $agents[] = [
                'id' => (int) $row['id'],
                'name' => $name,
                'whatsapp_number' => $normalizedNumber,
                'role_id' => isset($row['role_id']) ? (int) $row['role_id'] : null,
                'presence_status' => $presenceStatus,
            ];
        }

        $note = sprintf(
            'Candidatos evaluados: %d | notificados: %d | sin permiso: %d | whatsapp_notify=0: %d | sin número: %d | número inválido: %d | no disponibles: %d | filtro rol: %s',
            count($rows),
            count($agents),
            $excludedNoPermission,
            $excludedNotifyDisabled,
            $excludedNoNumber,
            $excludedInvalidNumber,
            $excludedUnavailable,
            $roleId !== null && $roleId > 0 ? (string) $roleId : 'sin filtro'
        );

        return [
            'agents' => $agents,
            'note' => $note,
        ];
    }

    private function resolvePresenceStatus(int $userId): string
    {
        if ($userId <= 0) {
            return 'available';
        }

        $map = $this->loadPresenceMap();

        return $map[$userId] ?? 'available';
    }

    /**
     * @return array<int, string>
     */
    private function loadPresenceMap(): array
    {
        if (is_array($this->presenceMap)) {
            return $this->presenceMap;
        }

        try {
            $stmt = $this->pdo->query('SELECT user_id, status FROM whatsapp_agent_presence');
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $exception) {
            $this->presenceMap = [];

            return $this->presenceMap;
        }

        if (!is_array($rows)) {
            $this->presenceMap = [];

            return $this->presenceMap;
        }

        $map = [];
        foreach ($rows as $row) {
            $id = (int) ($row['user_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $status = strtolower(trim((string) ($row['status'] ?? 'available')));
            if (!in_array($status, ['available', 'away', 'offline'], true)) {
                $status = 'available';
            }
            $map[$id] = $status;
        }

        $this->presenceMap = $map;

        return $this->presenceMap;
    }

    private function findAgentByWhatsapp(string $waNumber): ?array
    {
        $normalized = $this->normalizeNumber($waNumber);
        if ($normalized === null) {
            return null;
        }

        $stmt = $this->pdo->query('SELECT u.id, u.username, u.first_name, u.last_name, u.nombre, u.permisos, u.role_id, u.whatsapp_number, u.whatsapp_notify, r.permissions AS role_permissions FROM users u LEFT JOIN roles r ON r.id = u.role_id WHERE u.whatsapp_number IS NOT NULL AND u.whatsapp_number <> ""');
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        if (!is_array($rows)) {
            return null;
        }

        foreach ($rows as $row) {
            $permissions = Permissions::merge($row['permisos'] ?? [], $row['role_permissions'] ?? []);
            if (!Permissions::containsAny($permissions, ['whatsapp.chat.send', 'whatsapp.manage'])) {
                continue;
            }

            $userNumber = isset($row['whatsapp_number']) ? trim((string) $row['whatsapp_number']) : '';
            if ($userNumber === '') {
                continue;
            }

            $userNormalized = $this->normalizeNumber($userNumber);
            if ($userNormalized === null) {
                continue;
            }

            if ($userNormalized === $normalized) {
                $row['name'] = $this->buildUserName($row);
                $row['whatsapp_number'] = $userNormalized;

                return $row;
            }
        }

        return null;
    }

    private function notifyHandoff(int $conversationId): void
    {
        if (!$this->pusher instanceof PusherConfigService) {
            return;
        }

        $conversation = $this->conversations->findConversationById($conversationId);
        if ($conversation === null) {
            return;
        }

        $payload = [
            'type' => 'whatsapp_handoff',
            'conversation_id' => (int) $conversation['id'],
            'wa_number' => $conversation['wa_number'],
            'display_name' => $conversation['display_name'] ?? null,
            'patient_full_name' => $conversation['patient_full_name'] ?? null,
            'handoff_notes' => $conversation['handoff_notes'] ?? null,
            'handoff_role_id' => isset($conversation['handoff_role_id']) ? (int) $conversation['handoff_role_id'] : null,
            'handoff_role_name' => $conversation['handoff_role_name'] ?? null,
            'last_message_at' => $conversation['last_message_at'] ?? null,
        ];

        $this->pusher->trigger($payload, null, PusherConfigService::EVENT_WHATSAPP_HANDOFF);
    }

    private function resolveConversationLabel(?int $conversationId = null, ?string $fallback = null): string
    {
        $label = null;
        if ($conversationId !== null) {
            $conversation = $this->conversations->findConversationById($conversationId);
            if ($conversation !== null) {
                $label = $conversation['display_name'] ?? $conversation['patient_full_name'] ?? null;
                if (is_string($label) && trim($label) !== '') {
                    return $label;
                }
                $fallback = $conversation['wa_number'] ?? $fallback;
            }
        }

        if ($fallback !== null && trim((string) $fallback) !== '') {
            return (string) $fallback;
        }

        return 'Contacto';
    }

    private function resolveAgentName(int $agentId): ?string
    {
        if ($agentId <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT first_name, last_name, nombre, username FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $agentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        $name = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        $name = trim((string) ($row['nombre'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        $name = trim((string) ($row['username'] ?? ''));

        return $name !== '' ? $name : null;
    }

    private function sendAgentMessage(array $agent, string $message): void
    {
        $number = isset($agent['whatsapp_number']) ? (string) $agent['whatsapp_number'] : '';
        if ($number === '') {
            return;
        }

        $this->messenger->sendTextMessage($number, $message, [
            'skip_record' => true,
        ]);
    }

    private function buildUserName(array $row): string
    {
        $name = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        $name = trim((string) ($row['nombre'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        $name = trim((string) ($row['username'] ?? ''));

        return $name !== '' ? $name : 'Agente';
    }

    private function normalizeNumber(?string $waNumber): ?string
    {
        if ($waNumber === null) {
            return null;
        }

        $number = trim($waNumber);
        if ($number === '') {
            return null;
        }

        $config = $this->settings->get();

        return PhoneNumberFormatter::formatPhoneNumber($number, $config);
    }

    private function sanitizeNotes(?string $notes): ?string
    {
        if ($notes === null) {
            return null;
        }

        $notes = trim($notes);
        if ($notes === '') {
            return null;
        }

        return mb_substr($notes, 0, 255);
    }

    private function resolveAssignedUntil(): string
    {
        $now = new DateTimeImmutable('now');
        $config = $this->settings->get();
        $ttl = isset($config['handoff_ttl_hours']) ? (int) $config['handoff_ttl_hours'] : self::DEFAULT_TTL_HOURS;
        if ($ttl <= 0) {
            $ttl = self::DEFAULT_TTL_HOURS;
        }
        if ($ttl > 168) {
            $ttl = 168;
        }
        $until = $now->modify('+' . $ttl . ' hours');

        return $until->format('Y-m-d H:i:s');
    }

    /**
     * @param array<string, mixed> $config
     */
    private function buildAgentMessage(string $contact, ?string $notes, int $handoffId, array $config): string
    {
        $template = $config['handoff_agent_message'] ?? '';
        $template = is_string($template) ? trim($template) : '';

        if ($template === '') {
            $template = "Paciente {{contact}} necesita asistencia.\nToca para tomar ✅\n\nNota: {{notes}}";
        }

        $notesValue = $notes !== null ? trim($notes) : '';

        $message = str_replace(
            ['{{contact}}', '{{notes}}', '{{id}}'],
            [$contact, $notesValue, (string) $handoffId],
            $template
        );

        if ($notesValue === '') {
            $message = preg_replace('/\\n?\\s*Nota:\\s*$/u', '', $message) ?? $message;
            $message = str_replace('Nota:', '', $message);
        }

        $message = trim($message);

        return $message === '' ? ('Paciente ' . $contact . ' necesita asistencia.') : $message;
    }

    /**
     * @param array<string, mixed>|null $error
     */
    private function formatTransportError(?array $error): string
    {
        if ($error === null) {
            return 'No fue posible enviar la notificación a agentes.';
        }

        $parts = [];
        if (!empty($error['http_code'])) {
            $parts[] = 'HTTP ' . $error['http_code'];
        }

        $message = isset($error['message']) ? trim((string) $error['message']) : '';
        if ($message !== '') {
            $parts[] = $message;
        }

        $details = $error['details'] ?? null;
        if (is_array($details) && isset($details['error']) && is_array($details['error'])) {
            $meta = [];
            if (!empty($details['error']['message'])) {
                $meta[] = (string) $details['error']['message'];
            }
            if (!empty($details['error']['code'])) {
                $meta[] = 'code ' . (string) $details['error']['code'];
            }
            if (!empty($details['error']['type'])) {
                $meta[] = (string) $details['error']['type'];
            }
            if (!empty($details['error']['error_subcode'])) {
                $meta[] = 'subcode ' . (string) $details['error']['error_subcode'];
            }
            if (!empty($meta)) {
                $parts[] = 'Meta: ' . implode(' · ', $meta);
            }
        }

        $note = trim(implode(' | ', $parts));

        return $note !== '' ? $note : 'No fue posible enviar la notificación a agentes.';
    }

    private function resolveButtonLabel(string $value, string $fallback): string
    {
        $label = trim($value);
        if ($label === '') {
            $label = $fallback;
        }

        if (mb_strlen($label, 'UTF-8') > 20) {
            $label = mb_substr($label, 0, 20, 'UTF-8');
        }

        return $label;
    }
}
