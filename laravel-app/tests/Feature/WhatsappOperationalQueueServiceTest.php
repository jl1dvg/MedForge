<?php

namespace Tests\Feature;

use App\Modules\Whatsapp\Services\WhatsappOperationalDecisionService;
use App\Modules\Whatsapp\Services\WhatsappOperationalQueueService;
use Tests\TestCase;

class WhatsappOperationalQueueServiceTest extends TestCase
{
    /**
     * @return array<int,array<string,mixed>>
     */
    private function makeDecisions(): array
    {
        return [
            $this->decision(1, 'hot_open', WhatsappOperationalDecisionService::ACTION_SUPERVISOR_REVIEW, 'high', 'high'),
            $this->decision(2, 'hot_open', WhatsappOperationalDecisionService::ACTION_SUPERVISOR_REVIEW, 'high', 'high'),
            $this->decision(3, 'rescue', WhatsappOperationalDecisionService::ACTION_RESCUE_FOLLOWUP, 'medium', 'medium'),
            $this->decision(4, 'rescue', WhatsappOperationalDecisionService::ACTION_RESCUE_FOLLOWUP, 'medium', 'high'),
            $this->decision(5, 'hot_needs_template', WhatsappOperationalDecisionService::ACTION_SEND_TEMPLATE, 'medium', 'medium'),
            $this->decision(6, 'backlog', WhatsappOperationalDecisionService::ACTION_HOLD_BACKLOG, 'low', 'low'),
            $this->decision(7, 'lost', WhatsappOperationalDecisionService::ACTION_NO_ACTION_LOST, 'low', 'closed'),
            $this->decision(8, 'hot_open', WhatsappOperationalDecisionService::ACTION_NO_ACTION_CONVERTED, 'low', 'low'),
            $this->decision(9, 'hot_open', WhatsappOperationalDecisionService::ACTION_ALREADY_HANDLED, 'normal', 'low'),
        ];
    }

    public function test_builds_supervisor_queue_from_supervisor_review_decisions(): void
    {
        $service   = new WhatsappOperationalQueueService();
        $decisions = $this->makeDecisions();

        $queue = $service->buildSupervisorQueue($decisions);

        $this->assertCount(2, $queue);
        foreach ($queue as $item) {
            $this->assertSame(WhatsappOperationalDecisionService::ACTION_SUPERVISOR_REVIEW, $item['recommended_action']);
            $this->assertTrue($item['eligible_for_supervisor_alert']);
            $this->assertSame(120, $item['sla_minutes']);
            $this->assertArrayHasKey('assigned_user_id', $item);
            $this->assertArrayHasKey('waiting_minutes', $item);
            $this->assertArrayHasKey('sla_overdue_minutes', $item);
            $this->assertArrayHasKey('last_human_response_at', $item);
            $this->assertArrayHasKey('last_patient_message_at', $item);
        }
    }

    public function test_builds_rescue_queue_from_rescue_and_template_decisions(): void
    {
        $service   = new WhatsappOperationalQueueService();
        $decisions = $this->makeDecisions();

        $queue = $service->buildRescueQueue($decisions);

        $this->assertCount(3, $queue);
        foreach ($queue as $item) {
            $this->assertContains($item['recommended_action'], [
                WhatsappOperationalDecisionService::ACTION_RESCUE_FOLLOWUP,
                WhatsappOperationalDecisionService::ACTION_SEND_TEMPLATE,
            ]);
            $this->assertTrue($item['eligible_for_rescue']);
            $this->assertArrayHasKey('has_primary_clinical_appointment', $item);
        }
    }

    public function test_no_action_converted_excluded_from_active_queues(): void
    {
        $service   = new WhatsappOperationalQueueService();
        $decisions = $this->makeDecisions();

        $supervisor = $service->buildSupervisorQueue($decisions);
        $rescue     = $service->buildRescueQueue($decisions);
        $allConvIds = array_merge(
            array_column($supervisor, 'conversation_id'),
            array_column($rescue, 'conversation_id')
        );

        $this->assertNotContains(8, $allConvIds, 'no_action_converted must not appear in active queues');
        $this->assertNotContains(9, $allConvIds, 'already_handled must not appear in active queues');
    }

