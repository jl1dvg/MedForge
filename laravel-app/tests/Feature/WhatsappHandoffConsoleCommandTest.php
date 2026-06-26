<?php

namespace Tests\Feature;

use App\Models\WhatsappConversation;
use App\Modules\Whatsapp\Services\WhatsappHandoffAutoAssignService;
use App\Modules\Whatsapp\Services\WhatsappRealtimeService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsappHandoffConsoleCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('whatsapp_handoff_events');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_handoffs');
        Schema::dropIfExists('whatsapp_conversations');
        Schema::dropIfExists('whatsapp_agent_presence');
        Schema::dropIfExists('whatsapp_conversation_attributions');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('permissions')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username');
            $table->string('first_name')->default('');
            $table->string('middle_name')->default('');
            $table->string('last_name')->default('');
            $table->string('second_last_name')->default('');
            $table->timestamp('birth_date')->nullable();
            $table->string('password')->default('');
            $table->string('email')->default('');
            $table->string('nombre')->default('');
            $table->string('cedula')->default('');
            $table->string('registro')->default('');
            $table->string('sede')->default('');
            $table->string('especialidad')->default('');
            $table->text('permisos')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->boolean('whatsapp_notify')->default(false);
        });

        Schema::create('whatsapp_conversations', function (Blueprint $table): void {
            $table->id();
            $table->string('wa_number', 32)->unique();
            $table->string('display_name', 191)->nullable();
            $table->string('patient_hc_number', 64)->nullable();
            $table->string('patient_full_name', 191)->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->string('last_message_direction', 32)->nullable();
            $table->string('last_message_type', 64)->nullable();
            $table->string('last_message_preview', 512)->nullable();
            $table->boolean('needs_human')->default(false);
            $table->text('handoff_notes')->nullable();
            $table->unsignedBigInteger('handoff_role_id')->nullable();
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('handoff_requested_at')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamps();
        });

        Schema::create('whatsapp_handoffs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('wa_number', 32);
            $table->string('status', 24)->default('queued');
            $table->string('priority', 24)->default('normal');
            $table->string('topic', 191)->nullable();
            $table->unsignedBigInteger('handoff_role_id')->nullable();
            $table->unsignedBigInteger('assigned_agent_id')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('assigned_until')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_handoff_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('handoff_id');
            $table->string('event_type', 64);
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('whatsapp_messages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('direction', 16)->default('inbound');
            $table->timestamp('message_timestamp')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_agent_presence', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->primary();
            $table->string('status', 24)->default('available');
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('whatsapp_conversation_attributions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->unique();
            $table->string('source_category', 64)->nullable();
            $table->string('initial_intent', 64)->nullable();
            $table->string('conversation_type', 64)->nullable();
            $table->string('patient_segment', 64)->nullable();
            $table->timestamps();
        });

        DB::table('users')->insert([
            [
                'id' => 10,
                'username' => 'agent.one',
                'password' => bcrypt('secret'),
                'email' => 'agent1@example.com',
                'nombre' => 'Agente Uno',
                'cedula' => '1',
                'registro' => 'R1',
                'sede' => 'Matriz',
                'especialidad' => 'NA',
                'permisos' => json_encode(['whatsapp.chat.view', 'whatsapp.chat.send']),
            ],
            [
                'id' => 11,
                'username' => 'agent.two',
                'password' => bcrypt('secret'),
                'email' => 'agent2@example.com',
                'nombre' => 'Agente Dos',
                'cedula' => '2',
                'registro' => 'R2',
                'sede' => 'Matriz',
                'especialidad' => 'NA',
                'permisos' => json_encode(['whatsapp.chat.view', 'whatsapp.chat.send']),
            ],
        ]);

        DB::table('whatsapp_agent_presence')->insert([
            ['user_id' => 10, 'status' => 'available', 'updated_at' => now()],
            ['user_id' => 11, 'status' => 'away', 'updated_at' => now()],
        ]);
    }

    public function test_it_previews_expired_handoffs_from_console(): void
    {
        \DB::table('whatsapp_conversations')->insert([
            'id' => 801,
            'wa_number' => '593999111801',
            'display_name' => 'Paciente Preview',
            'needs_human' => 1,
            'assigned_user_id' => 10,
            'assigned_at' => now()->subDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_handoffs')->insert([
            'id' => 1801,
            'conversation_id' => 801,
            'wa_number' => '593999111801',
            'status' => 'assigned',
            'priority' => 'normal',
            'assigned_agent_id' => 10,
            'assigned_at' => now()->subDay(),
            'assigned_until' => now()->subMinute(),
            'queued_at' => now()->subDay(),
            'last_activity_at' => now()->subMinute(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Artisan::call('whatsapp:handoff-requeue-expired', ['--dry-run' => true]);

        $this->assertStringContainsString('1801', Artisan::output());
        $this->assertDatabaseHas('whatsapp_handoffs', [
            'id' => 1801,
            'status' => 'assigned',
        ]);
    }

    public function test_it_requeues_expired_handoffs_from_console(): void
    {
        \DB::table('whatsapp_conversations')->insert([
            'id' => 802,
            'wa_number' => '593999111802',
            'display_name' => 'Paciente Execute',
            'needs_human' => 1,
            'assigned_user_id' => 10,
            'assigned_at' => now()->subDay(),
            'handoff_role_id' => 9,
            'handoff_notes' => 'Seguimiento',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_handoffs')->insert([
            'id' => 1802,
            'conversation_id' => 802,
            'wa_number' => '593999111802',
            'status' => 'assigned',
            'priority' => 'normal',
            'handoff_role_id' => 9,
            'assigned_agent_id' => 10,
            'assigned_at' => now()->subDay(),
            'assigned_until' => now()->subMinute(),
            'queued_at' => now()->subDay(),
            'last_activity_at' => now()->subMinute(),
            'notes' => 'Seguimiento',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Artisan::call('whatsapp:handoff-requeue-expired');

        $this->assertStringContainsString('1802', Artisan::output());
        $this->assertDatabaseHas('whatsapp_handoffs', [
            'id' => 1802,
            'status' => 'queued',
            'assigned_agent_id' => null,
        ]);
        $this->assertDatabaseHas('whatsapp_conversations', [
            'id' => 802,
            'assigned_user_id' => null,
            'handoff_role_id' => 9,
            'needs_human' => 1,
        ]);
    }

    public function test_it_previews_hot_handoff_auto_assignment_without_mutating_records(): void
    {
        $this->seedHotQueuedHandoff(901, 1901, 'captacion_agendar');

        Artisan::call('whatsapp:handoff-auto-assign', ['--dry-run' => true, '--limit' => 10]);

        $output = Artisan::output();

        $this->assertStringContainsString('Eligible', $output);
        $this->assertStringContainsString('captacion_agendar', $output);
        $this->assertDatabaseHas('whatsapp_conversations', [
            'id' => 901,
            'assigned_user_id' => null,
        ]);
        $this->assertDatabaseHas('whatsapp_handoffs', [
            'id' => 1901,
            'status' => 'queued',
            'assigned_agent_id' => null,
        ]);
    }

    public function test_it_auto_assigns_hot_handoff_to_available_agent_and_emits_realtime_payload(): void
    {
        $this->seedHotQueuedHandoff(902, 1902, 'agenda_sin_disponibilidad');
        $realtime = new FakeWhatsappRealtimeService();
        $service = new WhatsappHandoffAutoAssignService(realtime: $realtime);

        $result = $service->run(['dry_run' => false, 'limit' => 10]);

        $this->assertSame(1, $result['assigned']);
        $this->assertSame(0, $result['supervisor']);
        $this->assertDatabaseHas('whatsapp_conversations', [
            'id' => 902,
            'assigned_user_id' => 10,
            'needs_human' => 1,
        ]);
        $this->assertDatabaseHas('whatsapp_handoffs', [
            'id' => 1902,
            'status' => 'assigned',
            'assigned_agent_id' => 10,
        ]);
        $this->assertDatabaseHas('whatsapp_handoff_events', [
            'handoff_id' => 1902,
            'event_type' => 'auto_assigned',
            'actor_user_id' => null,
        ]);

        $this->assertCount(1, $realtime->events);
        $this->assertSame('handoff.auto_assigned', $realtime->events[0]['event']);
        $this->assertSame(902, $realtime->events[0]['conversation_id']);
        $this->assertSame('agenda_sin_disponibilidad', $realtime->events[0]['topic']);
        $this->assertSame(10, $realtime->events[0]['assigned_to']['id']);
    }

    private function seedHotQueuedHandoff(int $conversationId, int $handoffId, string $topic): void
    {
        DB::table('whatsapp_conversations')->insert([
            'id' => $conversationId,
            'wa_number' => '593999' . $conversationId,
            'display_name' => 'Paciente Hot',
            'patient_hc_number' => 'HC' . $conversationId,
            'patient_full_name' => 'Paciente Hot',
            'needs_human' => 1,
            'assigned_user_id' => null,
            'handoff_requested_at' => now()->subMinutes(15),
            'last_message_at' => now()->subMinutes(20),
            'last_message_direction' => 'inbound',
            'last_message_type' => 'text',
            'last_message_preview' => 'Quiero agendar una cita',
            'unread_count' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('whatsapp_handoffs')->insert([
            'id' => $handoffId,
            'conversation_id' => $conversationId,
            'wa_number' => '593999' . $conversationId,
            'status' => 'queued',
            'priority' => 'high',
            'topic' => $topic,
            'assigned_agent_id' => null,
            'queued_at' => now()->subMinutes(15),
            'last_activity_at' => now()->subMinutes(15),
            'notes' => 'Paciente quiere agendar',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('whatsapp_messages')->insert([
            'conversation_id'   => $conversationId,
            'direction'         => 'inbound',
            'message_timestamp' => now()->subMinutes(20),
            'created_at'        => now()->subMinutes(20),
            'updated_at'        => now()->subMinutes(20),
        ]);

        DB::table('whatsapp_conversation_attributions')->insert([
            'conversation_id' => $conversationId,
            'source_category' => 'ad',
            'initial_intent' => 'booking',
            'conversation_type' => 'appointment',
            'patient_segment' => 'retorno',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_rescue_requeued_handoff_is_not_autoassigned(): void
    {
        // Conversation queued 48h ago: ageMinutes = 2880 > 1440 → bucket = rescue
        DB::table('whatsapp_conversations')->insert([
            'id'                   => 910,
            'wa_number'            => '593999910',
            'display_name'         => 'Paciente Rescue',
            'needs_human'          => 1,
            'assigned_user_id'     => null,
            'handoff_requested_at' => now()->subHours(48),
            'last_message_at'      => now()->subHours(48),
            'last_message_direction' => 'inbound',
            'created_at'           => now()->subHours(48),
            'updated_at'           => now(),
        ]);
        DB::table('whatsapp_handoffs')->insert([
            'id'               => 1910,
            'conversation_id'  => 910,
            'wa_number'        => '593999910',
            'status'           => 'queued',
            'priority'         => 'normal',
            'topic'            => 'captacion_agendar',
            'assigned_agent_id' => null,
            'queued_at'        => now()->subHours(48),
            'created_at'       => now()->subHours(48),
            'updated_at'       => now(),
        ]);
        DB::table('whatsapp_messages')->insert([
            'conversation_id'   => 910,
            'direction'         => 'inbound',
            'message_timestamp' => now()->subHours(48),
            'created_at'        => now()->subHours(48),
            'updated_at'        => now()->subHours(48),
        ]);

        $realtime = new FakeWhatsappRealtimeService();
        $service  = new WhatsappHandoffAutoAssignService(realtime: $realtime);
        $result   = $service->run(['dry_run' => false, 'limit' => 50, 'max_age_hours' => 72]);

        $this->assertSame(0, $result['assigned'], 'RESCUE must not be assigned');
        $this->assertSame(0, $result['would_assign']);
        $this->assertCount(0, $realtime->events, 'No realtime event should fire');
        $this->assertDatabaseHas('whatsapp_conversations', ['id' => 910, 'assigned_user_id' => null]);
        $this->assertDatabaseHas('whatsapp_handoffs', ['id' => 1910, 'status' => 'queued', 'assigned_agent_id' => null]);
        $this->assertDatabaseMissing('whatsapp_handoff_events', ['handoff_id' => 1910, 'event_type' => 'auto_assigned']);

        $skippedRow = collect($result['rows'])->firstWhere('handoff_id', 1910);
        $this->assertNotNull($skippedRow, 'Row must appear in result with skip info');
        $this->assertSame('skipped', $skippedRow['status']);
        $this->assertSame('bucket_not_hot_open', $skippedRow['skip_reason']);
        $this->assertSame('rescue', $skippedRow['bucket']);
    }

    public function test_backlog_requeued_handoff_is_not_autoassigned(): void
    {
        // Conversation queued 10 days ago: ageMinutes ≈ 14400 > 7*1440 → bucket = backlog
        DB::table('whatsapp_conversations')->insert([
            'id'                   => 911,
            'wa_number'            => '593999911',
            'display_name'         => 'Paciente Backlog',
            'needs_human'          => 1,
            'assigned_user_id'     => null,
            'handoff_requested_at' => now()->subDays(10),
            'last_message_at'      => now()->subDays(10),
            'last_message_direction' => 'inbound',
            'created_at'           => now()->subDays(10),
            'updated_at'           => now(),
        ]);
        DB::table('whatsapp_handoffs')->insert([
            'id'               => 1911,
            'conversation_id'  => 911,
            'wa_number'        => '593999911',
            'status'           => 'queued',
            'priority'         => 'normal',
            'topic'            => 'operacion_reagenda',
            'assigned_agent_id' => null,
            'queued_at'        => now()->subDays(10),
            'created_at'       => now()->subDays(10),
            'updated_at'       => now(),
        ]);
        DB::table('whatsapp_messages')->insert([
            'conversation_id'   => 911,
            'direction'         => 'inbound',
            'message_timestamp' => now()->subDays(10),
            'created_at'        => now()->subDays(10),
            'updated_at'        => now()->subDays(10),
        ]);

        $realtime = new FakeWhatsappRealtimeService();
        $service  = new WhatsappHandoffAutoAssignService(realtime: $realtime);
        // Use max_age_hours=720 to ensure a 10-day-old record is included by candidateRows
        $result   = $service->run(['dry_run' => false, 'limit' => 50, 'max_age_hours' => 720]);

        $this->assertSame(0, $result['assigned'], 'BACKLOG must not be assigned');
        $this->assertSame(0, $result['would_assign']);
        $this->assertCount(0, $realtime->events);
        $this->assertDatabaseHas('whatsapp_conversations', ['id' => 911, 'assigned_user_id' => null]);
        $this->assertDatabaseHas('whatsapp_handoffs', ['id' => 1911, 'status' => 'queued', 'assigned_agent_id' => null]);
        $this->assertDatabaseMissing('whatsapp_handoff_events', ['handoff_id' => 1911, 'event_type' => 'auto_assigned']);

        $skippedRow = collect($result['rows'])->firstWhere('handoff_id', 1911);
        $this->assertNotNull($skippedRow);
        $this->assertSame('skipped', $skippedRow['status']);
        $this->assertSame('bucket_not_hot_open', $skippedRow['skip_reason']);
        $this->assertSame('backlog', $skippedRow['bucket']);
    }

    public function test_hot_open_with_recent_inbound_is_autoassigned(): void
    {
        // Conversation queued 15 min ago, inbound message 10 min ago → bucket = hot_open
        $this->seedHotQueuedHandoff(912, 1912, 'captacion_agendar');

        $realtime = new FakeWhatsappRealtimeService();
        $service  = new WhatsappHandoffAutoAssignService(realtime: $realtime);
        $result   = $service->run(['dry_run' => false, 'limit' => 10]);

        $this->assertSame(1, $result['assigned'], 'HOT_OPEN must be assigned');
        $this->assertSame(0, $result['skipped']);
        $this->assertDatabaseHas('whatsapp_conversations', ['id' => 912, 'assigned_user_id' => 10]);
        $this->assertDatabaseHas('whatsapp_handoffs', ['id' => 1912, 'status' => 'assigned', 'assigned_agent_id' => 10]);
        $this->assertDatabaseHas('whatsapp_handoff_events', ['handoff_id' => 1912, 'event_type' => 'auto_assigned']);
        $this->assertCount(1, $realtime->events);
        $this->assertSame('handoff.auto_assigned', $realtime->events[0]['event']);
    }

    // ── Dry-run write isolation ───────────────────────────────────────────────

    public function test_dry_run_does_not_write_conversations_or_handoffs(): void
    {
        $this->seedHotQueuedHandoff(920, 1920, 'captacion_agendar');

        $realtime = new FakeWhatsappRealtimeService();
        $service  = new WhatsappHandoffAutoAssignService(realtime: $realtime);
        $result   = $service->run(['dry_run' => true, 'limit' => 10]);

        // Exactly 1 eligible, 0 db writes
        $this->assertSame(1, $result['would_assign'], 'dry-run must report would_assign');
        $this->assertSame(0, $result['assigned'], 'dry-run must not assign');
        $this->assertSame(0, $result['db_writes'], 'dry-run must report 0 db_writes');
        $this->assertTrue($result['read_only'], 'read_only must be true in dry-run');
        $this->assertSame('dry_run', $result['mode']);

        // No DB mutations
        $this->assertDatabaseHas('whatsapp_conversations', ['id' => 920, 'assigned_user_id' => null]);
        $this->assertDatabaseHas('whatsapp_handoffs', ['id' => 1920, 'status' => 'queued', 'assigned_agent_id' => null]);
        $this->assertDatabaseMissing('whatsapp_handoff_events', ['handoff_id' => 1920, 'event_type' => 'auto_assigned']);

        // No realtime broadcast
        $this->assertCount(0, $realtime->events, 'dry-run must not emit broadcasts');
    }

    public function test_dry_run_does_not_write_handoff_events(): void
    {
        $this->seedHotQueuedHandoff(921, 1921, 'agenda_sin_disponibilidad');

        $realtime = new FakeWhatsappRealtimeService();
        $service  = new WhatsappHandoffAutoAssignService(realtime: $realtime);
        $service->run(['dry_run' => true, 'limit' => 10]);

        $this->assertDatabaseMissing('whatsapp_handoff_events', ['handoff_id' => 1921]);
    }

    public function test_dry_run_reports_hot_open_as_would_assign(): void
    {
        $this->seedHotQueuedHandoff(922, 1922, 'captacion_agendar');

        $service = new WhatsappHandoffAutoAssignService();
        $result  = $service->run(['dry_run' => true, 'limit' => 10]);

        $row = collect($result['rows'])->firstWhere('handoff_id', 1922);
        $this->assertNotNull($row, 'Row must appear in result');
        $this->assertSame('would_assign', $row['status']);
        $this->assertSame('hot_open', $row['bucket']);
        $this->assertSame('captacion', $row['category']);
        $this->assertNotEmpty($row['topic_label']);
        $this->assertNotNull($row['assigned_to']);
    }

    public function test_dry_run_reports_rescue_as_skipped(): void
    {
        DB::table('whatsapp_conversations')->insert([
            'id' => 923, 'wa_number' => '593999923', 'display_name' => 'Rescue',
            'needs_human' => 1, 'assigned_user_id' => null,
            'last_message_at' => now()->subHours(48), 'last_message_direction' => 'inbound',
            'created_at' => now()->subHours(48), 'updated_at' => now(),
        ]);
        DB::table('whatsapp_handoffs')->insert([
            'id' => 1923, 'conversation_id' => 923, 'wa_number' => '593999923',
            'status' => 'queued', 'priority' => 'normal', 'topic' => 'captacion_agendar',
            'assigned_agent_id' => null, 'queued_at' => now()->subHours(48),
            'created_at' => now()->subHours(48), 'updated_at' => now(),
        ]);
        DB::table('whatsapp_messages')->insert([
            'conversation_id' => 923, 'direction' => 'inbound',
            'message_timestamp' => now()->subHours(48),
            'created_at' => now()->subHours(48), 'updated_at' => now()->subHours(48),
        ]);

        $service = new WhatsappHandoffAutoAssignService();
        $result  = $service->run(['dry_run' => true, 'limit' => 50, 'max_age_hours' => 72]);

        $this->assertSame(0, $result['would_assign']);
        $this->assertSame(0, $result['db_writes']);
        $row = collect($result['rows'])->firstWhere('handoff_id', 1923);
        $this->assertNotNull($row);
        $this->assertSame('skipped', $row['status']);
        $this->assertSame('rescue', $row['bucket']);
        $this->assertArrayHasKey('bucket_not_hot_open', $result['skipped_reasons']);
    }

    public function test_dry_run_json_command_returns_read_only_true_and_zero_db_writes(): void
    {
        $this->seedHotQueuedHandoff(924, 1924, 'captacion_agendar');

        Artisan::call('whatsapp:handoff-auto-assign', ['--dry-run' => true, '--json' => true, '--limit' => 10]);
        $output = Artisan::output();

        $json = json_decode($output, true);
        $this->assertIsArray($json, 'Output must be valid JSON');
        $this->assertTrue($json['read_only'] ?? false, 'read_only must be true');
        $this->assertSame(0, $json['db_writes'] ?? -1, 'db_writes must be 0');
        $this->assertSame('dry_run', $json['mode'] ?? '');
        $this->assertArrayHasKey('would_assign', $json);
        $this->assertArrayHasKey('skipped_reasons', $json);

        // Confirm DB untouched
        $this->assertDatabaseHas('whatsapp_conversations', ['id' => 924, 'assigned_user_id' => null]);
        $this->assertDatabaseHas('whatsapp_handoffs', ['id' => 1924, 'status' => 'queued', 'assigned_agent_id' => null]);
    }
}

class FakeWhatsappRealtimeService extends WhatsappRealtimeService
{
    /** @var array<int,array<string,mixed>> */
    public array $events = [];

    /**
     * @param array<string,mixed> $payload
     */
    public function broadcastHandoffOperationalEvent(array $payload): void
    {
        $this->events[] = $payload;
    }
}
