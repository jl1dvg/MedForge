<?php

namespace App\Modules\Whatsapp\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WhatsappOperationalDecisionService
{
    // SLA threshold: assigned conversation without human response after this many minutes → supervisor_review
    private const SLA_ASSIGNED_WITHOUT_RESPONSE_MINUTES = 120;

    private const HOT_TOPICS = [
        'captacion_agendar',
        'agenda_sin_disponibilidad',
        'faq_escalada',
        'operacion_cita_vigente',
        'operacion_reagenda',
    ];

    public const ACTION_ASSIGN_NOW         = 'assign_now';
    public const ACTION_SUPERVISOR_REVIEW  = 'supervisor_review';
    public const ACTION_SEND_TEMPLATE      = 'send_template_or_review';
    public const ACTION_RESCUE_FOLLOWUP    = 'rescue_followup';
    public const ACTION_HOLD_BACKLOG       = 'hold_backlog';
    public const ACTION_NO_ACTION_LOST     = 'no_action_lost';
    public const ACTION_NO_ACTION_CONVERTED = 'no_action_converted';
    public const ACTION_ALREADY_HANDLED    = 'no_action_already_handled';

    /**
     * @return array<string,mixed>
     */
    public function evaluate(CarbonInterface $snapshotDate): array
    {
        $asOf = CarbonImmutable::parse($snapshotDate->format('Y-m-d H:i:s'));
        $rows = $this->conversationRows($asOf);
        $attributionMap = $this->buildAttributionMap();

        $decisions = [];
        foreach ($rows as $row) {
            $decisions[] = $this->evaluateRow($row, $attributionMap, $asOf);
        }

        return [
            'date' => $asOf->toDateString(),
            'generated_at' => $asOf->format('Y-m-d H:i:s'),
            'summary' => $this->summarize($decisions),
            'decisions' => $decisions,
        ];
    }

    /**
     * @param array<string,mixed> $attributionMap
     * @return array<string,mixed>
     */
    private function evaluateRow(object $row, array $attributionMap, CarbonImmutable $asOf): array
    {
        $convId    = (int) $row->conversation_id;
        $bucket    = $this->classifyBucket($row, $asOf);
        $isAssigned = (int) ($row->assigned_user_id ?? 0) > 0
            || (int) ($row->assigned_agent_id ?? 0) > 0;

        $queueAt         = $this->parseAt($row->queued_at ?? null)
                           ?? $this->parseAt($row->handoff_created_at ?? null);
        $firstOutboundAt = $this->parseAt($row->first_outbound_at ?? null);
        $latestInboundAt = $this->parseAt($row->latest_inbound_at ?? null);

        $minutesSinceQueue  = $queueAt !== null ? max(0, $queueAt->diffInMinutes($asOf)) : 0;
        $hasHumanResponse   = $firstOutboundAt !== null
                              && ($queueAt === null || $firstOutboundAt->greaterThanOrEqualTo($queueAt));
        $windowOpen = $latestInboundAt !== null
                      && $latestInboundAt->greaterThanOrEqualTo($asOf->subHours(24));

        $attribution = $attributionMap[$convId] ?? null;
        $hasAttributedBooking  = $attribution !== null;
        $hasPrimary            = (bool) ($attribution['has_primary'] ?? false);
        $hasIndependent        = (bool) ($attribution['has_independent'] ?? false);

        $topic    = (string) ($row->topic ?? '');
        $category = self::topicCategory($topic);

        $base = [
            'conversation_id'                  => $convId,
            'bucket'                           => $bucket,
            'topic'                            => $topic,
            'topic_label'                      => self::topicLabel($topic),
            'category'                         => $category,
            'category_label'                   => self::categoryLabel($category),
            'latest_inbound_at'                => ($row->latest_inbound_at ?? null),
            'has_attributed_booking'           => $hasAttributedBooking,
            'has_primary_clinical_appointment' => $hasPrimary,
            'has_independent_attributed_service' => $hasIndependent,
        ];

        // Rule 1 — already converted
        if ($hasPrimary || ($hasAttributedBooking && $hasIndependent)) {
            return $base + [
                'recommended_action'         => self::ACTION_NO_ACTION_CONVERTED,
                'priority'                   => 'low',
                'risk_level'                 => 'low',
                'opportunity_level'          => 'completed',
                'eligible_for_autoassign'    => false,
                'eligible_for_rescue'        => false,
                'eligible_for_supervisor_alert' => false,
                'reason' => 'Conversación con cita clínica atribuida de alta confianza.',
            ];
        }

        return match ($bucket) {
            'hot_open'          => $this->decideHotOpen($base, $isAssigned, $hasHumanResponse, $minutesSinceQueue, $windowOpen),
            'hot_needs_template' => $this->decideHotNeedsTemplate($base, $minutesSinceQueue),
            'rescue'            => $this->decideRescue($base, $minutesSinceQueue),
            'backlog'           => $this->decideBacklog($base),
            'lost'              => $this->decideLost($base),
            default             => $base + [
                'recommended_action'         => self::ACTION_ALREADY_HANDLED,
                'priority'                   => 'low',
                'risk_level'                 => 'low',
                'opportunity_level'          => 'low',
                'eligible_for_autoassign'    => false,
                'eligible_for_rescue'        => false,
                'eligible_for_supervisor_alert' => false,
                'reason' => 'Conversación fuera de ventana operacional activa.',
            ],
        };
    }

