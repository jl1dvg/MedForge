<?php

namespace App\Modules\Whatsapp\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WhatsappAttributedAppointmentSourceService
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public function attributedAppointments(\DateTimeInterface $from, \DateTimeInterface $to, ?int $roleId = null, ?int $agentId = null): array
    {
        $fromSql = $from->format('Y-m-d H:i:s');
        $toSql = $to->format('Y-m-d H:i:s');

        $bot = $this->botAppointments($fromSql, $toSql, $roleId, $agentId);
        $manual = $this->manualAppointments($fromSql, $toSql, $roleId, $agentId, $this->botExclusions($fromSql, $toSql));

        $records = array_merge($bot, $manual);
        usort($records, static fn (array $a, array $b): int => strcmp((string) ($b['booking_created_at'] ?? ''), (string) ($a['booking_created_at'] ?? '')));

        return $this->applyServiceClassification($records);
    }

    /**
     * @return array<string,mixed>
     */
    public function humanAttributionSummary(\DateTimeInterface $from, \DateTimeInterface $to, ?int $roleId = null, ?int $agentId = null): array
    {
        $records = array_values(array_filter(
            $this->attributedAppointments($from, $to, $roleId, $agentId),
            static fn (array $record): bool => ($record['booking_source'] ?? '') !== 'bot_api'
        ));

        $strongSlots = [];
        $strongForms = [];
        $strongConversations = [];
        $strongPatients = [];
        $mediumSlots = [];
        $mediumForms = [];
        $mediumConversations = [];
        $mediumPatients = [];
        $weakSlots = [];
        $trendCounts = [];
        $agentGroups = [];
        $sedeGroups = [];
        $sourceGroups = [];

        foreach ($records as $record) {
            $slotKey = (string) ($record['slot_key'] ?? $record['observed_booking_key'] ?? '');
            $formId = (string) ($record['form_id'] ?? $slotKey);
            $conversationId = (int) ($record['conversation_id'] ?? 0);
            $hcNumber = (string) ($record['patient_hc_number'] ?? '');
            $window = (string) ($record['attribution_window'] ?? '');

            if ($window === 'strong') {
                $strongSlots[$slotKey] = true;
                $strongForms[$formId] = true;
                $strongConversations[$conversationId] = true;
                $strongPatients[$hcNumber] = true;
                $mediumSlots[$slotKey] = true;
                $mediumForms[$formId] = true;
                $mediumConversations[$conversationId] = true;
                $mediumPatients[$hcNumber] = true;
                $trendDate = substr((string) ($record['booking_created_at'] ?? ''), 0, 10);
                if ($trendDate !== '') {
                    $trendCounts[$trendDate] = ($trendCounts[$trendDate] ?? 0) + 1;
                }
                $this->addGroupedRecord($agentGroups, $record, 'agent_id', 'agent_name', $slotKey, $conversationId, $hcNumber);
                $this->addGroupedRecord($sedeGroups, $record, 'sede_nombre', 'sede_nombre', $slotKey, $conversationId, $hcNumber);
                $this->addGroupedRecord($sourceGroups, $record, 'source_category', 'source_label', $slotKey, $conversationId, $hcNumber);
                continue;
            }

            if ($window === 'medium') {
                $mediumSlots[$slotKey] = true;
                $mediumForms[$formId] = true;
                $mediumConversations[$conversationId] = true;
                $mediumPatients[$hcNumber] = true;
                continue;
            }

            if ($window === 'weak') {
                $weakSlots[$slotKey] = true;
            }
        }

        ksort($trendCounts);

        return [
            'summary' => [
                'human_attributed_appointments_strong' => count($strongSlots),
                'human_attributed_forms_strong' => count($strongForms),
                'human_attributed_appointment_conversations_strong' => count(array_filter($strongConversations)),
                'human_attributed_appointment_patients_strong' => count(array_filter($strongPatients, static fn (string $value): bool => $value !== '')),
                'human_attributed_appointments_medium' => count($mediumSlots),
                'human_attributed_forms_medium' => count($mediumForms),
                'human_attributed_appointment_conversations_medium' => count(array_filter($mediumConversations)),
                'human_attributed_appointment_patients_medium' => count(array_filter($mediumPatients, static fn (string $value): bool => $value !== '')),
                'human_attributed_appointments_weak' => count($weakSlots),
            ],
            'trend_rows' => array_map(
                static fn (string $date, int $total): array => ['period_date' => $date, 'total' => $total],
                array_keys($trendCounts),
                array_values($trendCounts)
            ),
            'by_agent' => array_slice($this->groupRows($agentGroups, 'user_id', 'agent_name'), 0, 20),
            'by_sede' => array_slice($this->groupRows($sedeGroups, null, 'sede_nombre'), 0, 20),
            'by_source' => array_slice($this->groupRows($sourceGroups, 'source_category', 'source_label'), 0, 20),
            'details' => array_slice(array_values(array_filter($records, static fn (array $record): bool => ($record['attribution_window'] ?? '') === 'strong')), 0, 10000),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function botAppointments(string $fromSql, string $toSql, ?int $roleId, ?int $agentId): array
    {
        if (!Schema::hasTable('whatsapp_sigcenter_bookings')) {
            return [];
        }

        $bookingColumn = static fn (string $column): string => Schema::hasColumn('whatsapp_sigcenter_bookings', $column)
            ? 'b.' . $column
            : 'NULL';
        $attributionColumn = static fn (string $column): string => Schema::hasTable('whatsapp_conversation_attributions') && Schema::hasColumn('whatsapp_conversation_attributions', $column)
            ? 'a.' . $column
            : 'NULL';
        $attributionJoin = Schema::hasTable('whatsapp_conversation_attributions')
            ? 'LEFT JOIN whatsapp_conversation_attributions a ON a.conversation_id = b.conversation_id'
            : 'LEFT JOIN (SELECT NULL AS conversation_id, NULL AS source_category, NULL AS source_type, NULL AS source_id, NULL AS headline, NULL AS initial_intent, NULL AS patient_segment) a ON 1 = 0';
        $userJoin = Schema::hasTable('users')
            ? 'LEFT JOIN users u_assigned ON u_assigned.id = c.assigned_user_id'
            : '';

        $where = 'COALESCE(b.booked_at, b.created_at) >= ? AND COALESCE(b.booked_at, b.created_at) < ? AND b.status IN ("created", "confirmed")';
        $params = [$fromSql, $toSql];
        $scope = $this->conversationScopeFilterSql('c', 'u_assigned', $roleId, $agentId);
        if ($scope['where'] !== '') {
            $where .= ' AND ' . $scope['where'];
            $params = array_merge($params, array_values($scope['params']));
        }

        $rows = DB::select(
            'SELECT b.id,
                    b.conversation_id,
                    ' . $bookingColumn('wa_number') . ' AS wa_number,
                    ' . $bookingColumn('patient_hc_number') . ' AS patient_hc_number,
                    ' . $bookingColumn('patient_full_name') . ' AS patient_full_name,
                    ' . $bookingColumn('sede_nombre') . ' AS sede_nombre,
                    ' . $bookingColumn('medico_nombre') . ' AS medico_nombre,
                    ' . $bookingColumn('procedimiento_nombre') . ' AS procedimiento_nombre,
                    ' . $bookingColumn('fecha_inicio') . ' AS fecha_inicio,
                    ' . $bookingColumn('booked_at') . ' AS booked_at,
                    b.created_at,
                    COALESCE(NULLIF(' . $attributionColumn('source_category') . ', ""), "unknown") AS source_category,
                    ' . $attributionColumn('source_type') . ' AS source_type,
                    ' . $attributionColumn('source_id') . ' AS source_id,
                    ' . $attributionColumn('headline') . ' AS headline,
                    ' . $attributionColumn('initial_intent') . ' AS initial_intent,
                    ' . $attributionColumn('patient_segment') . ' AS patient_segment,
                    c.assigned_user_id,
                    ' . $this->agentNameSql('u_assigned', 'c.assigned_user_id', 'Agente #') . ' AS assigned_agent_name
             FROM whatsapp_sigcenter_bookings b
             LEFT JOIN whatsapp_conversations c ON c.id = b.conversation_id
             ' . $userJoin . '
             ' . $attributionJoin . '
             WHERE ' . $where . '
             ORDER BY COALESCE(b.booked_at, b.created_at) DESC
             LIMIT 10000',
            $params
        );

        return array_map(function (object $row): array {
            $source = (string) ($row->source_category ?? 'unknown');
            $procedure = (string) ($row->procedimiento_nombre ?? '');
            $appointmentType = $this->appointmentTypeForProcedure($procedure, (string) ($row->source_type ?? ''));
            $bookingAt = $row->booked_at !== null ? (string) $row->booked_at : (string) ($row->created_at ?? '');
            $appointmentDate = $this->dateOnly($row->fecha_inicio ?? null);
            $appointmentTime = $this->timeOnly($row->fecha_inicio ?? null);

            return [
                'booking_source' => 'bot_api',
                'booking_type' => 'Bot / integración',
                'observed_booking_key' => 'whatsapp_sigcenter_bookings:' . (int) ($row->id ?? 0),
                'booking_id' => (int) ($row->id ?? 0),
                'form_id' => null,
                'conversation_id' => (int) ($row->conversation_id ?? 0),
                'wa_number' => (string) ($row->wa_number ?? ''),
                'patient_hc_number' => (string) ($row->patient_hc_number ?? ''),
                'patient_name' => (string) ($row->patient_full_name ?? ''),
                'source_category' => $source,
                'source_label' => $this->sourceCategoryLabel($source),
                'source_type' => (string) ($row->source_type ?? ''),
                'source_id' => (string) ($row->source_id ?? ''),
                'campaign_headline' => (string) ($row->headline ?? ''),
                'initial_intent' => (string) ($row->initial_intent ?? ''),
                'initial_intent_label' => $this->initialIntentLabel((string) ($row->initial_intent ?? '')),
                'patient_segment' => (string) ($row->patient_segment ?? ''),
                'appointment_type' => $appointmentType['key'],
                'appointment_type_label' => $appointmentType['label'],
                'appointment_date' => $appointmentDate,
                'appointment_time' => $appointmentTime,
                'booking_created_at' => $bookingAt,
                'sede_nombre' => (string) ($row->sede_nombre ?? ''),
                'doctor' => (string) ($row->medico_nombre ?? ''),
                'medico_nombre' => (string) ($row->medico_nombre ?? ''),
                'procedure' => $procedure,
                'procedimiento_nombre' => $procedure,
                'agent_id' => (int) ($row->assigned_user_id ?? 0),
                'agent_name' => (string) ($row->assigned_agent_name ?? ''),
                'confidence' => 'observed',
                'attribution_window' => 'observed',
                'source_table' => 'whatsapp_sigcenter_bookings',
                'slot_key' => (string) ($row->patient_hc_number ?? '') . '|' . $appointmentDate . '|' . $appointmentTime,
                'raw_payload' => [],
            ];
        }, $rows);
    }

    /**
     * @param array{conversation_dates:array<string,true>,hc_slots:array<string,true>,hc_dates:array<string,true>} $botExclusions
     * @return array<int,array<string,mixed>>
     */
    private function manualAppointments(string $fromSql, string $toSql, ?int $roleId, ?int $agentId, array $botExclusions): array
    {
        if (!Schema::hasTable('procedimiento_proyectado')
            || !Schema::hasColumn('procedimiento_proyectado', 'hc_number')
            || !Schema::hasColumn('procedimiento_proyectado', 'fecha')
            || !Schema::hasTable('whatsapp_conversations')) {
            return [];
        }

        $events = $this->humanAppointmentEventRows($fromSql, $toSql, $roleId, $agentId);
        if ($events === []) {
            return [];
        }

        $conversationsByHc = $this->conversationsByHc($events);
        if ($conversationsByHc === []) {
            return [];
        }

        $appointments = $this->procedureAppointments(array_keys($conversationsByHc));
        if ($appointments === []) {
            return [];
        }

        $records = [];
        foreach ($appointments as $appointment) {
            $hcNumber = trim((string) ($appointment->hc_number ?? ''));
            $appointmentDate = $this->dateOnly($appointment->fecha ?? null);
            if ($hcNumber === '' || $appointmentDate === '' || empty($conversationsByHc[$hcNumber])) {
                continue;
            }

            $appointmentTime = $this->timeOnly($appointment->hora ?? null);
            $createdAt = isset($appointment->created_at) ? Carbon::parse((string) $appointment->created_at) : null;
            if ($createdAt === null) {
                continue;
            }

            $slotKey = $hcNumber . '|' . $appointmentDate . '|' . $appointmentTime;
            if (isset($botExclusions['hc_slots'][$slotKey])
                || (isset($botExclusions['hc_dates'][$hcNumber . '|' . $appointmentDate]) && $appointmentTime === '')
            ) {
                continue;
            }

            foreach ($conversationsByHc[$hcNumber] as $conversation) {
                $conversationId = (int) $conversation['conversation_id'];
                if (isset($botExclusions['conversation_dates'][$conversationId . '|' . $appointmentDate])) {
                    continue;
                }

                $firstHumanAt = $conversation['first_human_at'];
                $lastHumanAt = $conversation['last_human_at'];
                $appointmentDay = Carbon::parse($appointmentDate)->startOfDay();
                if ($appointmentDay->lessThan($firstHumanAt->copy()->startOfDay()) || $appointmentDay->greaterThan($firstHumanAt->copy()->addDays(30)->endOfDay())) {
                    continue;
                }

                $window = $this->manualAttributionWindow($createdAt, $firstHumanAt, $lastHumanAt);
                if ($window === null) {
                    continue;
                }

                $procedureName = trim((string) ($appointment->procedimiento_nombre ?? ''));
                if ($procedureName === '') {
                    $procedureName = trim((string) ($appointment->procedimiento_proyectado ?? ''));
                }
                $appointmentType = $this->appointmentTypeForProcedure($procedureName, '');
                $agentIdForGroup = (int) ($conversation['last_agent_id'] ?? 0);
                $sourceCategory = (string) ($conversation['source_category'] ?? 'unknown');
                $sedeNombre = trim((string) ($appointment->sede_departamento ?? '')) ?: 'Sin sede';
                $formId = (int) ($appointment->form_id ?? 0);

                $records[$slotKey . '|' . $conversationId] = [
                    'booking_source' => 'manual_sigcenter',
                    'booking_type' => 'Humano atribuido',
                    'observed_booking_key' => 'procedimiento_proyectado:' . $formId,
                    'booking_id' => null,
                    'form_id' => $formId > 0 ? $formId : null,
                    'conversation_id' => $conversationId,
                    'wa_number' => (string) ($conversation['wa_number'] ?? ''),
                    'patient_hc_number' => $hcNumber,
                    'patient_name' => '',
                    'source_category' => $sourceCategory,
                    'source_label' => $this->sourceCategoryLabel($sourceCategory),
                    'source_type' => '',
                    'source_id' => '',
                    'campaign_headline' => '',
                    'initial_intent' => '',
                    'initial_intent_label' => '',
                    'patient_segment' => '',
                    'appointment_type' => $appointmentType['key'],
                    'appointment_type_label' => $appointmentType['label'],
                    'appointment_date' => $appointmentDate,
                    'appointment_time' => $appointmentTime,
                    'booking_created_at' => $createdAt->format('Y-m-d H:i:s'),
                    'sede_nombre' => $sedeNombre,
                    'doctor' => (string) ($appointment->medico_nombre ?? $appointment->doctor ?? $appointment->trabajador_nombre ?? ''),
                    'medico_nombre' => (string) ($appointment->medico_nombre ?? $appointment->doctor ?? $appointment->trabajador_nombre ?? ''),
                    'procedure' => $procedureName,
                    'procedimiento_nombre' => $procedureName,
                    'agent_id' => $agentIdForGroup,
                    'agent_name' => '',
                    'confidence' => $window,
                    'attribution_window' => $window,
                    'source_table' => 'procedimiento_proyectado',
                    'slot_key' => $slotKey,
                    'first_human_at' => $firstHumanAt->format('Y-m-d H:i:s'),
                    'last_human_at' => $lastHumanAt->format('Y-m-d H:i:s'),
                    'raw_payload' => [],
                ];
            }
        }

        $agentNames = $this->agentNamesById(array_values(array_filter(array_map(
            static fn (array $record): int => (int) ($record['agent_id'] ?? 0),
            $records
        ))));

        return array_values(array_map(static function (array $record) use ($agentNames): array {
            $agentId = (int) ($record['agent_id'] ?? 0);
            $record['agent_name'] = $agentId > 0 ? (string) ($agentNames[$agentId] ?? ('Agente #' . $agentId)) : '';
            return $record;
        }, $records));
    }

    private function manualAttributionWindow(Carbon $createdAt, Carbon $firstHumanAt, Carbon $lastHumanAt): ?string
    {
        $start = $firstHumanAt->copy()->subMinutes(15);
        if ($createdAt->betweenIncluded($start, $lastHumanAt->copy()->addDay())) {
            return 'strong';
        }
        if ($createdAt->betweenIncluded($start, $lastHumanAt->copy()->addDays(3))) {
            return 'medium';
        }
        if ($createdAt->betweenIncluded($start, $firstHumanAt->copy()->addDays(30))) {
            return 'weak';
        }
        return null;
    }

    /**
     * @return array<int,object>
     */
    private function humanAppointmentEventRows(string $fromSql, string $toSql, ?int $roleId, ?int $agentId): array
    {
        $allowedAgents = $this->allowedAgentIds($roleId, $agentId);
        if (($roleId !== null && $roleId > 0 || $agentId !== null && $agentId > 0) && $allowedAgents === []) {
            return [];
        }

        $patientHcSql = Schema::hasColumn('whatsapp_conversations', 'patient_hc_number') ? 'c.patient_hc_number' : 'NULL';
        $assignedUserSql = Schema::hasColumn('whatsapp_conversations', 'assigned_user_id') ? 'c.assigned_user_id' : 'NULL';
        $attributionJoin = Schema::hasTable('whatsapp_conversation_attributions')
            ? 'LEFT JOIN whatsapp_conversation_attributions attr ON attr.conversation_id = c.id'
            : 'LEFT JOIN (SELECT NULL AS conversation_id, NULL AS source_category) attr ON 1 = 0';
        $selectPrefix = 'c.id AS conversation_id, c.wa_number, ' . $patientHcSql . ' AS patient_hc_number, ' . $assignedUserSql . ' AS assigned_user_id, COALESCE(NULLIF(attr.source_category, ""), "unknown") AS source_category';
        $queries = [];
        $params = [];

        if (Schema::hasTable('whatsapp_messages')
            && Schema::hasColumn('whatsapp_messages', 'sender_type')
            && Schema::hasColumn('whatsapp_messages', 'sender_id')
        ) {
            $eventAt = 'COALESCE(m.message_timestamp, m.created_at)';
            $queries[] = 'SELECT ' . $selectPrefix . ', ' . $eventAt . ' AS event_at, m.sender_id AS agent_id
                          FROM whatsapp_messages m
                          INNER JOIN whatsapp_conversations c ON c.id = m.conversation_id
                          ' . $attributionJoin . '
                          WHERE m.direction = "outbound"
                            AND m.sender_type = "agent"
                            AND m.sender_id IS NOT NULL
                            AND ' . $eventAt . ' >= ?
                            AND ' . $eventAt . ' < ?';
            $params[] = $fromSql;
            $params[] = $toSql;
        }

        if (Schema::hasTable('whatsapp_handoffs')
            && Schema::hasColumn('whatsapp_handoffs', 'assigned_agent_id')
        ) {
            $eventAt = Schema::hasColumn('whatsapp_handoffs', 'assigned_at')
                ? 'COALESCE(h.assigned_at, h.created_at)'
                : 'h.created_at';
            $queries[] = 'SELECT ' . $selectPrefix . ', ' . $eventAt . ' AS event_at, h.assigned_agent_id AS agent_id
                          FROM whatsapp_handoffs h
                          INNER JOIN whatsapp_conversations c ON c.id = h.conversation_id
                          ' . $attributionJoin . '
                          WHERE h.assigned_agent_id IS NOT NULL
                            AND ' . $eventAt . ' >= ?
                            AND ' . $eventAt . ' < ?';
            $params[] = $fromSql;
            $params[] = $toSql;
        }

        if (Schema::hasTable('whatsapp_handoff_events')
            && Schema::hasTable('whatsapp_handoffs')
            && Schema::hasColumn('whatsapp_handoff_events', 'actor_user_id')
        ) {
            $queries[] = 'SELECT ' . $selectPrefix . ', e.created_at AS event_at, e.actor_user_id AS agent_id
                          FROM whatsapp_handoff_events e
                          INNER JOIN whatsapp_handoffs h ON h.id = e.handoff_id
                          INNER JOIN whatsapp_conversations c ON c.id = h.conversation_id
                          ' . $attributionJoin . '
                          WHERE e.actor_user_id IS NOT NULL
                            AND e.created_at >= ?
                            AND e.created_at < ?';
            $params[] = $fromSql;
            $params[] = $toSql;
        }

        if (Schema::hasColumn('whatsapp_conversations', 'assigned_user_id')) {
            $eventAt = Schema::hasColumn('whatsapp_conversations', 'assigned_at')
                ? 'COALESCE(c.assigned_at, c.handoff_requested_at, c.updated_at, c.created_at)'
                : 'COALESCE(c.handoff_requested_at, c.updated_at, c.created_at)';
            $queries[] = 'SELECT ' . $selectPrefix . ', ' . $eventAt . ' AS event_at, c.assigned_user_id AS agent_id
                          FROM whatsapp_conversations c
                          ' . $attributionJoin . '
                          WHERE c.assigned_user_id IS NOT NULL
                            AND ' . $eventAt . ' >= ?
                            AND ' . $eventAt . ' < ?';
            $params[] = $fromSql;
            $params[] = $toSql;
        }

        if ($queries === []) {
            return [];
        }

        $rows = DB::select(implode(' UNION ALL ', $queries), $params);
        if ($allowedAgents === null) {
            return $rows;
        }

        return array_values(array_filter($rows, static function (object $row) use ($allowedAgents): bool {
            $agentId = (int) ($row->agent_id ?? 0);
            $assignedUserId = (int) ($row->assigned_user_id ?? 0);
            return ($agentId > 0 && isset($allowedAgents[$agentId]))
                || ($assignedUserId > 0 && isset($allowedAgents[$assignedUserId]));
        }));
    }

    /**
     * @param array<int,object> $events
     * @return array<string,array<int,array<string,mixed>>>
     */
    private function conversationsByHc(array $events): array
    {
        $conversations = [];
        $phoneTails = [];

        foreach ($events as $row) {
            $conversationId = (int) ($row->conversation_id ?? 0);
            $eventAt = isset($row->event_at) ? Carbon::parse((string) $row->event_at) : null;
            if ($conversationId <= 0 || $eventAt === null) {
                continue;
            }

            $waNumber = (string) ($row->wa_number ?? '');
            $tail = $this->phoneTail($waNumber);
            $patientHcNumber = trim((string) ($row->patient_hc_number ?? ''));
            if ($patientHcNumber === '' && $tail !== '') {
                $phoneTails[$tail] = true;
            }
            $agentFromEvent = (int) ($row->agent_id ?? 0);
            $assignedUserId = (int) ($row->assigned_user_id ?? 0);
            $agentForEvent = $agentFromEvent > 0 ? $agentFromEvent : ($assignedUserId > 0 ? $assignedUserId : null);

            if (!isset($conversations[$conversationId])) {
                $conversations[$conversationId] = [
                    'conversation_id' => $conversationId,
                    'wa_number' => $waNumber,
                    'phone_tail' => $tail,
                    'patient_hc_number' => $patientHcNumber,
                    'source_category' => (string) ($row->source_category ?? 'unknown'),
                    'first_human_at' => $eventAt,
                    'last_human_at' => $eventAt,
                    'last_agent_id' => $agentForEvent,
                ];
                continue;
            }

            if ($eventAt->lessThan($conversations[$conversationId]['first_human_at'])) {
                $conversations[$conversationId]['first_human_at'] = $eventAt;
            }
            if ($eventAt->greaterThanOrEqualTo($conversations[$conversationId]['last_human_at'])) {
                $conversations[$conversationId]['last_human_at'] = $eventAt;
                if ($agentForEvent !== null) {
                    $conversations[$conversationId]['last_agent_id'] = $agentForEvent;
                }
            }
        }

        $hcByTail = $this->patientHcByPhoneTail(array_keys($phoneTails));
        $byHc = [];
        foreach ($conversations as $conversation) {
            $hcNumber = (string) $conversation['patient_hc_number'];
            if ($hcNumber === '' && $conversation['phone_tail'] !== '') {
                $hcNumber = (string) ($hcByTail[$conversation['phone_tail']] ?? '');
            }
            if ($hcNumber === '') {
                continue;
            }
            $conversation['patient_hc_number'] = $hcNumber;
            $byHc[$hcNumber][] = $conversation;
        }

        return $byHc;
    }

    /**
     * @param array<int,string> $hcNumbers
     * @return array<int,object>
     */
    private function procedureAppointments(array $hcNumbers): array
    {
        $appointments = [];
        foreach (array_chunk($hcNumbers, 500) as $hcChunk) {
            $query = DB::table('procedimiento_proyectado')
                ->select(['form_id', 'hc_number', 'fecha', 'created_at'])
                ->whereIn('hc_number', $hcChunk)
                ->whereNotNull('fecha');

            foreach (['hora', 'sede_departamento', 'medico_nombre', 'trabajador_nombre', 'doctor', 'procedimiento_nombre', 'procedimiento_proyectado'] as $optionalColumn) {
                if (Schema::hasColumn('procedimiento_proyectado', $optionalColumn)) {
                    $query->addSelect($optionalColumn);
                }
            }
            if (Schema::hasColumn('procedimiento_proyectado', 'sigcenter_present')) {
                $query->where('sigcenter_present', 1);
            }

            foreach ($query->get() as $appointment) {
                $appointments[] = $appointment;
            }
        }

        return $appointments;
    }

    /**
     * @return array{conversation_dates:array<string,true>,hc_slots:array<string,true>,hc_dates:array<string,true>}
     */
    private function botExclusions(string $fromSql, string $toSql): array
    {
        $exclusions = ['conversation_dates' => [], 'hc_slots' => [], 'hc_dates' => []];
        if (!Schema::hasTable('whatsapp_sigcenter_bookings')) {
            return $exclusions;
        }

        $query = DB::table('whatsapp_sigcenter_bookings')
            ->select(['conversation_id', 'created_at', 'booked_at', 'status'])
            ->whereIn('status', ['created', 'confirmed'])
            ->where('created_at', '>=', $fromSql)
            ->where('created_at', '<', $toSql);

        if (Schema::hasColumn('whatsapp_sigcenter_bookings', 'patient_hc_number')) {
            $query->addSelect('patient_hc_number');
        }
        if (Schema::hasColumn('whatsapp_sigcenter_bookings', 'fecha_inicio')) {
            $query->addSelect('fecha_inicio');
        }

        foreach ($query->get() as $booking) {
            $conversationId = (int) ($booking->conversation_id ?? 0);
            $hcNumber = trim((string) ($booking->patient_hc_number ?? ''));
            $appointmentDate = $this->dateOnly($booking->fecha_inicio ?? null);
            if ($appointmentDate === '') {
                $appointmentDate = $this->dateOnly($booking->booked_at ?? $booking->created_at ?? null);
            }
            $appointmentTime = $this->timeOnly($booking->fecha_inicio ?? null);
            if ($conversationId > 0 && $appointmentDate !== '') {
                $exclusions['conversation_dates'][$conversationId . '|' . $appointmentDate] = true;
            }
            if ($hcNumber !== '' && $appointmentDate !== '') {
                $exclusions['hc_dates'][$hcNumber . '|' . $appointmentDate] = true;
                $exclusions['hc_slots'][$hcNumber . '|' . $appointmentDate . '|' . $appointmentTime] = true;
            }
        }

        return $exclusions;
    }

    /**
     * @return array<string,string>
     */
    private function patientHcByPhoneTail(array $phoneTails): array
    {
        if ($phoneTails === [] || !Schema::hasTable('patient_data') || !Schema::hasColumn('patient_data', 'hc_number') || !Schema::hasColumn('patient_data', 'celular')) {
            return [];
        }

        $wanted = array_fill_keys($phoneTails, true);
        $matches = [];
        foreach (DB::table('patient_data')->select(['hc_number', 'celular'])->whereNotNull('celular')->get() as $row) {
            $tail = $this->phoneTail((string) ($row->celular ?? ''));
            $hcNumber = trim((string) ($row->hc_number ?? ''));
            if ($tail !== '' && $hcNumber !== '' && isset($wanted[$tail])) {
                $matches[$tail] = $hcNumber;
            }
        }
        return $matches;
    }

    private function addGroupedRecord(array &$groups, array $record, string $keyField, string $labelField, string $slotKey, int $conversationId, string $hcNumber): void
    {
        $key = (string) ($record[$keyField] ?? '');
        if ($key === '' || $key === '0') {
            return;
        }
        if (!isset($groups[$key])) {
            $groups[$key] = [
                $keyField => $record[$keyField] ?? $key,
                $labelField => $record[$labelField] ?? $key,
                'slot_keys' => [],
                'conversation_ids' => [],
                'patient_hcs' => [],
            ];
        }
        $groups[$key]['slot_keys'][$slotKey] = true;
        $groups[$key]['conversation_ids'][$conversationId] = true;
        $groups[$key]['patient_hcs'][$hcNumber] = true;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function groupRows(array $groups, ?string $idKey, string $labelKey): array
    {
        $rows = array_values(array_map(static function (array $group) use ($idKey, $labelKey): array {
            $row = [
                $labelKey => (string) ($group[$labelKey] ?? ''),
                'appointment_slots' => count($group['slot_keys'] ?? []),
                'conversations' => count($group['conversation_ids'] ?? []),
                'patients' => count($group['patient_hcs'] ?? []),
            ];
            if ($idKey !== null) {
                $rawId = $group[$idKey] ?? ($idKey === 'user_id' ? ($group['agent_id'] ?? null) : null);
                $row[$idKey] = is_numeric($rawId) ? (int) $rawId : (string) ($rawId ?? '');
            }
            return $row;
        }, $groups));

        usort($rows, static fn (array $a, array $b): int => ((int) $b['appointment_slots'] <=> (int) $a['appointment_slots']) ?: strcmp((string) ($a[$labelKey] ?? ''), (string) ($b[$labelKey] ?? '')));
        return $rows;
    }

    /**
     * @return ?array<int,true>
     */
    private function allowedAgentIds(?int $roleId, ?int $agentId): ?array
    {
        if ($agentId !== null && $agentId > 0) {
            return [$agentId => true];
        }
        if ($roleId === null || $roleId <= 0 || !Schema::hasTable('users') || !Schema::hasColumn('users', 'role_id')) {
            return null;
        }

        $ids = [];
        foreach (DB::table('users')->select('id')->where('role_id', $roleId)->get() as $row) {
            $id = (int) ($row->id ?? 0);
            if ($id > 0) {
                $ids[$id] = true;
            }
        }
        return $ids;
    }

    /**
     * @return array{where:string,params:array<int,mixed>}
     */
    private function conversationScopeFilterSql(string $conversationAlias, string $userAlias, ?int $roleId, ?int $agentId): array
    {
        $where = [];
        $params = [];
        if ($agentId !== null && $agentId > 0 && Schema::hasColumn('whatsapp_conversations', 'assigned_user_id')) {
            $where[] = $conversationAlias . '.assigned_user_id = ?';
            $params[] = $agentId;
        } elseif ($roleId !== null && $roleId > 0 && Schema::hasTable('users') && Schema::hasColumn('users', 'role_id') && Schema::hasColumn('whatsapp_conversations', 'assigned_user_id')) {
            $where[] = $userAlias . '.role_id = ?';
            $params[] = $roleId;
        }

        return ['where' => implode(' AND ', $where), 'params' => $params];
    }

    /**
     * @return array<int,string>
     */
    private function agentNamesById(array $agentIds): array
    {
        $agentIds = array_values(array_unique(array_filter(array_map('intval', $agentIds))));
        if ($agentIds === [] || !Schema::hasTable('users')) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($agentIds), '?'));
        $rows = DB::select(
            'SELECT id, ' . $this->agentNameSql(null, 'id', 'Agente #') . ' AS agent_name FROM users WHERE id IN (' . $placeholders . ')',
            $agentIds
        );

        $names = [];
        foreach ($rows as $row) {
            $id = (int) ($row->id ?? 0);
            if ($id > 0) {
                $names[$id] = (string) ($row->agent_name ?? ('Agente #' . $id));
            }
        }
        return $names;
    }

    /**
     * @param array<int,array<string,mixed>> $records
     * @return array<int,array<string,mixed>>
     */
    private function applyServiceClassification(array $records): array
    {
        $records = array_map(function (array $r): array {
            $r['service_category'] = $this->classifyServiceCategory((string) ($r['procedure'] ?? ''));
            return $r;
        }, $records);

        $ophthalmologyByHcDate = [];
        foreach ($records as $r) {
            if (($r['service_category'] ?? '') === 'ophthalmology_consult') {
                $hc = (string) ($r['patient_hc_number'] ?? '');
                $date = (string) ($r['appointment_date'] ?? '');
                if ($hc !== '' && $date !== '') {
                    $ophthalmologyByHcDate[$hc . '|' . $date] = true;
                }
            }
        }

        return array_map(function (array $r) use ($ophthalmologyByHcDate): array {
            $category = (string) ($r['service_category'] ?? 'other');
            $hc = (string) ($r['patient_hc_number'] ?? '');
            $date = (string) ($r['appointment_date'] ?? '');
            $isCompanion = $category === 'optometry'
                && $hc !== ''
                && $date !== ''
                && isset($ophthalmologyByHcDate[$hc . '|' . $date]);

            $r['service_counting_role']          = $isCompanion ? 'companion_service' : 'independent_service';
            $r['is_companion_service']           = $isCompanion;
            $r['is_independent_service']         = !$isCompanion;
            $r['is_primary_clinical_appointment'] = match ($category) {
                'ophthalmology_consult' => true,
                'optometry'             => !$isCompanion,
                'follow_up_review'      => true,
                default                 => false,
            };

            return $r;
        }, $records);
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

        foreach (['tomografia', 'oct', 'campimetria', 'campo visual', 'biometria', 'ecografia', 'retinografia', 'paquimetria', 'topografia', 'microscopia', 'imagenes', 'imagen'] as $needle) {
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

    /**
     * @return array{key:string,label:string}
     */
    private function appointmentTypeForProcedure(string $procedure, string $sourceType = ''): array
    {
        $normalizedSource = strtolower(trim($sourceType));
        $normalizedProcedure = $this->normalizeText($procedure);
        if ($normalizedSource === 'imagenes') {
            return ['key' => 'imagenes', 'label' => 'Imágenes'];
        }
        foreach (['tomografia', 'oct', 'campimetria', 'biometria', 'ecografia', 'retinografia', 'paquimetria', 'topografia', 'imagen', 'imagenes'] as $needle) {
            if (str_contains($normalizedProcedure, $needle)) {
                return ['key' => 'imagenes', 'label' => 'Imágenes'];
            }
        }
        foreach (['cirugia', 'quirurg', 'laser', 'inyeccion', 'yag', 'procedimiento'] as $needle) {
            if (str_contains($normalizedProcedure, $needle)) {
                return ['key' => 'procedimiento', 'label' => 'Procedimiento / cirugía'];
            }
        }
        foreach (['consulta', 'control', 'evaluacion', 'valoracion', 'oftalmolog'] as $needle) {
            if (str_contains($normalizedProcedure, $needle)) {
                return ['key' => 'consulta', 'label' => 'Consulta / control'];
            }
        }
        if ($normalizedSource === 'servicios_oftalmologicos_generales') {
            return ['key' => 'consulta', 'label' => 'Consulta / control'];
        }
        return ['key' => 'otros', 'label' => 'Otro / sin clasificar'];
    }

    private function initialIntentLabel(string $intent): string
    {
        return match ($intent) {
            'booking' => 'Agendar cita',
            'price' => 'Precios',
            'location_hours' => 'Horarios o ubicación',
            'results' => 'Resultados',
            'human_help' => 'Ayuda humana',
            'followup_outbound' => 'Seguimiento saliente',
            'return_patient' => 'Paciente de retorno',
            'post_consultation_followup' => 'Seguimiento postconsulta',
            'general_info' => 'Información general',
            default => $intent !== '' ? ucfirst(str_replace('_', ' ', $intent)) : 'Sin clasificar',
        };
    }

    private function sourceCategoryLabel(string $category): string
    {
        return match ($category) {
            'ad' => 'Ads',
            'organic_direct' => 'Orgánico directo',
            'campaign_outbound' => 'Campaña saliente',
            'patient_return' => 'Paciente de retorno',
            'post_consultation' => 'Post consulta',
            'post_surgery' => 'Post cirugía',
            'support_operational' => 'Soporte operativo',
            default => 'Sin clasificar',
        };
    }

    private function normalizeText(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        return strtolower(trim($value));
    }

    private function phoneTail(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        return $digits !== '' ? substr($digits, -9) : '';
    }

    private function dateOnly(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        return Carbon::parse((string) $value)->toDateString();
    }

    private function timeOnly(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        return Carbon::parse((string) $value)->format('H:i:s');
    }

    private function agentNameSql(?string $tableAlias, ?string $idExpression, ?string $fallbackLabel): string
    {
        $prefix = $tableAlias !== null && $tableAlias !== '' ? $tableAlias . '.' : '';
        $parts = [];
        if (Schema::hasColumn('users', 'first_name') && Schema::hasColumn('users', 'last_name')) {
            $parts[] = $this->nullIfEmpty($this->trimSql($this->concatSql([
                'COALESCE(' . $prefix . 'first_name, "")',
                $this->stringLiteral(' '),
                'COALESCE(' . $prefix . 'last_name, "")',
            ])));
        }
        if (Schema::hasColumn('users', 'nombre')) {
            $parts[] = 'NULLIF(' . $prefix . 'nombre, "")';
        }
        if (Schema::hasColumn('users', 'username')) {
            $parts[] = 'NULLIF(' . $prefix . 'username, "")';
        }
        if ($fallbackLabel !== null) {
            $parts[] = $idExpression !== null && $idExpression !== ''
                ? $this->concatSql([$this->stringLiteral($fallbackLabel), $this->castToTextSql($idExpression)])
                : $this->stringLiteral($fallbackLabel);
        }
        if (count($parts) === 1) {
            return $parts[0];
        }
        return 'COALESCE(' . implode(', ', $parts ?: [$this->stringLiteral('Agente')]) . ')';
    }

    /**
     * @param array<int,string> $parts
     */
    private function concatSql(array $parts): string
    {
        if ($this->isSqlite()) {
            return implode(' || ', $parts);
        }

        return 'CONCAT(' . implode(', ', $parts) . ')';
    }

    private function castToTextSql(string $expression): string
    {
        return $this->isSqlite()
            ? 'CAST(' . $expression . ' AS TEXT)'
            : 'CAST(' . $expression . ' AS CHAR)';
    }

    private function trimSql(string $expression): string
    {
        return 'TRIM(' . $expression . ')';
    }

    private function nullIfEmpty(string $expression): string
    {
        return 'NULLIF(' . $expression . ', "")';
    }

    private function isSqlite(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }

    private function stringLiteral(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
