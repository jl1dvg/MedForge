<?php

namespace App\Modules\Whatsapp\Services;

/**
 * Read-only daily operational report.
 *
 * Aggregates Alert Engine output into a summary suitable for
 * coordinación / gerencia. read_only=true, db_writes=0 always.
 * No messages sent, no events inserted, no scheduler dependency.
 */
class WhatsappOperationalDailyReportService
{
    public function __construct(
        private readonly WhatsappOperationalAlertService $alertService
            = new WhatsappOperationalAlertService(),
        private readonly WhatsappOperationalNotificationPreviewService $previewService
            = new WhatsappOperationalNotificationPreviewService(),
    ) {
    }

    /**
     * @param array<string,mixed> $options  date, limit
     * @return array<string,mixed>
     */
    public function report(array $options = []): array
    {
        $date  = (string) ($options['date']  ?? date('Y-m-d'));
        $limit = max(1, min(500, (int) ($options['limit'] ?? 500)));

        // ── Pull full alert list ──────────────────────────────────────────────
        $alertResult = $this->alertService->alerts([
            'date'          => $date,
            'limit'         => $limit,
            'include_items' => true,
        ]);

        $alerts    = $alertResult['alerts'] ?? [];
        $evaluated = (int) ($alertResult['evaluated'] ?? 0);
        $total     = count($alerts);

        // ── Summary counts ────────────────────────────────────────────────────
        $sevCounts = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
        foreach ($alerts as $a) {
            $sev = (string) ($a['severity'] ?? 'low');
            $sevCounts[$sev] = ($sevCounts[$sev] ?? 0) + 1;
        }

        // ── By type ───────────────────────────────────────────────────────────
        $byType = [];
        foreach ($alerts as $a) {
            $t = (string) ($a['alert_type'] ?? 'unknown');
            $byType[$t] = ($byType[$t] ?? 0) + 1;
        }

        // ── By category ───────────────────────────────────────────────────────
        $byCategory = [];
        foreach ($alerts as $a) {
            $cat = (string) ($a['category'] ?? 'unknown');
            $byCategory[$cat] = ($byCategory[$cat] ?? 0) + 1;
        }

        // ── By agent ──────────────────────────────────────────────────────────
        $agentMap = [];
        foreach ($alerts as $a) {
            $uid  = $a['assigned_user_id'] ?? null;
            $key  = $uid === null ? 'unassigned' : (string) $uid;
            $name = $uid === null ? 'Sin asignar' : (string) ($a['assigned_user_name'] ?? "Agente #{$uid}");

            if (!isset($agentMap[$key])) {
                $agentMap[$key] = [
                    'assigned_user_id'   => $uid,
                    'assigned_user_name' => $name,
                    'alerts_total'       => 0,
                    'critical'           => 0,
                    'high'               => 0,
                    'medium'             => 0,
                    'low'                => 0,
                ];
            }
            $agentMap[$key]['alerts_total']++;
            $sev = (string) ($a['severity'] ?? 'low');
            $agentMap[$key][$sev] = ($agentMap[$key][$sev] ?? 0) + 1;
        }
        usort($agentMap, static fn ($a, $b) => $b['alerts_total'] <=> $a['alerts_total']);
        $byAgent = array_values($agentMap);

        // ── Top topics ────────────────────────────────────────────────────────
        $topicMap = [];
        foreach ($alerts as $a) {
            $topic = (string) ($a['topic'] ?? '');
            if ($topic === '') {
                continue;
            }
            if (!isset($topicMap[$topic])) {
                $topicMap[$topic] = [
                    'topic'       => $topic,
                    'topic_label' => (string) ($a['topic_label'] ?? $topic),
                    'count'       => 0,
                ];
            }
            $topicMap[$topic]['count']++;
        }
        usort($topicMap, static fn ($a, $b) => $b['count'] <=> $a['count']);
        $topTopics = array_values($topicMap);

        // ── Notification preview (dry-run, reuse existing service) ────────────
        $previewResult   = $this->previewService->preview(['date' => $date]);
        $notificationPrev = [
            'mode'         => 'dry_run',
            'channel'      => 'none',
            'would_notify' => (int) ($previewResult['would_notify'] ?? 0),
            'policy'       => 'hot_unassigned critical unassigned only',
        ];

        // ── Static recommendations ────────────────────────────────────────────
        $recommendations = [];
        if (($sevCounts['critical'] ?? 0) > 0) {
            $recommendations[] = 'Revisar críticas HOT sin asignar primero.';
        }
        $recommendations[] = 'No notificar rescue aging todavía.';
        $recommendations[] = 'Validar con coordinación antes de activar Fase 4C.';

        return [
            'ok'                   => true,
            'mode'                 => 'read_only',
            'read_only'            => true,
            'db_writes'            => 0,
            'date'                 => $date,
            'summary'              => array_merge(['evaluated' => $evaluated, 'alerts_total' => $total], $sevCounts),
            'by_type'              => $byType,
            'by_category'          => $byCategory,
            'by_agent'             => $byAgent,
            'top_topics'           => $topTopics,
            'notification_preview' => $notificationPrev,
            'recommendations'      => $recommendations,
        ];
    }
}
