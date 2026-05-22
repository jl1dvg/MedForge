<?php

namespace Tests\Feature;

use App\Modules\Whatsapp\Services\WhatsappAppointmentReminderService;
use App\Modules\Shared\Support\SettingsOptionResolver;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsappAppointmentReminderServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        SettingsOptionResolver::flush();

        Schema::dropIfExists('whatsapp_handoff_events');
        Schema::dropIfExists('whatsapp_handoffs');
        Schema::dropIfExists('whatsapp_appointment_reminders');
        Schema::dropIfExists('whatsapp_template_revisions');
        Schema::dropIfExists('whatsapp_message_templates');
        Schema::dropIfExists('whatsapp_messages');
        Schema::dropIfExists('whatsapp_conversations');
        Schema::dropIfExists('procedimiento_proyectado');
        Schema::dropIfExists('patient_data');
        Schema::dropIfExists('app_settings');

        Schema::create('app_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
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

        Schema::create('whatsapp_messages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('wa_message_id', 191)->nullable();
            $table->string('direction', 16);
            $table->string('message_type', 64)->default('text');
            $table->longText('body')->nullable();
            $table->json('raw_payload')->nullable();
            $table->string('status', 32)->nullable();
            $table->timestamp('message_timestamp')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_message_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('template_code');
            $table->string('display_name');
            $table->string('language')->default('es');
            $table->string('category')->default('utility');
            $table->string('status')->default('approved');
            $table->unsignedBigInteger('current_revision_id')->nullable();
            $table->string('wa_business_account')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('approval_requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_template_revisions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('template_id');
            $table->unsignedInteger('version')->default(1);
            $table->string('status')->default('approved');
            $table->string('header_type')->default('text');
            $table->text('header_text')->nullable();
            $table->longText('body_text');
            $table->text('footer_text')->nullable();
            $table->json('buttons')->nullable();
            $table->json('variables')->nullable();
            $table->string('quality_rating')->default('unknown');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('patient_data', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number')->nullable();
            $table->string('lname')->default('');
            $table->string('lname2')->nullable();
            $table->string('fname')->default('');
            $table->string('mname')->nullable();
            $table->string('celular')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('procedimiento_proyectado', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('form_id');
            $table->string('procedimiento_proyectado');
            $table->string('doctor')->nullable();
            $table->string('hc_number');
            $table->string('sede_departamento')->nullable();
            $table->string('estado_agenda')->nullable();
            $table->date('fecha')->nullable();
            $table->time('hora')->nullable();
            $table->boolean('sigcenter_present')->default(true);
            $table->timestamps();
        });

        Schema::create('whatsapp_appointment_reminders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->string('wa_number', 32)->nullable();
            $table->string('hc_number', 64);
            $table->unsignedBigInteger('form_id');
            $table->string('source_type', 24);
            $table->string('template_code', 191);
            $table->string('reminder_window', 16);
            $table->string('dedupe_key', 191)->unique();
            $table->dateTime('event_at');
            $table->string('status', 24)->default('pending');
            $table->string('template_message_id', 191)->nullable();
            $table->json('payload')->nullable();
            $table->string('response_value', 64)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_handoffs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('wa_number', 32);
            $table->string('status', 24)->default('queued');
            $table->string('priority', 24)->default('normal');
            $table->string('topic', 64)->nullable();
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

        \DB::table('app_settings')->insert([
            ['name' => 'whatsapp_cloud_enabled', 'value' => '1', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'whatsapp_cloud_phone_number_id', 'value' => '1234567890', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'whatsapp_cloud_access_token', 'value' => 'test-token', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'whatsapp_cloud_api_version', 'value' => 'v17.0', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'whatsapp_cloud_default_country_code', 'value' => '593', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'whatsapp_reminders_enabled', 'value' => '1', 'created_at' => now(), 'updated_at' => now()],
        ]);

        \DB::table('whatsapp_message_templates')->insert([
            'id' => 1,
            'template_code' => 'recordatorio_cita_medica_cive',
            'display_name' => 'Recordatorio cita',
            'language' => 'es',
            'category' => 'utility',
            'status' => 'approved',
            'current_revision_id' => 11,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_template_revisions')->insert([
            'id' => 11,
            'template_id' => 1,
            'version' => 1,
            'status' => 'approved',
            'header_type' => 'text',
            'header_text' => 'Recordatorio',
            'body_text' => 'Hola {{1}}, te recordamos tu cita en {{2}} el {{3}} a las {{4}} con {{5}}. Dirección: {{6}}',
            'buttons' => json_encode([
                ['type' => 'quick_reply', 'text' => 'Confirmo asistencia'],
                ['type' => 'quick_reply', 'text' => 'Necesito reagendar'],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        config()->set('whatsapp.transport.dry_run', true);
        config()->set('whatsapp.migration.automation.dry_run', false);
        config()->set('whatsapp.migration.reminders.enabled', true);
        config()->set('whatsapp.migration.reminders.consultation_template_code', 'recordatorio_cita_medica_cive');
        config()->set('whatsapp.migration.reminders.image_template_code', 'recordatorio_cita_medica_cive');
        config()->set('whatsapp.migration.reminders.windows.24h', 1440);
        config()->set('whatsapp.migration.reminders.windows.2h', 120);
        config()->set('whatsapp.migration.reminders.window_tolerance_minutes', 15);
    }

    public function test_it_dispatches_consultation_reminders_without_duplicates(): void
    {
        $eventAt = Carbon::now('America/Guayaquil')->addMinutes(1440);

        \DB::table('patient_data')->insert([
            'hc_number' => 'HC001',
            'fname' => 'Rocio',
            'lname' => 'Retto',
            'celular' => '0999000111',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('procedimiento_proyectado')->insert([
            'form_id' => 5001,
            'procedimiento_proyectado' => 'CONSULTA OFTALMOLOGICA NUEVO PACIENTE',
            'doctor' => 'Jorge Luis de Vera',
            'hc_number' => 'HC001',
            'sede_departamento' => 'Villa Club',
            'estado_agenda' => 'AGENDADO',
            'fecha' => $eventAt->toDateString(),
            'hora' => $eventAt->format('H:i:s'),
            'sigcenter_present' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(WhatsappAppointmentReminderService::class);

        $first = $service->dispatchWindow('24h', false, 50);
        $this->assertSame(1, $first['sent']);
        $this->assertDatabaseHas('whatsapp_appointment_reminders', [
            'form_id' => 5001,
            'status' => 'sent',
            'source_type' => 'servicios_oftalmologicos_generales',
        ]);
        $this->assertDatabaseHas('whatsapp_conversations', [
            'wa_number' => '593999000111',
            'needs_human' => 0,
        ]);

        $second = $service->dispatchWindow('24h', false, 50);
        $this->assertSame(0, $second['sent']);
        $this->assertDatabaseCount('whatsapp_appointment_reminders', 1);
    }

    public function test_it_marks_reminder_confirmed_when_patient_confirms(): void
    {
        $eventAt = Carbon::now('America/Guayaquil')->addMinutes(1440);

        \DB::table('patient_data')->insert([
            'hc_number' => 'HC002',
            'fname' => 'Maria',
            'lname' => 'Paz',
            'celular' => '0999000222',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('procedimiento_proyectado')->insert([
            'form_id' => 5002,
            'procedimiento_proyectado' => 'CONSULTA OFTALMOLOGICA CONTROL',
            'doctor' => 'Emilda Dib',
            'hc_number' => 'HC002',
            'sede_departamento' => 'Ceibos',
            'estado_agenda' => 'AGENDADO',
            'fecha' => $eventAt->toDateString(),
            'hora' => $eventAt->format('H:i:s'),
            'sigcenter_present' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(WhatsappAppointmentReminderService::class);
        $service->dispatchWindow('24h', false, 50);

        $conversation = \App\Models\WhatsappConversation::query()->where('patient_hc_number', 'HC002')->firstOrFail();
        $result = $service->handleInboundResponse($conversation, 'Confirmar');

        $this->assertTrue((bool) $result['handled']);
        $this->assertFalse((bool) $result['handoff_requested']);
        $this->assertDatabaseHas('whatsapp_appointment_reminders', [
            'form_id' => 5002,
            'status' => 'responded',
            'response_value' => 'confirmar',
        ]);
    }

    public function test_it_groups_multiple_imaging_events_for_the_same_patient_and_date(): void
    {
        $baseDate = Carbon::now('America/Guayaquil')->addMinutes(1440)->toDateString();

        \DB::table('patient_data')->insert([
            'hc_number' => 'HC004',
            'fname' => 'Wilfrido',
            'lname' => 'Chele',
            'lname2' => 'Chele',
            'celular' => '0999000444',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('procedimiento_proyectado')->insert([
            [
                'form_id' => 6001,
                'procedimiento_proyectado' => 'IMAGENES - OCT DEL NERVIO OPTICO (AO) - AMBOS OJOS',
                'doctor' => 'Germania Briones',
                'hc_number' => 'HC004',
                'sede_departamento' => 'Villa Club',
                'estado_agenda' => 'AGENDADO',
                'fecha' => $baseDate,
                'hora' => '09:10:00',
                'sigcenter_present' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'form_id' => 6002,
                'procedimiento_proyectado' => 'IMAGENES - TOPOGRAFIA CORNEAL - AMBOS OJOS',
                'doctor' => 'Germania Briones',
                'hc_number' => 'HC004',
                'sede_departamento' => 'Villa Club',
                'estado_agenda' => 'AGENDADO',
                'fecha' => $baseDate,
                'hora' => '09:15:00',
                'sigcenter_present' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'form_id' => 6003,
                'procedimiento_proyectado' => 'IMAGENES - MICROSCOPIA ESPECULAR (AO) - AMBOS OJOS',
                'doctor' => 'Germania Briones',
                'hc_number' => 'HC004',
                'sede_departamento' => 'Villa Club',
                'estado_agenda' => 'AGENDADO',
                'fecha' => $baseDate,
                'hora' => '09:20:00',
                'sigcenter_present' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $service = app(WhatsappAppointmentReminderService::class);
        $result = $service->dispatchWindow('24h', false, 50, ['ignore_window' => true]);

        $this->assertSame(1, $result['sent']);
        $this->assertDatabaseCount('whatsapp_appointment_reminders', 1);
        $this->assertDatabaseHas('whatsapp_appointment_reminders', [
            'form_id' => 6001,
            'hc_number' => 'HC004',
            'status' => 'sent',
            'source_type' => 'imagenes',
        ]);

        $reminder = \App\Models\WhatsappAppointmentReminder::query()->firstOrFail();
        $payload = is_array($reminder->payload) ? $reminder->payload : [];

        $this->assertSame('Exámenes de imágenes programados', $payload['procedimiento'] ?? null);
        $this->assertSame(3, $payload['group_count'] ?? null);
        $this->assertSame('09:10', Carbon::parse((string) $reminder->event_at, 'America/Guayaquil')->format('H:i'));
    }

    public function test_it_routes_reminder_to_agent_when_patient_requests_agent(): void
    {
        $eventAt = Carbon::now('America/Guayaquil')->addMinutes(1440);

        \DB::table('patient_data')->insert([
            'hc_number' => 'HC003',
            'fname' => 'Zoila',
            'lname' => 'Rodriguez',
            'celular' => '0999000333',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('procedimiento_proyectado')->insert([
            'form_id' => 5003,
            'procedimiento_proyectado' => 'CONSULTA OFTALMOLOGICA CONTROL',
            'doctor' => 'Jessica Ortega',
            'hc_number' => 'HC003',
            'sede_departamento' => 'Villa Club',
            'estado_agenda' => 'AGENDADO',
            'fecha' => $eventAt->toDateString(),
            'hora' => $eventAt->format('H:i:s'),
            'sigcenter_present' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(WhatsappAppointmentReminderService::class);
        $service->dispatchWindow('24h', false, 50);

        $conversation = \App\Models\WhatsappConversation::query()->where('patient_hc_number', 'HC003')->firstOrFail();
        $result = $service->handleInboundResponse($conversation, 'Necesito reagendar');

        $this->assertTrue((bool) $result['handled']);
        $this->assertTrue((bool) $result['handoff_requested']);
        $this->assertDatabaseHas('whatsapp_appointment_reminders', [
            'form_id' => 5003,
            'status' => 'responded',
            'response_value' => 'agente',
        ]);
        $this->assertDatabaseHas('whatsapp_handoffs', [
            'conversation_id' => $conversation->id,
            'status' => 'queued',
        ]);
    }
}