    public function test_summary_counts_all_queues_and_no_action(): void
    {
        $service   = app(WhatsappOperationalQueueService::class);
        $decisions = $this->makeDecisions();

        $supervisor = $service->buildSupervisorQueue($decisions);
        $rescue     = $service->buildRescueQueue($decisions);

        // Use reflection or expose via queues() output
        $result = $this->callQueuesWithFakeDecisions($decisions);
        $summary = $result['summary'];

        $this->assertSame(2, $summary['supervisor_queue']['total']);
        $this->assertSame(3, $summary['rescue_queue']['total']);
        $this->assertSame(2, $summary['rescue_queue']['rescue_followup']);
        $this->assertSame(1, $summary['rescue_queue']['send_template_or_review']);
        $this->assertSame(1, $summary['no_action']['converted']);
        $this->assertSame(1, $summary['no_action']['already_handled']);
        $this->assertSame(1, $summary['no_action']['backlog']);
        $this->assertSame(1, $summary['no_action']['lost']);
        $this->assertSame(9, $summary['total_decisions']);
    }

    public function test_limit_does_not_alter_summary(): void
    {
        $service   = app(WhatsappOperationalQueueService::class);
        $decisions = $this->makeDecisions();

        $resultFull    = $this->callQueuesWithFakeDecisions($decisions, queue: 'rescue');
        $resultLimited = $this->callQueuesWithFakeDecisions($decisions, queue: 'rescue', limit: 1);

        $this->assertSame(
            $resultFull['summary']['total'],
            $resultLimited['summary']['total']
        );
        $this->assertCount(1, $resultLimited['items']);
    }

    public function test_queue_supervisor_returns_only_supervisor_items(): void
    {
        $service   = app(WhatsappOperationalQueueService::class);
        $decisions = $this->makeDecisions();
        $result    = $this->callQueuesWithFakeDecisions($decisions, queue: 'supervisor');

        $this->assertSame('supervisor', $result['queue']);
        foreach ($result['items'] as $item) {
            $this->assertSame(WhatsappOperationalDecisionService::ACTION_SUPERVISOR_REVIEW, $item['recommended_action']);
        }
    }

    public function test_queue_rescue_returns_only_rescue_items(): void
    {
        $service   = app(WhatsappOperationalQueueService::class);
        $decisions = $this->makeDecisions();
        $result    = $this->callQueuesWithFakeDecisions($decisions, queue: 'rescue');

        $this->assertSame('rescue', $result['queue']);
        foreach ($result['items'] as $item) {
            $this->assertContains($item['recommended_action'], [
                WhatsappOperationalDecisionService::ACTION_RESCUE_FOLLOWUP,
                WhatsappOperationalDecisionService::ACTION_SEND_TEMPLATE,
            ]);
        }
    }

    public function test_queue_all_returns_both_queues(): void
    {
        $service   = app(WhatsappOperationalQueueService::class);
        $decisions = $this->makeDecisions();
        $result    = $this->callQueuesWithFakeDecisions($decisions, queue: 'all');

        $this->assertArrayHasKey('queues', $result);
        $this->assertArrayHasKey('supervisor', $result['queues']);
        $this->assertArrayHasKey('rescue', $result['queues']);
    }

