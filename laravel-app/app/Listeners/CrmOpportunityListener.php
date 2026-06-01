<?php

namespace App\Listeners;

use App\Events\Crm\ExamenSolicitado;
use App\Events\Crm\SolicitudCreada;
use App\Events\Crm\SolicitudKanbanEstadoCambiado;
use App\Events\Crm\WhatsappLeadQualified;
use App\Models\CrmOpportunity;
use App\Modules\CRM\Services\CrmContactResolverService;
use App\Modules\CRM\Services\CrmOpportunityService;

class CrmOpportunityListener
{
    public function __construct(
        private readonly CrmContactResolverService $contactResolver,
        private readonly CrmOpportunityService $opportunityService,
    ) {}

    public function handleWhatsappLeadQualified(WhatsappLeadQualified $event): void
    {
        $lead = $event->lead;

        $contact = $this->contactResolver->resolve(
            phone: $lead->wa_number,
            name: $lead->patient_full_name ?? $lead->display_name ?? $lead->wa_number,
            cedula: $lead->cedula,
            source: 'whatsapp',
        );

        $this->opportunityService->upsertFromEvent(
            contact: $contact,
            title: 'Lead WhatsApp: ' . ($lead->motivo_baja ?: 'sin motivo registrado'),
            source: 'whatsapp',
            sourceId: $lead->id,
            sourceType: 'whatsapp_lead',
            assignedTo: $event->actorUserId,
        );
    }

    public function handleSolicitudCreada(SolicitudCreada $event): void
    {
        $data = $event->solicitudData;

        $contact = $this->contactResolver->resolve(
            phone: (string) ($data['paciente_telefono'] ?? ''),
            name: (string) ($data['paciente_nombre'] ?? 'Paciente'),
            cedula: $data['paciente_cedula'] ?? null,
            source: 'solicitud',
        );

        $this->opportunityService->upsertFromEvent(
            contact: $contact,
            title: 'Solicitud: ' . (string) ($data['servicio'] ?? 'Servicio médico'),
            source: 'solicitud',
            sourceId: $event->solicitudId,
            sourceType: 'solicitud_procedimiento',
        );
    }

    public function handleExamenSolicitado(ExamenSolicitado $event): void
    {
        $data = $event->examenData;

        $contact = $this->contactResolver->resolve(
            phone: (string) ($data['paciente_telefono'] ?? ''),
            name: (string) ($data['paciente_nombre'] ?? 'Paciente'),
            cedula: $data['paciente_cedula'] ?? null,
            source: 'examen',
        );

        $this->opportunityService->upsertFromEvent(
            contact: $contact,
            title: 'Examen: ' . (string) ($data['descripcion_examen'] ?? 'Examen solicitado'),
            source: 'examen',
            sourceId: $event->examenId,
            sourceType: 'consulta_examenes',
        );
    }

    /**
     * Maps solicitud kanban stages to CRM pipeline stages.
     * Only advances — never downgrades a stage that's already ahead.
     */
    public function handleSolicitudKanbanEstadoCambiado(SolicitudKanbanEstadoCambiado $event): void
    {
        $crmStage = self::SOLICITUD_KANBAN_TO_CRM_STAGE[$event->kanbanSlug] ?? null;
        if ($crmStage === null) {
            return; // Unrecognized slug — ignore
        }

        // Find the opportunity linked to this solicitud
        // First try: activity log (works after consolidation — covers all solicitudes in the opp)
        $opp = CrmOpportunity::query()
            ->whereHas('activities', static fn ($q) => $q
                ->where('source_type', 'solicitud_procedimiento')
                ->where('source_id', $event->solicitudId)
            )
            ->first()
            // Fallback: direct source on opportunity (original source solicitud)
            ?? CrmOpportunity::query()
                ->where('source_type', 'solicitud_procedimiento')
                ->where('source_id', $event->solicitudId)
                ->first();

        if (!($opp instanceof CrmOpportunity)) {
            return; // No CRM opportunity linked to this solicitud
        }

        // Only advance the stage — never downgrade
        $stageOrder   = array_flip(CrmOpportunity::STAGES);
        $currentOrder = $stageOrder[$opp->stage] ?? 0;
        $newOrder     = $stageOrder[$crmStage] ?? 0;

        if ($newOrder <= $currentOrder) {
            return;
        }

        $this->opportunityService->changeStage(
            opportunity: $opp,
            newStage: $crmStage,
            userId: $event->actorUserId,
        );
    }

    /**
     * Solicitud kanban slug → CRM pipeline stage.
     *
     * Solicitud workflow:  recibida → llamado → en-atencion → revision-codigos →
     *                      espera-documentos → apto-oftalmologo → apto-anestesia →
     *                      listo-para-agenda → programada → completado
     *
     * @var array<string, string>
     */
    private const SOLICITUD_KANBAN_TO_CRM_STAGE = [
        'recibida'             => CrmOpportunity::STAGE_NUEVO,
        'llamado'              => CrmOpportunity::STAGE_CONTACTADO,
        'en-atencion'          => CrmOpportunity::STAGE_EN_EVALUACION,
        'revision-codigos'     => CrmOpportunity::STAGE_EN_EVALUACION,
        'espera-documentos'    => CrmOpportunity::STAGE_EN_EVALUACION,
        'apto-oftalmologo'     => CrmOpportunity::STAGE_EN_EVALUACION,
        'apto-anestesia'       => CrmOpportunity::STAGE_EN_EVALUACION,
        'listo-para-agenda'    => CrmOpportunity::STAGE_EN_EVALUACION,
        'programada'           => CrmOpportunity::STAGE_COMPROMETIDO,
        'completado'           => CrmOpportunity::STAGE_GANADO,
    ];
}
