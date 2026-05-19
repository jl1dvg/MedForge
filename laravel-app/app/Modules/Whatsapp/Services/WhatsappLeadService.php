<?php

namespace App\Modules\Whatsapp\Services;

use App\Models\CrmLead;
use App\Models\WhatsappAutoresponderSession;
use App\Models\WhatsappConversation;
use App\Models\WhatsappHandoff;
use App\Models\WhatsappHandoffEvent;
use App\Models\WhatsappLead;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WhatsappLeadService
{
    /**
     * Crea un lead de seguimiento a partir de una conversación.
     * También crea el registro en crm_leads y resuelve el handoff activo.
     *
     * @return array<string, mixed>
     */
    public function createFromConversation(
        int $conversationId,
        string $motivoBaja,
        int $actorUserId
    ): array {
        $motivoBaja = trim($motivoBaja);
        if ($motivoBaja === '') {
            throw new RuntimeException('El motivo de la baja es obligatorio.');
        }

        $conversation = WhatsappConversation::query()->find($conversationId);
        if (!$conversation instanceof WhatsappConversation) {
            throw new RuntimeException('Conversación no encontrada.');
        }

        // Recuperar cédula del contexto del bot si existe
        $cedula = $this->resolveCedula($conversation);

        $result = DB::transaction(function () use ($conversation, $motivoBaja, $cedula, $actorUserId): array {
            // 1. Crear crm_lead
            $crmLead = CrmLead::query()->create([
                'hc_number'   => $conversation->patient_hc_number,
                'name'        => $conversation->patient_full_name ?: $conversation->display_name ?: $conversation->wa_number,
                'phone'       => $conversation->wa_number,
                'status'      => 'nuevo',
                'source'      => 'whatsapp_baja',
                'notes'       => "Dado de baja desde WhatsApp.\nNúmero: {$conversation->wa_number}\nMotivo: {$motivoBaja}"
                    . ($cedula ? "\nCédula: {$cedula}" : ''),
                'created_by'  => $actorUserId,
                'assigned_to' => $actorUserId,
            ]);

            // 2. Crear whatsapp_lead
            $lead = WhatsappLead::query()->create([
                'conversation_id'    => $conversation->id,
                'crm_lead_id'        => $crmLead->id,
                'wa_number'          => $conversation->wa_number,
                'display_name'       => $conversation->display_name,
                'hc_number'          => $conversation->patient_hc_number,
                'cedula'             => $cedula,
                'patient_full_name'  => $conversation->patient_full_name,
                'motivo_baja'        => $motivoBaja,
                'status'             => 'pendiente',
                'created_by_user_id' => $actorUserId,
            ]);

            // 3. Resolver handoff activo si existe
            $this->resolveActiveHandoff($conversation, $actorUserId, $motivoBaja);

            // 4. Limpiar asignación de la conversación
            $conversation->fill([
                'needs_human'       => false,
                'assigned_user_id'  => null,
                'assigned_at'       => null,
            ]);
            $conversation->save();

            return [
                'lead'    => $lead,
                'crm_lead_id' => $crmLead->id,
            ];
        });

        /** @var WhatsappLead $lead */
        $lead = $result['lead'];

        return $this->serializeLead($lead, $result['crm_lead_id']);
    }

    /**
     * Lista leads de seguimiento con filtros opcionales.
     *
     * @return array<string, mixed>
     */
    public function list(
        string $status = '',
        string $search = '',
        int $page = 1,
        int $perPage = 50
    ): array {
        $query = WhatsappLead::query()
            ->orderByDesc('created_at');

        if ($status !== '' && in_array($status, ['pendiente', 'contactado', 'cerrado'], true)) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('wa_number', 'like', "%{$search}%")
                  ->orWhere('display_name', 'like', "%{$search}%")
                  ->orWhere('patient_full_name', 'like', "%{$search}%")
                  ->orWhere('hc_number', 'like', "%{$search}%")
                  ->orWhere('cedula', 'like', "%{$search}%");
            });
        }

        $total  = (clone $query)->count();
        $offset = ($page - 1) * $perPage;
        $items  = $query->offset($offset)->limit($perPage)->get();

        return [
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'items'    => $items->map(fn (WhatsappLead $l) => $this->serializeLead($l))->values()->all(),
        ];
    }

    /**
     * Actualiza el estado de un lead.
     *
     * @return array<string, mixed>
     */
    public function updateStatus(int $leadId, string $newStatus): array
    {
        $allowed = ['pendiente', 'contactado', 'cerrado'];
        if (!in_array($newStatus, $allowed, true)) {
            throw new RuntimeException('Estado no válido. Usa: ' . implode(', ', $allowed));
        }

        $lead = WhatsappLead::query()->find($leadId);
        if (!$lead instanceof WhatsappLead) {
            throw new RuntimeException('Lead no encontrado.');
        }

        $lead->status = $newStatus;
        $lead->save();

        return $this->serializeLead($lead);
    }

    // ──────────────────────────────────────────────────────────────────────────

    private function resolveCedula(WhatsappConversation $conversation): ?string
    {
        $session = WhatsappAutoresponderSession::query()
            ->where('conversation_id', $conversation->id)
            ->first();

        if (!$session instanceof WhatsappAutoresponderSession) {
            return null;
        }

        $ctx = is_array($session->context) ? $session->context : [];

        foreach (['cedula', 'identifier', 'identificacion', 'id_number'] as $key) {
            if (!empty($ctx[$key]) && is_string($ctx[$key])) {
                return trim($ctx[$key]);
            }
        }

        return null;
    }

    private function resolveActiveHandoff(WhatsappConversation $conversation, int $actorUserId, string $notes): void
    {
        if (!class_exists(WhatsappHandoff::class)) {
            return;
        }

        $handoff = WhatsappHandoff::query()
            ->where('conversation_id', $conversation->id)
            ->whereIn('status', ['assigned', 'queued', 'requested'])
            ->orderByDesc('id')
            ->first();

        if (!$handoff instanceof WhatsappHandoff) {
            return;
        }

        $now = now();
        $handoff->fill([
            'status'      => 'resolved',
            'resolved_at' => $now,
        ]);
        $handoff->save();

        if (class_exists(WhatsappHandoffEvent::class)) {
            WhatsappHandoffEvent::query()->create([
                'handoff_id'         => $handoff->id,
                'event_type'         => 'resolved',
                'actor_user_id'      => $actorUserId,
                'notes'              => 'Baja: ' . mb_substr($notes, 0, 500),
                'created_at'         => $now,
                'updated_at'         => $now,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLead(WhatsappLead $lead, ?int $crmLeadId = null): array
    {
        return [
            'id'                => $lead->id,
            'conversation_id'   => $lead->conversation_id,
            'crm_lead_id'       => $crmLeadId ?? $lead->crm_lead_id,
            'wa_number'         => $lead->wa_number,
            'display_name'      => $lead->display_name,
            'hc_number'         => $lead->hc_number,
            'cedula'            => $lead->cedula,
            'patient_full_name' => $lead->patient_full_name,
            'motivo_baja'       => $lead->motivo_baja,
            'status'            => $lead->status,
            'created_by_user_id' => $lead->created_by_user_id,
            'created_at'        => optional($lead->created_at)->toISOString(),
        ];
    }
}