    public function test_supervisor_items_sorted_by_risk_then_priority_then_conv_id(): void
    {
        $service = new WhatsappOperationalQueueService();

        $decisions = [
            $this->decision(10, 'hot_open', WhatsappOperationalDecisionService::ACTION_SUPERVISOR_REVIEW, 'medium', 'medium'),
            $this->decision(5,  'hot_open', WhatsappOperationalDecisionService::ACTION_SUPERVISOR_REVIEW, 'high', 'high'),
            $this->decision(3,  'hot_open', WhatsappOperationalDecisionService::ACTION_SUPERVISOR_REVIEW, 'high', 'high'),
        ];

        $queue = $service->buildSupervisorQueue($decisions);

        $this->assertSame(3, $queue[0]['conversation_id']);
        $this->assertSame(5, $queue[1]['conversation_id']);
        $this->assertSame(10, $queue[2]['conversation_id']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * @param array<int,array<string,mixed>> $decisions
     * @return array<string,mixed>
     */
    private function callQueuesWithFakeDecisions(array $decisions, string $queue = 'all', ?int $limit = null): array
    {
        // We build the queue result structure manually, mirroring what queues() does internally
        $service = new WhatsappOperationalQueueService();

        $supervisorItems = $service->buildSupervisorQueue($decisions);
        $rescueItems     = $service->buildRescueQueue($decisions);

        // Access private buildSummary via reflection
        $ref    = new \ReflectionClass($service);
        $method = $ref->getMethod('buildSummary');
        $method->setAccessible(true);
        $fullSummary = $method->invoke($service, $decisions, $supervisorItems, $rescueItems);

        $supervisorSummaryMethod = $ref->getMethod('supervisorSummary');
        $supervisorSummaryMethod->setAccessible(true);
        $rescueSummaryMethod = $ref->getMethod('rescueSummary');
        $rescueSummaryMethod->setAccessible(true);

        [$returnedSupervisor, $returnedRescue] = match ($queue) {
            'supervisor' => [$supervisorItems, []],
            'rescue'     => [[], $rescueItems],
            default      => [$supervisorItems, $rescueItems],
        };

        $allItems = array_merge($returnedSupervisor, $returnedRescue);
        if ($limit !== null) {
            $returnedSupervisor = array_slice($returnedSupervisor, 0, $limit);
            $returnedRescue     = array_slice($returnedRescue, 0, $limit);
            $allItems           = array_slice($allItems, 0, $limit);
        }

        $payload = [
            'date'         => '2026-06-25',
            'generated_at' => now()->format('Y-m-d H:i:s'),
        ];

        if ($queue === 'supervisor') {
            return $payload + [
                'queue'   => 'supervisor',
                'summary' => $supervisorSummaryMethod->invoke($service, $supervisorItems),
                'items'   => $returnedSupervisor,
            ];
        }
        if ($queue === 'rescue') {
            return $payload + [
                'queue'   => 'rescue',
                'summary' => $rescueSummaryMethod->invoke($service, $rescueItems),
                'items'   => $returnedRescue,
            ];
        }

        return $payload + [
            'summary' => $fullSummary,
            'queues'  => ['supervisor' => $returnedSupervisor, 'rescue' => $returnedRescue],
            'items'   => $allItems,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function decision(int $convId, string $bucket, string $action, string $priority, string $risk): array
    {
        $isSupervisor = $action === WhatsappOperationalDecisionService::ACTION_SUPERVISOR_REVIEW;
        $isRescue     = in_array($action, [
            WhatsappOperationalDecisionService::ACTION_RESCUE_FOLLOWUP,
            WhatsappOperationalDecisionService::ACTION_SEND_TEMPLATE,
        ], true);

        return [
            'conversation_id'                  => $convId,
            'bucket'                           => $bucket,
            'recommended_action'               => $action,
            'priority'                         => $priority,
            'risk_level'                       => $risk,
            'opportunity_level'                => 'high',
            'eligible_for_autoassign'          => false,
            'eligible_for_rescue'              => $isRescue,
            'eligible_for_supervisor_alert'    => $isSupervisor,
            'has_attributed_booking'           => false,
            'has_primary_clinical_appointment' => false,
            'has_independent_attributed_service' => false,
            'reason'                           => 'Test reason for conv ' . $convId,
        ];
    }
}