    /**
     * @param array<string,mixed> $base
     * @return array<string,mixed>
     */
    private function decideHotOpen(array $base, bool $isAssigned, bool $hasHumanResponse, int $minutesSinceQueue, bool $windowOpen): array
    {
        // Assigned but no human response within SLA → supervisor
        if ($isAssigned && !$hasHumanResponse && $minutesSinceQueue >= self::SLA_ASSIGNED_WITHOUT_RESPONSE_MINUTES) {
            return $base + [
                'recommended_action'         => self::ACTION_SUPERVISOR_REVIEW,
                'priority'                   => 'high',
                'risk_level'                 => 'high',
                'opportunity_level'          => 'high',
                'eligible_for_autoassign'    => false,
                'eligible_for_rescue'        => false,
                'eligible_for_supervisor_alert' => true,
                'reason' => 'HOT_OPEN asignada sin respuesta humana superando SLA de ' . self::SLA_ASSIGNED_WITHOUT_RESPONSE_MINUTES . ' min.',
            ];
        }

        // Unassigned → assign now
        if (!$isAssigned) {
            return $base + [
                'recommended_action'         => self::ACTION_ASSIGN_NOW,
                'priority'                   => 'high',
                'risk_level'                 => $windowOpen ? 'medium' : 'high',
                'opportunity_level'          => 'high',
                'eligible_for_autoassign'    => true,
                'eligible_for_rescue'        => false,
                'eligible_for_supervisor_alert' => false,
                'reason' => 'HOT_OPEN sin asignación activa ni cita atribuida.',
            ];
        }

        // Assigned and responded — no action needed yet
        return $base + [
            'recommended_action'         => self::ACTION_ALREADY_HANDLED,
            'priority'                   => 'normal',
            'risk_level'                 => 'low',
            'opportunity_level'          => 'high',
            'eligible_for_autoassign'    => false,
            'eligible_for_rescue'        => false,
            'eligible_for_supervisor_alert' => false,
            'reason' => 'HOT_OPEN asignada con respuesta humana reciente.',
        ];
    }

    /**
     * @param array<string,mixed> $base
     * @return array<string,mixed>
     */
    private function decideHotNeedsTemplate(array $base, int $minutesSinceQueue): array
    {
        $priority = $minutesSinceQueue >= 12 * 60 ? 'high' : 'medium';

        return $base + [
            'recommended_action'         => self::ACTION_SEND_TEMPLATE,
            'priority'                   => $priority,
            'risk_level'                 => 'medium',
            'opportunity_level'          => 'high',
            'eligible_for_autoassign'    => false,
            'eligible_for_rescue'        => true,
            'eligible_for_supervisor_alert' => false,
            'reason' => 'HOT sin ventana activa. Requiere plantilla de rescate o revisión manual.',
        ];
    }

    /**
     * @param array<string,mixed> $base
     * @return array<string,mixed>
     */
    private function decideRescue(array $base, int $minutesSinceQueue): array
    {
        $riskLevel = $minutesSinceQueue >= 5 * 24 * 60 ? 'high' : 'medium';

        return $base + [
            'recommended_action'         => self::ACTION_RESCUE_FOLLOWUP,
            'priority'                   => 'medium',
            'risk_level'                 => $riskLevel,
            'opportunity_level'          => 'medium',
            'eligible_for_autoassign'    => false,
            'eligible_for_rescue'        => true,
            'eligible_for_supervisor_alert' => false,
            'reason' => 'Conversación en rescate sin cita atribuida. Seguimiento recomendado.',
        ];
    }

