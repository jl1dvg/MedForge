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

class HandoffService
{
    private const DEFAULT_TTL_HOURS = 24;

    private PDO $pdo;
    private ConversationRepository $conversations;
    private HandoffRepository $handoffs;
    private WhatsAppSettings $settings;
    private Messenger $messenger;
    private ?PusherConfigService $pusher = null;

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
            if (!empty($updates)) {
                $this->handoffs->update((int) $active['id'], $updates);
            }

            if ($isAssigned) {
                $this->conversations->updateHandoffDetails($conversationId, $notes, $roleId);
            } else {
                $this->conversations->setHandoffFlag($conversationId, true, $notes, $roleId);
            }
            $this->handoffs->insertEvent((int) $active['id'], 'requested', null, $notes);
            if (!$isAssigned) {
                $this->notifyHandoff($conversationId);
                $this->notifyAgents($normalized, (int) $active['id'], $roleId, $notes, $conversationId);
            }

            return (int) $active['id'];
        }

        $handoffId = $this->handoffs->create([
            'conversation_id' => $conversationId,
            'wa_number' => $normalized,
            'status' => 'queued',
            'priority' => $priority,
            'topic' => $topic,
            'handoff_role_id' => $roleId,
            'queued_at' => date('Y-m-d H:i:s'),
            'notes' => $notes,
        ]);

        $this->handoffs->insertEvent($handoffId, 'queued', null, $notes);
        $this->conversations->setHandoffFlag($conversationId, true, $notes, $roleId);
        $this->notifyHandoff($conversationId);
        $this->notifyAgents($normalized, $handoffId, $roleId, $notes, $conversationId);

        return $handoffId;
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
        $agents = $this->listNotifiableAgents($roleId);
        if (empty($agents)) {
            return;
        }

        $label = $this->resolveConversationLabel($conversationId, $waNumber);
        $message = 'Paciente ' . $label . " necesita asistencia.\nToca para tomar ✅";
        if ($notes !== null && $notes !== '') {
            $message .= "\n\nNota: " . $notes;
        }

        $buttons = [
            ['id' => 'TOMAR_' . $handoffId, 'title' => 'Tomar'],
            ['id' => 'IGNORAR_' . $handoffId, 'title' => 'Ignorar'],
        ];

        $recipients = [];
        foreach ($agents as $agent) {
            if (empty($agent['whatsapp_number'])) {
                continue;
            }
            $recipients[] = $agent['whatsapp_number'];
        }

        if (empty($recipients)) {
            return;
        }

        $this->messenger->sendInteractiveButtons($recipients, $message, $buttons, [
            'skip_record' => true,
        ]);
    }

    private function listNotifiableAgents(?int $roleId = null): array
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
            return [];
        }

        $agents = [];
        foreach ($rows as $row) {
            $permissions = Permissions::merge($row['permisos'] ?? [], $row['role_permissions'] ?? []);
            if (!Permissions::containsAny($permissions, ['whatsapp.chat.send', 'whatsapp.manage'])) {
                continue;
            }

            $whatsappNumber = isset($row['whatsapp_number']) ? trim((string) $row['whatsapp_number']) : '';
            $notify = isset($row['whatsapp_notify']) ? (int) $row['whatsapp_notify'] : 0;
            if ($whatsappNumber === '' || $notify !== 1) {
                continue;
            }

            $normalizedNumber = $this->normalizeNumber($whatsappNumber);
            if ($normalizedNumber === null) {
                continue;
            }

            $name = $this->buildUserName($row);
            $agents[] = [
                'id' => (int) $row['id'],
                'name' => $name,
                'whatsapp_number' => $normalizedNumber,
                'role_id' => isset($row['role_id']) ? (int) $row['role_id'] : null,
            ];
        }

        return $agents;
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
        $until = $now->modify('+' . self::DEFAULT_TTL_HOURS . ' hours');

        return $until->format('Y-m-d H:i:s');
    }
}
