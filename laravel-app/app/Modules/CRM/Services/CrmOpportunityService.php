<?php

namespace App\Modules\CRM\Services;

use App\Models\CrmActivity;
use App\Models\CrmContact;
use App\Models\CrmOpportunity;
use App\Models\CrmProcedureRule;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CrmOpportunityService
{
    public function __construct(
        private readonly CrmActivityService $activityService,
    ) {}

    // =========================================================================
    // Source → stage / activity-type mappings (shared by both algorithms)
    // =========================================================================

    private const SOURCE_ENTRY_STAGE = [
        'whatsapp'  => CrmOpportunity::STAGE_NUEVO,
        'solicitud' => CrmOpportunity::STAGE_NUEVO,
        'examen'    => CrmOpportunity::STAGE_NUEVO,
        'manual'    => CrmOpportunity::STAGE_NUEVO,
    ];

    private const SOURCE_ACTIVITY_TYPE = [
        'whatsapp'  => CrmActivity::TYPE_WHATSAPP,
        'solicitud' => CrmActivity::TYPE_SOLICITUD,
        'examen'    => CrmActivity::TYPE_EXAMEN,
        'manual'    => CrmActivity::TYPE_NOTA,
    ];

    // =========================================================================
    // Public entry point — dispatches to legacy or intent algorithm
    // =========================================================================

    /**
     * Creates or reuses a CRM opportunity for an incoming clinical/commercial event.
     *
     * Returns null only in intent mode when genera_oportunidad=0 and the contact
     * has no active opportunity to attach the activity to.
     */
    public function upsertFromEvent(
        CrmContact $contact,
        string     $title,
        string     $source,
        ?int       $sourceId        = null,
        ?string    $sourceType      = null,
        ?int       $assignedTo      = null,
        ?string    $procedureCodigo = null,
        ?string    $lateralidad     = null,
        ?\DateTimeInterface $episodeAt = null,
    ): ?CrmOpportunity {
        if (config('crm.intent_model_enabled') && $procedureCodigo !== null) {
            return $this->upsertFromEventIntent(
                contact:         $contact,
                title:           $title,
                source:          $source,
                sourceId:        $sourceId,
                sourceType:      $sourceType,
                assignedTo:      $assignedTo,
                procedureCodigo: $procedureCodigo,
                lateralidad:     $lateralidad,
                episodeAt:       $episodeAt,
            );
        }

        return $this->upsertFromEventLegacy(
            contact:    $contact,
            title:      $title,
            source:     $source,
            sourceId:   $sourceId,
            sourceType: $sourceType,
            assignedTo: $assignedTo,
        );
    }

    // =========================================================================
    // Legacy algorithm — original behaviour, untouched
    // =========================================================================

    private function upsertFromEventLegacy(
        CrmContact $contact,
        string     $title,
        string     $source,
        ?int       $sourceId   = null,
        ?string    $sourceType = null,
        ?int       $assignedTo = null,
    ): CrmOpportunity {
        $title = $this->limitTitle($title);

        return DB::transaction(function () use ($contact, $title, $source, $sourceId, $sourceType, $assignedTo): CrmOpportunity {
            $existing = CrmOpportunity::query()->where('contact_id', $contact->id)->first();

            if ($existing instanceof CrmOpportunity) {
                $activityType = self::SOURCE_ACTIVITY_TYPE[$source] ?? CrmActivity::TYPE_NOTA;
                if ($sourceId !== null) {
                    $this->activityService->logClinical(
                        opportunityId: $existing->id,
                        type:          $activityType,
                        description:   $title,
                        sourceId:      $sourceId,
                        sourceType:    $sourceType ?? '',
                    );
                } else {
                    $this->activityService->logSystemEvent($existing->id, $title);
                }
                $this->touchLastActivity($existing);
                return $existing;
            }

            $stage       = self::SOURCE_ENTRY_STAGE[$source] ?? CrmOpportunity::STAGE_NUEVO;
            $escalationAt = $this->computeEscalationAt($stage);

            $opp = CrmOpportunity::query()->create([
                'contact_id'       => $contact->id,
                'title'            => $title,
                'stage'            => $stage,
                'phase'            => CrmOpportunity::PHASE_OPERATIONAL,
                'source'           => $source,
                'source_id'        => $sourceId,
                'source_type'      => $sourceType,
                'assigned_to'      => $assignedTo,
                'last_activity_at' => now(),
                'escalation_at'    => $escalationAt,
            ]);

            $activityType = self::SOURCE_ACTIVITY_TYPE[$source] ?? CrmActivity::TYPE_NOTA;
            if ($sourceId !== null) {
                $this->activityService->logClinical(
                    opportunityId: $opp->id,
                    type:          $activityType,
                    description:   $title,
                    sourceId:      $sourceId,
                    sourceType:    $sourceType ?? '',
                );
            } else {
                $this->activityService->logSystemEvent($opp->id, "Oportunidad creada desde {$source}");
            }

            return $opp;
        });
    }

    // =========================================================================
    // Intent algorithm — episode-based model (Phase 2B activation)
    // =========================================================================

    private function upsertFromEventIntent(
        CrmContact $contact,
        string     $title,
        string     $source,
        ?int       $sourceId,
        ?string    $sourceType,
        ?int       $assignedTo,
        string     $procedureCodigo,
        ?string    $lateralidad,
        ?\DateTimeInterface $episodeAt,
    ): ?CrmOpportunity {
        $title = $this->limitTitle($title);

        return DB::transaction(function () use (
            $contact, $title, $source, $sourceId, $sourceType,
            $assignedTo, $procedureCodigo, $lateralidad, $episodeAt,
        ): ?CrmOpportunity {
            $rule           = $this->resolveRule($procedureCodigo);
            $procedureGroup = $rule['grupo_codigo'] ?: $procedureCodigo;
            $oppType        = $rule['tipo'];
            $ventanaDias    = $rule['ventana_dias'];
            $generaOpp      = (bool) $rule['genera_oportunidad'];
            $agruparPorOjo  = (bool) $rule['agrupar_por_ojo'];

            $lateralidadEfectiva = $agruparPorOjo ? $lateralidad : null;

            // --- genera_oportunidad=0 or diagnostico: attach to any active opp, never create ---
            if (!$generaOpp || $oppType === 'diagnostico') {
                $anyActive = CrmOpportunity::query()
                    ->active()
                    ->where('contact_id', $contact->id)
                    ->latest('last_activity_at')
                    ->first();

                if ($anyActive instanceof CrmOpportunity) {
                    $this->logActivityOnOpportunity($anyActive, $source, $sourceId, $sourceType, $title);
                    $this->touchLastActivity($anyActive);
                    return $anyActive;
                }

                return null;
            }

            // --- Look for an open compatible episode ---
            $existing = $this->findActiveCompatible($contact->id, $procedureGroup, $lateralidadEfectiva, $oppType, $ventanaDias);

            if ($existing instanceof CrmOpportunity) {
                $this->logActivityOnOpportunity($existing, $source, $sourceId, $sourceType, $title);
                $this->touchLastActivity($existing);
                return $existing;
            }

            // --- No active episode — create a new one ---
            $previous        = null;
            $continuityFlag  = false;

            if ($oppType === 'recurrente') {
                $previous = $this->findLastClosed($contact->id, $procedureGroup, $lateralidadEfectiva);

                if ($previous !== null && $ventanaDias !== null) {
                    $previousDate   = $previous->episode_started_at ?? $previous->created_at;
                    $daysSince      = (int) \Carbon\Carbon::parse($previousDate)->diffInDays(now());
                    $continuityFlag = $daysSince <= $ventanaDias;
                }
            }

            return $this->createNewOpportunity(
                contact:         $contact,
                title:           $title,
                source:          $source,
                sourceId:        $sourceId,
                sourceType:      $sourceType,
                assignedTo:      $assignedTo,
                procedureGroup:  $procedureGroup,
                lateralidad:     $lateralidadEfectiva,
                oppType:         $oppType,
                episodeAt:       $episodeAt,
                previous:        $previous,
                continuityFlag:  $continuityFlag,
            );
        });
    }

    // =========================================================================
    // Intent helpers
    // =========================================================================

    private function resolveRule(string $codigo): array
    {
        return CrmProcedureRule::forCodigo($codigo);
    }

    private function findActiveCompatible(
        int     $contactId,
        string  $procedureGroup,
        ?string $lateralidad,
        string  $oppType,
        ?int    $ventanaDias,
    ): ?CrmOpportunity {
        // Legacy opps (procedure_group=null or opportunity_type=null) are never
        // candidates for episode reuse — they lack the episode schema.
        // Zombie cutoff: episodes idle beyond their type's window cannot be reused.
        $cutoff = match ($oppType) {
            'unica'      => now()->subDays(180),
            'recurrente' => now()->subDays($ventanaDias ?? 90),
            'diagnostico'=> now()->subDays(30),
            default      => now()->subDays(180),
        };

        return CrmOpportunity::query()
            ->active()
            ->whereNotNull('procedure_group')
            ->whereNotNull('opportunity_type')
            ->where('contact_id',      $contactId)
            ->where('procedure_group', $procedureGroup)
            ->when(
                $lateralidad !== null,
                fn ($q) => $q->where('lateralidad', $lateralidad),
                fn ($q) => $q->whereNull('lateralidad'),
            )
            ->whereRaw('COALESCE(last_activity_at, created_at) >= ?', [$cutoff])
            ->latest('episode_started_at')
            ->first();
    }

    private function findLastClosed(int $contactId, string $procedureGroup, ?string $lateralidad): ?CrmOpportunity
    {
        return CrmOpportunity::query()
            ->whereIn('stage', [CrmOpportunity::STAGE_GANADO, CrmOpportunity::STAGE_PERDIDO])
            ->where('contact_id',      $contactId)
            ->where('procedure_group', $procedureGroup)
            ->when(
                $lateralidad !== null,
                fn ($q) => $q->where('lateralidad', $lateralidad),
                fn ($q) => $q->whereNull('lateralidad'),
            )
            ->latest('episode_started_at')
            ->first();
    }

    private function createNewOpportunity(
        CrmContact   $contact,
        string       $title,
        string       $source,
        ?int         $sourceId,
        ?string      $sourceType,
        ?int         $assignedTo,
        string       $procedureGroup,
        ?string      $lateralidad,
        string       $oppType,
        ?\DateTimeInterface $episodeAt,
        ?CrmOpportunity     $previous,
        bool         $continuityFlag,
    ): CrmOpportunity {
        $stage       = self::SOURCE_ENTRY_STAGE[$source] ?? CrmOpportunity::STAGE_NUEVO;
        $escalationAt = $this->computeEscalationAt($stage);

        $opp = CrmOpportunity::query()->create([
            'contact_id'              => $contact->id,
            'title'                   => $title,
            'stage'                   => $stage,
            'phase'                   => CrmOpportunity::PHASE_OPERATIONAL,
            'source'                  => $source,
            'source_id'               => $sourceId,
            'source_type'             => $sourceType,
            'assigned_to'             => $assignedTo,
            'last_activity_at'        => now(),
            'escalation_at'           => $escalationAt,
            'procedure_group'         => $procedureGroup,
            'lateralidad'             => $lateralidad,
            'opportunity_type'        => $oppType,
            'episode_started_at'      => $episodeAt ?? now(),
            'previous_opportunity_id' => $previous?->id,
            'continuity_flag'         => $continuityFlag ? 1 : 0,
        ]);

        $this->logActivityOnOpportunity($opp, $source, $sourceId, $sourceType, $title);

        return $opp;
    }

    private function logActivityOnOpportunity(
        CrmOpportunity $opp,
        string $source,
        ?int $sourceId,
        ?string $sourceType,
        string $title,
    ): void {
        $activityType = self::SOURCE_ACTIVITY_TYPE[$source] ?? CrmActivity::TYPE_NOTA;
        if ($sourceId !== null) {
            $this->activityService->logClinical(
                opportunityId: $opp->id,
                type:          $activityType,
                description:   $title,
                sourceId:      $sourceId,
                sourceType:    $sourceType ?? '',
            );
        } else {
            $this->activityService->logSystemEvent($opp->id, $title);
        }
    }

    // =========================================================================
    // Stage management (unchanged)
    // =========================================================================

    public function changeStage(
        CrmOpportunity $opportunity,
        string $newStage,
        ?int $userId = null,
        ?string $lostReason = null,
    ): CrmOpportunity {
        if (!in_array($newStage, CrmOpportunity::STAGES, true)) {
            throw new RuntimeException("Etapa inválida: {$newStage}");
        }

        $fromStage = $opportunity->stage;

        DB::transaction(function () use ($opportunity, $newStage, $lostReason, $userId, $fromStage): void {
            $opportunity->stage = $newStage;

            if ($newStage === CrmOpportunity::STAGE_PERDIDO && $lostReason !== null) {
                $opportunity->lost_reason = $lostReason;
            }

            if (in_array($newStage, CrmOpportunity::COMMERCIAL_STAGES, true)) {
                $opportunity->phase        = CrmOpportunity::PHASE_COMMERCIAL;
                $opportunity->escalation_at = null;
            } else {
                $opportunity->escalation_at = $this->computeEscalationAt($newStage);
            }

            $opportunity->last_activity_at = now();
            $opportunity->save();

            $this->activityService->logStageChange($opportunity->id, $fromStage, $newStage, $userId);
        });

        return $opportunity->fresh();
    }

    public function assign(CrmOpportunity $opportunity, int $userId): CrmOpportunity
    {
        $opportunity->assigned_to      = $userId;
        $opportunity->last_activity_at = now();
        $opportunity->save();
        return $opportunity;
    }

    public function escalateToCommercial(CrmOpportunity $opportunity): void
    {
        DB::transaction(function () use ($opportunity): void {
            $opportunity->phase        = CrmOpportunity::PHASE_COMMERCIAL;
            $opportunity->escalation_at = null;
            $opportunity->save();

            $daysSince = (int) ($opportunity->last_activity_at?->diffInDays(now()) ?? 0);
            $this->activityService->logSystemEvent(
                $opportunity->id,
                "Escalado automáticamente a Comercial — sin actividad por {$daysSince} días",
            );
        });
    }

    // =========================================================================
    // Private utilities
    // =========================================================================

    private function touchLastActivity(CrmOpportunity $opportunity): void
    {
        $opportunity->last_activity_at = now();
        if ($opportunity->phase === CrmOpportunity::PHASE_OPERATIONAL) {
            $opportunity->escalation_at = $this->computeEscalationAt($opportunity->stage);
        }
        $opportunity->save();
    }

    private function limitTitle(string $title): string
    {
        return mb_strlen($title) > 255 ? mb_substr($title, 0, 255) : $title;
    }

    private function computeEscalationAt(string $stage): ?\Carbon\Carbon
    {
        $days = match ($stage) {
            CrmOpportunity::STAGE_CONTACTADO    => (int) config('crm.escalacion.dias_contactado', 7),
            CrmOpportunity::STAGE_EN_EVALUACION => (int) config('crm.escalacion.dias_en_evaluacion', 14),
            default                              => null,
        };

        return $days !== null ? now()->addDays($days) : null;
    }
}