    /**
     * @param array<string,mixed> $base
     * @return array<string,mixed>
     */
    private function decideBacklog(array $base): array
    {
        return $base + [
            'recommended_action'         => self::ACTION_HOLD_BACKLOG,
            'priority'                   => 'low',
            'risk_level'                 => 'low',
            'opportunity_level'          => 'low',
            'eligible_for_autoassign'    => false,
            'eligible_for_rescue'        => false,
            'eligible_for_supervisor_alert' => false,
            'reason' => 'Conversación en backlog histórico. Sin acción operativa inmediata.',
        ];
    }

    /**
     * @param array<string,mixed> $base
     * @return array<string,mixed>
     */
    private function decideLost(array $base): array
    {
        return $base + [
            'recommended_action'         => self::ACTION_NO_ACTION_LOST,
            'priority'                   => 'low',
            'risk_level'                 => 'closed',
            'opportunity_level'          => 'low',
            'eligible_for_autoassign'    => false,
            'eligible_for_rescue'        => false,
            'eligible_for_supervisor_alert' => false,
            'reason' => 'Conversación perdida (>30 días). No requiere acción.',
        ];
    }

    /**
     * Public alias so commands can re-summarize a filtered subset of decisions.
     *
     * @param array<int,array<string,mixed>> $decisions
     * @return array<string,mixed>
     */
    public function summarizeDecisions(array $decisions): array
    {
        return $this->summarize($decisions);
    }

    /**
     * @param array<int,array<string,mixed>> $decisions
     * @return array<string,mixed>
     */
    private function summarize(array $decisions): array
    {
        $byAction    = [];
        $byPriority  = [];
        $byRisk      = [];
        $autoassign  = 0;
        $rescue      = 0;
        $supervisor  = 0;
        $converted   = 0;

        foreach ($decisions as $d) {
            $action   = (string) ($d['recommended_action'] ?? 'unknown');
            $priority = (string) ($d['priority'] ?? 'unknown');
            $risk     = (string) ($d['risk_level'] ?? 'unknown');

            $byAction[$action]   = (int) ($byAction[$action] ?? 0) + 1;
            $byPriority[$priority] = (int) ($byPriority[$priority] ?? 0) + 1;
            $byRisk[$risk]       = (int) ($byRisk[$risk] ?? 0) + 1;

            if ((bool) ($d['eligible_for_autoassign'] ?? false)) {
                $autoassign++;
            }
            if ((bool) ($d['eligible_for_rescue'] ?? false)) {
                $rescue++;
            }
            if ((bool) ($d['eligible_for_supervisor_alert'] ?? false)) {
                $supervisor++;
            }
            if ($action === self::ACTION_NO_ACTION_CONVERTED) {
                $converted++;
            }
        }

        arsort($byAction);
        arsort($byPriority);
        arsort($byRisk);

        return [
            'total_evaluated'           => count($decisions),
            'by_recommended_action'     => $byAction,
            'by_priority'               => $byPriority,
            'by_risk_level'             => $byRisk,
            'eligible_for_autoassign'   => $autoassign,
            'eligible_for_rescue'       => $rescue,
            'eligible_for_supervisor_alert' => $supervisor,
            'already_converted'         => $converted,
        ];
    }

