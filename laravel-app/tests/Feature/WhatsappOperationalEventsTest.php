<?php

namespace Tests\Feature;

use App\Modules\Whatsapp\Services\WhatsappOperationalEventService;
use App\Modules\Whatsapp\Services\ConversationOpsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsappOperationalEventsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('whatsapp_operational_events');
        Schema::dropIfExists('whatsapp_handoff_events');
        Schema::dropIfExists('whatsapp_handoffs');
        Schema::dropIfExists('whatsapp_sigcenter_bookings');
        Schema::dropIfExists('whatsapp_conversations');

        Schema::create('whatsapp_conversations', function (Blueprint $table): void {
            $table->id();
            $table->string('wa_number', 32)->unique();
            $table->string('display_name', 191)->nullable();
            $table->string('patient_hc_number', 64)->nullable();
            $table->boolean('needs_human')->default(false);
            $table->text('handoff_notes')->nullable();
            $table->unsignedBigInteger('handoff_role_id')->nullable();
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('handoff_requested_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_handoffs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('wa_number', 32);
            $table->string('status', 24)->default('queued');
            $table->string('priority', 24)->default('normal');
            $table->string('topic', 64)->nullable();
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

        Schema::create('whatsapp_sigcenter_bookings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->string('wa_number', 32)->nullable();
            $table->string('patient_hc_number', 64)->nullable();
            $table->string('sigcenter_agenda_id', 64)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        $migration = include dirname(__DIR__, 2) . '/database/migrations/2026_06_24_000001_create_whatsapp_operational_events_table.php';
        $migration->up();
    }

    public function test_it_creates_operational_event_idempotently_with_payload(): void
    {
        DB::table('whatsapp_conversations')->insert([
            'id' => 101,
            'wa_number' => '593991001001',
            'patient_hc_number' => 'HC-101',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(WhatsappOperationalEventService::class);

        $first = $service->record([
            'conversation_id' => 101,
            'event_type' => 'booking_created',
            'event_at' => '2026-06-24 10:00:00',
            'producer' => 'test',
            'actor_type' => 'system',
            'wa_number' => '593991001001',
            'patient_hc_number' => 'HC-101',
            'payload' => ['source' => 'sigcenter'],
            'idempotency_key' => 'booking:101:abc',
        ]);

        $second = $service->record([
            'conversation_id' => 101,
            'event_type' => 'booking_created',
            'event_at' => '2026-06-24 10:00:00',
            'producer' => 'test',
            'actor_type' => 'system',
            'payload' => ['source' => 'ignored'],
            'idempotency_key' => 'booking:101:abc',
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('whatsapp_operational_events', 1);
        $this->assertDatabaseHas('whatsapp_operational_events', [
            'conversation_id' => 101,
            'event_type' => 'booking_created',
            'event_group' => 'agenda',
            'idempotency_key' => 'booking:101:abc',
        ]);
        $this->assertSame('sigcenter', DB::table('whatsapp_operational_events')->value('payload->source'));
    }

    public function test_backfill_maps_legacy_handoff_events_idempotently(): void
    {
        DB::table('whatsapp_conversations')->insert([
            'id' => 201,
            'wa_number' => '593992002002',
            'patient_hc_number' => 'HC-201',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('whatsapp_handoffs')->insert([
            'id' => 301,
            'conversation_id' => 201,
            'wa_number' => '593992002002',
            'status' => 'queued',
            'priority' => 'high',
            'topic' => 'captacion_agendar',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['queued', 'requeued', 'expired', 'auto_assigned', 'assigned', 'resolved', 'autoassign_rollback_level_a'] as $index => $type) {
            DB::table('whatsapp_handoff_events')->insert([
                'id' => 401 + $index,
                'handoff_id' => 301,
                'event_type' => $type,
                'actor_user_id' => $type === 'auto_assigned' ? null : 9,
                'notes' => 'legacy ' . $type,
                'created_at' => now()->subMinutes(10 - $index),
            ]);
        }

        Artisan::call('whatsapp:operational-events-backfill', [
            '--from' => now()->subDay()->toDateString(),
            '--to' => now()->addDay()->toDateString(),
        ]);

        $this->assertDatabaseHas('whatsapp_operational_events', ['event_type' => 'handoff_created']);
        $this->assertDatabaseHas('whatsapp_operational_events', ['event_type' => 'handoff_requeued']);
        $this->assertDatabaseHas('whatsapp_operational_events', ['event_type' => 'handoff_expired']);
        $this->assertDatabaseHas('whatsapp_operational_events', ['event_type' => 'auto_assigned']);
        $this->assertDatabaseHas('whatsapp_operational_events', ['event_type' => 'legacy_assigned']);
        $this->assertDatabaseHas('whatsapp_operational_events', ['event_type' => 'handoff_resolved']);
        $this->assertDatabaseHas('whatsapp_operational_events', ['event_type' => 'assignment_rollback']);
        $this->assertDatabaseHas('whatsapp_operational_events', [
            'event_type' => 'handoff_expired',
            'event_group' => 'handoff',
            'reason' => 'legacy_expired',
        ]);

        Artisan::call('whatsapp:operational-events-backfill', [
            '--from' => now()->subDay()->toDateString(),
            '--to' => now()->addDay()->toDateString(),
        ]);

        $this->assertDatabaseCount('whatsapp_operational_events', 7);
    }

    public function test_conversation_ops_writes_operational_events_when_requeueing_expired_handoff(): void
    {
        DB::table('whatsapp_conversations')->insert([
            'id' => 801,
            'wa_number' => '593998008008',
            'patient_hc_number' => 'HC-801',
            'needs_human' => 1,
            'assigned_user_id' => 12,
            'assigned_at' => now()->subDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('whatsapp_handoffs')->insert([
            'id' => 901,
            'conversation_id' => 801,
            'wa_number' => '593998008008',
            'status' => 'assigned',
            'priority' => 'high',
            'topic' => 'captacion_agendar',
            'assigned_agent_id' => 12,
            'assigned_at' => now()->subDay(),
            'assigned_until' => now()->subMinute(),
            'queued_at' => now()->subDay(),
            'last_activity_at' => now()->subMinute(),
            'notes' => 'TTL vencido',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = app(ConversationOpsService::class)->requeueExpired();

        $this->assertSame(1, $result['count']);
        $this->assertDatabaseHas('whatsapp_operational_events', [
            'conversation_id' => 801,
            'handoff_id' => 901,
            'event_type' => 'handoff_expired',
            'event_group' => 'handoff',
            'producer' => 'conversation_ops',
        ]);
        $this->assertDatabaseHas('whatsapp_operational_events', [
            'conversation_id' => 801,
            'handoff_id' => 901,
            'event_type' => 'handoff_requeued',
            'producer' => 'conversation_ops',
            'topic' => 'captacion_agendar',
        ]);
    }

    public function test_it_records_booking_created_connected_to_conversation(): void
    {
        DB::table('whatsapp_conversations')->insert([
            'id' => 501,
            'wa_number' => '593995005005',
            'patient_hc_number' => 'HC-501',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('whatsapp_sigcenter_bookings')->insert([
            'id' => 601,
            'conversation_id' => 501,
            'wa_number' => '593995005005',
            'patient_hc_number' => 'HC-501',
            'sigcenter_agenda_id' => 'AG-601',
            'created_at' => '2026-06-24 11:00:00',
            'updated_at' => '2026-06-24 11:00:00',
        ]);

        $event = app(WhatsappOperationalEventService::class)->recordBookingCreated(601);

        $this->assertNotNull($event);
        $this->assertDatabaseHas('whatsapp_operational_events', [
            'conversation_id' => 501,
            'booking_id' => 601,
            'event_type' => 'booking_created',
            'event_group' => 'agenda',
            'wa_number' => '593995005005',
            'patient_hc_number' => 'HC-501',
        ]);
    }
}
