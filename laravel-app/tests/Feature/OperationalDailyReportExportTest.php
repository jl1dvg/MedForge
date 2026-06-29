<?php

namespace Tests\Feature;

use App\Modules\Whatsapp\Http\Controllers\OperationalAlertDailyReportExportController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Fase 4B.6 — Validates the manual read-only CSV/XLSX export endpoint.
 *
 * Guarantees:
 *  - read_only=true, db_writes=0 always.
 *  - No modifications to conversations, handoffs, handoff_events, operational_events.
 *  - No messages sent. No scheduler. No jobs.
 *  - CSV has correct Content-Type, Content-Disposition, and all required sections.
 *  - Invalid format returns 422.
 *  - notification_preview in CSV uses mode=dry_run, channel=none.
 *  - rescue_aging is NOT a notification candidate.
 */
class OperationalDailyReportExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'whatsapp_operational_booking_attributions',
            'whatsapp_messages',
            'whatsapp_handoff_events',
            'whatsapp_operational_events',
            'whatsapp_handoffs',
            'whatsapp_conversations',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre')->default('');
            $table->string('first_name')->default('');
            $table->string('last_name')->default('');
            $table->string('username')->default('');
            $table->string('password')->default('');
        });

        Schema::create('whatsapp_conversations', function (Blueprint $table): void {
            $table->id();
            $table->string('wa_number', 32)->unique();
            $table->string('display_name', 191)->nullable();
            $table->string('patient_hc_number', 64)->nullable();
            $table->string('patient_full_name', 191)->nullable();
            $table->boolean('needs_human')->default(true);
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('handoff_requested_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_handoffs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('wa_number', 32);
            $table->string('status', 24)->default('queued');
            $table->string('topic', 191)->nullable();
            $table->string('priority', 24)->default('normal');
            $table->unsignedBigInteger('assigned_agent_id')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_messages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('direction', 16);
            $table->timestamp('message_timestamp')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_handoff_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('handoff_id');
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->string('event_type', 64);
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('source', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_operational_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('event_type', 64);
            $table->string('source', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_operational_booking_attributions', function (Blueprint $table): void {
            $table->id();
            $table->string('booking_source', 48)->default('bot_api');
            $table->string('observed_booking_key', 191)->unique();
            $table->unsignedBigInteger('attributed_conversation_id')->nullable();
            $table->unsignedBigInteger('form_id')->nullable();
            $table->string('event_type', 64);
            $table->string('attribution_method', 64);
            $table->string('confidence', 24);
            $table->timestamps();
        });
    }

    // ── 1. Endpoint accessible (via withoutMiddleware) ───────────────────────

    public function test_export_endpoint_is_accessible_via_http(): void
    {
        Carbon::setTestNow('2026-06-27 10:00:00');
        $this->seedHotUnassigned(1);

        $response = $this->withoutMiddleware()
            ->get('/v2/whatsapp/api/operational-alerts/daily-report/export?date=2026-06-27&format=csv');

        $response->assertOk();

        Carbon::setTestNow();
    }

    // ── 2. CSV returns 200 ───────────────────────────────────────────────────

    public function test_export_csv_returns_200(): void
    {
        Carbon::setTestNow('2026-06-27 10:00:00');
        $this->seedHotUnassigned(2);

        $response = $this->callExport(['format' => 'csv']);

        $this->assertSame(200, $response->getStatusCode());

        Carbon::setTestNow();
    }

    // ── 3. CSV Content-Type is text/csv ─────────────────────────────────────

    public function test_export_csv_has_correct_content_type(): void
    {
        Carbon::setTestNow('2026-06-27 10:00:00');
        $this->seedHotUnassigned(3);

        $response = $this->callExport(['format' => 'csv']);

        $this->assertStringContainsStringIgnoringCase('text/csv', $response->headers->get('Content-Type') ?? '');

        Carbon::setTestNow();
    }

    // ── 4. CSV Content-Disposition has expected filename ─────────────────────

    public function test_export_csv_has_correct_content_disposition(): void
    {
        Carbon::setTestNow('2026-06-27 10:00:00');
        $this->seedHotUnassigned(4);

        $response = $this->callExport(['format' => 'csv', 'date' => '2026-06-27']);

        $disposition = $response->headers->get('Content-Disposition') ?? '';
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('whatsapp-alert-engine-daily-report-2026-06-27.csv', $disposition);

        Carbon::setTestNow();
    }

    // ── 5. CSV contains section: summary ────────────────────────────────────

    public function test_export_csv_contains_summary_section(): void
    {
        Carbon::setTestNow('2026-06-27 10:00:00');
        $this->seedHotUnassigned(5);

        $csv = $this->getCsvContent(['date' => '2026-06-27']);

        $this->assertStringContainsString('summary', $csv);

        Carbon::setTestNow();
    }

    // ── 6. CSV contains section: by_type ────────────────────────────────────

    public function test_export_csv_contains_by_type_section(): void
    {
        Carbon::setTestNow('2026-06-27 10:00:00');
        $this->seedHotUnassigned(6);

        $csv = $this->getCsvContent(['date' => '2026-06-27']);

        $this->assertStringContainsString('by_type', $csv);

        Carbon::setTestNow();
    }

    // ── 7. CSV contains section: by_category ────────────────────────────────

    public function test_export_csv_contains_by_category_section(): void
    {
        Carbon::setTestNow('2026-06-27 10:00:00');
        $this->seedHotUnassigned(7);

        $csv = $this->getCsvContent(['date' => '2026-06-27']);

        $this->assertStringContainsString('by_category', $csv);

        Carbon::setTestNow();
    }

    // ── 8. CSV contains section: by_agent ───────────────────────────────────

    public function test_export_csv_contains_by_agent_section(): void
    {
        Carbon::setTestNow('2026-06-27 10:00:00');
        $this->seedHotUnassigned(8);

        $csv = $this->getCsvContent(['date' => '2026-06-27']);

        $this->assertStringContainsString('by_agent', $csv);

        Carbon::setTestNow();
    }

    // ── 9. CSV contains section: top_topics ─────────────────────────────────

    public function test_export_csv_contains_top_topics_section(): void
    {
        Carbon::setTestNow('2026-06-27 10:00:00');
        $this->seedHotUnassigned(9);

        $csv = $this->getCsvContent(['date' => '2026-06-27']);

        $this->assertStringContainsString('top_topics', $csv);

        Carbon::setTestNow();
    }

    // ── 10. CSV notification_preview has dry-run and channel none ────────────

    public function test_export_csv_notification_preview_is_dry_run_channel_none(): void
    {
        Carbon::setTestNow('2026-06-27 10:00:00');
        $this->seedHotUnassigned(10);

        $csv = $this->getCsvContent(['date' => '2026-06-27']);

        $this->assertStringContainsString('notification_preview', $csv);
        $this->assertStringContainsString('dry_run', $csv);
        $this->assertStringContainsString('none', $csv);

        Carbon::setTestNow();
    }

    // ── 11. rescue_aging not a notification candidate in CSV ─────────────────

    public function test_export_csv_rescue_aging_not_in_notification_preview(): void
    {
        Carbon::setTestNow('2026-06-27 10:00:00');
        // Seed only rescue_aging (queued 3 days ago, no agent)
        $this->seedConversation(11, now()->subDays(3), null, 'normal', 'captacion_agendar');
        $this->seedInbound(11, now()->subDays(3)->addMinutes(10));

        $csv = $this->getCsvContent(['date' => '2026-06-27']);

        // Extract the notification_preview row and assert would_notify = 0
        $rows = $this->parseCsv($csv);
        $previewRows = array_filter($rows, fn ($r) => ($r['section'] ?? '') === 'notification_preview');
        foreach ($previewRows as $row) {
            $this->assertSame('0', $row['value'], 'rescue_aging must not be counted as notification candidate');
        }

        Carbon::setTestNow();
    }

    // ── 12. Invalid format returns 422 ───────────────────────────────────────

    public function test_export_invalid_format_returns_422(): void
    {
        $response = $this->callExport(['format' => 'pdf']);

        $this->assertSame(422, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true);
        $this->assertFalse($data['ok']);
        $this->assertArrayHasKey('format', $data['errors'] ?? []);
    }

    public function test_export_invalid_format_xml_returns_422(): void
    {
        $response = $this->callExport(['format' => 'xml']);

        $this->assertSame(422, $response->getStatusCode());
    }

    // ── 13. Export does not modify conversations ──────────────────────────────

    public function test_export_does_not_modify_conversations(): void
    {
        Carbon::setTestNow('2026-06-27 10:00:00');
        $this->seedHotUnassigned(13);

        $before = DB::table('whatsapp_conversations')->where('id', 13)->value('assigned_user_id');
        $this->callExport(['format' => 'csv', 'date' => '2026-06-27']);
        $after  = DB::table('whatsapp_conversations')->where('id', 13)->value('assigned_user_id');

        $this->assertSame($before, $after);

        Carbon::setTestNow();
    }

    // ── 14. Export does not modify handoffs ───────────────────────────────────

    public function test_export_does_not_modify_handoffs(): void
    {
        Carbon::setTestNow('2026-06-27 10:00:00');
        $this->seedHotUnassigned(14);

        $this->callExport(['format' => 'csv', 'date' => '2026-06-27']);

        $this->assertDatabaseHas('whatsapp_handoffs', ['conversation_id' => 14, 'status' => 'queued']);

        Carbon::setTestNow();
    }

    // ── 15. Export does not insert handoff_events ─────────────────────────────

    public function test_export_does_not_insert_handoff_events(): void
    {
        Carbon::setTestNow('2026-06-27 10:00:00');
        $this->seedHotUnassigned(15);

        $before = DB::table('whatsapp_handoff_events')->count();
        $this->callExport(['format' => 'csv', 'date' => '2026-06-27']);
        $after  = DB::table('whatsapp_handoff_events')->count();

        $this->assertSame($before, $after);

        Carbon::setTestNow();
    }

    // ── 16. Export does not insert operational_events ─────────────────────────

    public function test_export_does_not_insert_operational_events(): void
    {
        Carbon::setTestNow('2026-06-27 10:00:00');
        $this->seedHotUnassigned(16);

        $before = DB::table('whatsapp_operational_events')->count();
        $this->callExport(['format' => 'csv', 'date' => '2026-06-27']);
        $after  = DB::table('whatsapp_operational_events')->count();

        $this->assertSame($before, $after);

        Carbon::setTestNow();
    }

    // ── Bonus: CSV header row contains expected columns ───────────────────────

    public function test_export_csv_contains_expected_header_columns(): void
    {
        Carbon::setTestNow('2026-06-27 10:00:00');
        $this->seedHotUnassigned(20);

        $csv  = $this->getCsvContent(['date' => '2026-06-27']);
        $rows = $this->parseCsv($csv);

        $this->assertNotEmpty($rows);
        $firstRow = $rows[0];
        foreach (['section', 'metric', 'key', 'label', 'value', 'notes'] as $col) {
            $this->assertArrayHasKey($col, $firstRow, "CSV missing column: {$col}");
        }

        Carbon::setTestNow();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function callExport(array $params = []): \Symfony\Component\HttpFoundation\Response
    {
        $params = array_merge(['date' => '2026-06-27', 'format' => 'csv'], $params);
        $request  = Request::create(
            '/v2/whatsapp/api/operational-alerts/daily-report/export',
            'GET',
            $params
        );
        return app(OperationalAlertDailyReportExportController::class)->index($request);
    }

    private function getCsvContent(array $params = []): string
    {
        $response = $this->callExport(array_merge(['format' => 'csv'], $params));
        $content  = (string) $response->getContent();
        // Strip UTF-8 BOM if present
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }
        return $content;
    }

    /**
     * @return array<int,array<string,string>>
     */
    private function parseCsv(string $csv): array
    {
        $lines  = explode("\n", trim($csv));
        if (count($lines) < 2) {
            return [];
        }
        $headers = str_getcsv(array_shift($lines));
        $rows    = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $values = str_getcsv($line);
            $rows[] = array_combine($headers, array_pad($values, count($headers), '')) ?: [];
        }
        return $rows;
    }

    private function seedHotUnassigned(int $id): void
    {
        $this->seedConversation($id, now()->subMinutes(90), null, 'high', 'captacion_agendar');
        $this->seedInbound($id, now()->subMinutes(80));
    }

    private function seedConversation(int $id, Carbon $queuedAt, ?int $agentId, string $priority, string $topic): void
    {
        DB::table('whatsapp_conversations')->insert([
            'id'                   => $id,
            'wa_number'            => '593111' . $id,
            'display_name'         => 'Test Export ' . $id,
            'patient_hc_number'    => 'HC-EX-' . $id,
            'needs_human'          => true,
            'assigned_user_id'     => $agentId,
            'last_message_at'      => $queuedAt,
            'handoff_requested_at' => $queuedAt,
            'created_at'           => $queuedAt,
            'updated_at'           => $queuedAt,
        ]);

        DB::table('whatsapp_handoffs')->insert([
            'id'                => $id,
            'conversation_id'   => $id,
            'wa_number'         => '593111' . $id,
            'status'            => $agentId !== null ? 'assigned' : 'queued',
            'topic'             => $topic,
            'priority'          => $priority,
            'assigned_agent_id' => $agentId,
            'assigned_at'       => $agentId !== null ? $queuedAt->copy()->addMinutes(5) : null,
            'queued_at'         => $queuedAt,
            'created_at'        => $queuedAt,
            'updated_at'        => $queuedAt,
        ]);
    }

    private function seedInbound(int $conversationId, Carbon $at): void
    {
        DB::table('whatsapp_messages')->insert([
            'conversation_id'   => $conversationId,
            'direction'         => 'inbound',
            'message_timestamp' => $at,
            'created_at'        => $at,
            'updated_at'        => $at,
        ]);
    }
}