    /**
     * @return array<int,object>
     */
    private function conversationRows(CarbonImmutable $asOf): array
    {
        if (!Schema::hasTable('whatsapp_conversations') || !Schema::hasTable('whatsapp_handoffs')) {
            return [];
        }

        $latestHandoffSubquery = DB::table('whatsapp_handoffs')
            ->selectRaw('conversation_id, MAX(id) AS id')
            ->whereIn('status', ['queued', 'assigned', 'expired'])
            ->groupBy('conversation_id');

        $query = DB::table('whatsapp_conversations as c')
            ->joinSub($latestHandoffSubquery, 'latest_h', 'latest_h.conversation_id', '=', 'c.id')
            ->join('whatsapp_handoffs as h', 'h.id', '=', 'latest_h.id')
            ->select([
                'c.id as conversation_id',
                'c.assigned_user_id',
                'c.last_message_at',
                'c.handoff_requested_at',
                'c.created_at as conversation_created_at',
                'h.id as handoff_id',
                'h.status as handoff_status',
                'h.topic',
                'h.assigned_agent_id',
                'h.assigned_at as handoff_assigned_at',
                'h.queued_at',
                'h.created_at as handoff_created_at',
            ])
            ->where('c.needs_human', true)
            ->whereIn('h.topic', self::HOT_TOPICS);

        if (Schema::hasColumn('whatsapp_conversations', 'closed_at')) {
            $query->whereNull('c.closed_at');
        }

        if (Schema::hasTable('whatsapp_messages')) {
            $latestInbound = DB::table('whatsapp_messages')
                ->selectRaw('conversation_id, MAX(COALESCE(message_timestamp, created_at)) AS latest_inbound_at')
                ->where('direction', 'inbound')
                ->groupBy('conversation_id');
            $firstOutbound = DB::table('whatsapp_messages')
                ->selectRaw('conversation_id, MIN(COALESCE(message_timestamp, created_at)) AS first_outbound_at')
                ->where('direction', 'outbound')
                ->groupBy('conversation_id');

            $query->leftJoinSub($latestInbound, 'latest_inbound', 'latest_inbound.conversation_id', '=', 'c.id')
                ->leftJoinSub($firstOutbound, 'first_outbound', 'first_outbound.conversation_id', '=', 'c.id')
                ->addSelect(['latest_inbound.latest_inbound_at', 'first_outbound.first_outbound_at']);
        } else {
            $query->addSelect([
                DB::raw('NULL AS latest_inbound_at'),
                DB::raw('NULL AS first_outbound_at'),
            ]);
        }

        return $query->get()->all();
    }

    /**
     * Build map of conversationId → attribution data.
     * Uses persisted whatsapp_operational_booking_attributions + procedure classification.
     *
     * @return array<int,array{has_primary:bool,has_independent:bool,categories:string[]}>
     */
    private function buildAttributionMap(): array
    {
        if (!Schema::hasTable('whatsapp_operational_booking_attributions')) {
            return [];
        }

        $rows = DB::table('whatsapp_operational_booking_attributions')
            ->select(['attributed_conversation_id', 'booking_source', 'form_id', 'confidence'])
            ->whereNotNull('attributed_conversation_id')
            ->get();

        // Collect form IDs for batch procedure lookup
        $formIds = [];
        foreach ($rows as $row) {
            $formId = (int) ($row->form_id ?? 0);
            if ($formId > 0) {
                $formIds[$formId] = true;
            }
        }

        $procedureByFormId = [];
        if ($formIds !== [] && Schema::hasTable('procedimiento_proyectado')) {
            $column = Schema::hasColumn('procedimiento_proyectado', 'procedimiento_nombre')
                ? 'procedimiento_nombre'
                : 'procedimiento_proyectado';
            foreach (DB::table('procedimiento_proyectado')
                ->select(['form_id', $column])
                ->whereIn('form_id', array_keys($formIds))
                ->get() as $proc) {
                $fid = (int) $proc->form_id;
                $procedureByFormId[$fid] = trim((string) ($proc->{$column} ?? ''));
            }
        }

        // Group categories by conversation
        $byConv = [];
        foreach ($rows as $row) {
            $convId = (int) ($row->attributed_conversation_id ?? 0);
            if ($convId <= 0) {
                continue;
            }
            $formId    = (int) ($row->form_id ?? 0);
            $source    = (string) ($row->booking_source ?? '');
            $procedure = $formId > 0 ? ($procedureByFormId[$formId] ?? '') : '';

            // bot_api bookings without a procedure lookup → treat as confirmed service
            $category = $procedure !== ''
                ? $this->classifyServiceCategory($procedure)
                : ($source === 'bot_api' ? 'ophthalmology_consult' : 'other');

            $byConv[$convId][] = $category;
        }

        // Apply companion logic and resolve flags
        $map = [];
        foreach ($byConv as $convId => $categories) {
            $hasOphthalmology = in_array('ophthalmology_consult', $categories, true);
            $hasPrimary       = false;
            $hasIndependent   = false;

            foreach ($categories as $category) {
                $isCompanion = $category === 'optometry' && $hasOphthalmology;
                if ($isCompanion) {
                    continue;
                }
                $hasIndependent = true;
                if (in_array($category, ['ophthalmology_consult', 'optometry', 'follow_up_review'], true)) {
                    $hasPrimary = true;
                }
            }

            $map[$convId] = [
                'has_primary'     => $hasPrimary,
                'has_independent' => $hasIndependent,
                'categories'      => $categories,
            ];
        }

        return $map;
    }

