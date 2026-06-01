<?php

namespace App\Listeners;

use App\Events\Crm\ExamenEstadoCambiado;
use App\Events\Crm\ExamenSolicitado;
use App\Events\Crm\SolicitudCreada;
use App\Events\Crm\SolicitudKanbanEstadoCambiado;
use App\Events\Crm\WhatsappLeadQualified;
use App\Models\CrmOpportunity;
use App\Models\CrmStageMapping;
use App\Modules\CRM\Services\CrmContactResolverService;
use App\Modules\CRM\Services\CrmOpportunityService;
use Illuminate\Support\Facades\DB;

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

        $opp = $this->opportunityService->upsertFromEvent(
            contact: $contact,
            title: 'Solicitud: ' . (string) ($data['servicio'] ?? 'Servicio médico'),
            source: 'solicitud',
            sourceId: $event->solicitudId,
            sourceType: 'solicitud_procedimiento',
        );

        // Stamp affiliation type from solicitud data so the filter column is populated immediately
        $afiliacion = (string) ($data['afiliacion'] ?? '');
        if ($afiliacion !== '') {
            $tipo = self::classifyAfiliacion($afiliacion);
            if ($opp->afiliacion_tipo !== $tipo) {
                $opp->afiliacion_tipo = $tipo;
                $opp->saveQuietly();
            }
        }
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
     * Stage map is loaded from crm_stage_mappings (DB, cached 5 min).
     * Only advances — never downgrades a stage that's already ahead.
     */
    public function handleSolicitudKanbanEstadoCambiado(SolicitudKanbanEstadoCambiado $event): void
    {
        $map      = CrmStageMapping::forSourceType('solicitud_procedimiento');
        $crmStage = $map[$event->kanbanSlug] ?? null;

        if ($crmStage === null) {
            return;
        }

        $opp = $this->findOpportunityBySource('solicitud_procedimiento', $event->solicitudId);

        if (!($opp instanceof CrmOpportunity)) {
            return;
        }

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

        // Keep afiliacion_tipo fresh whenever a solicitud transitions
        $this->refreshAfiliacionFromSolicitud($opp, $event->solicitudId);
    }

    /**
     * Maps consulta_examenes.estado → CRM pipeline stage and advances the opportunity.
     * Stage map is loaded from crm_stage_mappings (DB, cached 5 min).
     * Only moves forward — never downgrades.
     */
    public function handleExamenEstadoCambiado(ExamenEstadoCambiado $event): void
    {
        $normalized = mb_strtolower(trim($event->nuevoEstado), 'UTF-8');
        $normalized = strtr($normalized, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n']);

        $map      = CrmStageMapping::forSourceType('consulta_examenes');
        $crmStage = $map[$normalized] ?? null;

        if ($crmStage === null) {
            return;
        }

        $opp = $this->findOpportunityBySource('consulta_examenes', $event->examenId)
            ?? $this->findOpportunityByExamenHcFallback($event->examenId);

        if (!($opp instanceof CrmOpportunity)) {
            return;
        }

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

    // =========================================================================
    // Helpers
    // =========================================================================

    private function findOpportunityBySource(string $sourceType, int $sourceId): ?CrmOpportunity
    {
        return CrmOpportunity::query()
            ->whereHas('activities', static fn ($q) => $q
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
            )
            ->first()
            ?? CrmOpportunity::query()
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->first();
    }

    private function findOpportunityByExamenHcFallback(int $examenId): ?CrmOpportunity
    {
        $hcNumber = DB::table('consulta_examenes')
            ->where('id', $examenId)
            ->value('hc_number');

        if ($hcNumber === null) {
            return null;
        }

        return CrmOpportunity::query()
            ->whereHas('contact', static fn ($q) => $q->where('cedula', $hcNumber))
            ->first();
    }

    private function refreshAfiliacionFromSolicitud(CrmOpportunity $opp, int $solicitudId): void
    {
        $afiliacion = DB::table('solicitud_procedimiento')
            ->where('id', $solicitudId)
            ->value('afiliacion');

        // Fallback: contact cedula → most recent solicitud
        $cedula = null;
        if ($afiliacion === null && $opp->contact_id !== null) {
            $cedula = DB::table('crm_contacts')
                ->where('id', $opp->contact_id)
                ->value('cedula');

            if ($cedula !== null) {
                $afiliacion = DB::table('solicitud_procedimiento')
                    ->where('hc_number', $cedula)
                    ->orderByDesc('id')
                    ->value('afiliacion');
            }
        }

        // Final fallback: patient_data direct lookup
        if ($afiliacion === null && $cedula !== null) {
            $afiliacion = DB::table('patient_data')
                ->where('hc_number', $cedula)
                ->value('afiliacion');
        }

        if ($afiliacion === null) {
            return;
        }

        $tipo = self::classifyAfiliacion((string) $afiliacion);
        if ($opp->afiliacion_tipo !== $tipo) {
            $opp->afiliacion_tipo = $tipo;
            $opp->saveQuietly();
        }
    }

    /**
     * Classifies an afiliacion string into one of the five canonical types.
     * Centralizes the logic that was previously duplicated in SQL (CASE WHEN REGEXP).
     */
    public static function classifyAfiliacion(string $afiliacion): string
    {
        $lower = mb_strtolower(trim($afiliacion), 'UTF-8');

        if ($lower === '') {
            return 'sin_dato';
        }

        if (preg_match('/iess|issfa|isspol|msp|ministerio|salud.publica|red.publica/', $lower)) {
            return 'publico';
        }

        if (str_contains($lower, 'particular')) {
            return 'particular';
        }

        if (str_contains($lower, 'fundaci')) {
            return 'fundacional';
        }

        return 'privado';
    }
}