    private function classifyBucket(object $row, CarbonImmutable $asOf): string
    {
        $ageAt = $this->parseAt($row->queued_at ?? null)
            ?? $this->parseAt($row->handoff_requested_at ?? null)
            ?? $this->parseAt($row->last_message_at ?? null)
            ?? $this->parseAt($row->conversation_created_at ?? null);

        if ($ageAt === null) {
            return 'unknown';
        }

        $ageMinutes = max(0, $ageAt->diffInMinutes($asOf, false));

        if ($ageMinutes <= 24 * 60) {
            $latestInbound = $this->parseAt($row->latest_inbound_at ?? null);
            $windowOpen    = $latestInbound !== null
                             && $latestInbound->greaterThanOrEqualTo($asOf->subHours(24));

            return $windowOpen ? 'hot_open' : 'hot_needs_template';
        }

        if ($ageMinutes <= 7 * 24 * 60) {
            return 'rescue';
        }

        if ($ageMinutes <= 30 * 24 * 60) {
            return 'backlog';
        }

        return 'lost';
    }

    private function classifyServiceCategory(string $procedure): string
    {
        $n = $this->normalizeText($procedure);

        foreach (['anestesiolog', 'anestesia', 'preop', 'pre operatorio', 'pre-op'] as $needle) {
            if (str_contains($n, $needle)) {
                return 'preop_or_anesthesia';
            }
        }
        foreach (['optometri', 'examen optometrico'] as $needle) {
            if (str_contains($n, $needle)) {
                return 'optometry';
            }
        }
        foreach (['tomografia', 'oct', 'campimetria', 'campo visual', 'biometria', 'ecografia',
                   'retinografia', 'paquimetria', 'topografia', 'microscopia', 'imagenes', 'imagen'] as $needle) {
            if (str_contains($n, $needle)) {
                return 'diagnostic';
            }
        }
        foreach (['revision de examenes', 'revision examenes'] as $needle) {
            if (str_contains($n, $needle)) {
                return 'follow_up_review';
            }
        }
        foreach (['oftalmolog', 'consulta', 'control', 'evaluacion', 'valoracion'] as $needle) {
            if (str_contains($n, $needle)) {
                return 'ophthalmology_consult';
            }
        }

        return 'other';
    }

    private function normalizeText(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;

        return strtolower(trim($value));
    }

    private function parseAt(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return CarbonImmutable::parse((string) $value);
    }

    // ── Topic / category helpers ──────────────────────────────────────────────

    private const TOPIC_CATEGORY_MAP = [
        'captacion_agendar'           => 'captacion',
        'agenda_sin_disponibilidad'   => 'captacion',
        'operacion_cita_vigente'      => 'operacion',
        'operacion_reagenda'          => 'operacion',
        'faq_escalada'                => 'ambiguo',
    ];

    private const TOPIC_LABEL_MAP = [
        'captacion_agendar'           => 'Nuevo paciente — agendar',
        'agenda_sin_disponibilidad'   => 'Sin disponibilidad — requiere alternativa',
        'operacion_cita_vigente'      => 'Paciente con cita próxima',
        'operacion_reagenda'          => 'Solicita cambio de cita',
        'faq_escalada'                => 'Consulta escalada',
    ];

    private const CATEGORY_LABEL_MAP = [
        'captacion' => 'Captación',
        'operacion' => 'Operación',
        'ambiguo'   => 'FAQ / Ambiguo',
    ];

    public static function topicCategory(string $topic): string
    {
        return self::TOPIC_CATEGORY_MAP[$topic] ?? 'ambiguo';
    }

    public static function topicLabel(string $topic): string
    {
        return self::TOPIC_LABEL_MAP[$topic] ?? 'No clasificado';
    }

    public static function categoryLabel(string $category): string
    {
        return self::CATEGORY_LABEL_MAP[$category] ?? 'No clasificado';
    }

    /** @return string[] */
    public static function categoryTopics(string $category): array
    {
        return array_keys(array_filter(self::TOPIC_CATEGORY_MAP, fn (string $c) => $c === $category));
    }
}
