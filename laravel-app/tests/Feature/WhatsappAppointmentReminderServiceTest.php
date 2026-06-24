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
            $table->string('sender_type', 32)->nullable();
            $table->unsignedBigInteger('sender_id')->nullable();
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
            'template_code' => 'confirmacion_cita_med_v2',
            'display_name' => 'Recordatorio cita',
            'language' => 'es_EC',
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
            'body_text' => 'Hola {{1}}, te recordamos tu cita el {{2}} a las {{3}} con {{4}}.',
            'buttons' => json_encode([
                ['type' => 'quick_reply', 'text' => 'Confirmo asistencia'],
                ['type' => 'quick_reply', 'text' => 'Necesito reagendar'],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_message_templates')->insert([
            'id' => 2,
            'template_code' => 'recordatorio_cita_pni_imagen_villaclub',
            'display_name' => 'Recordatorio PNI imagen Villa Club',
            'language' => 'es_EC',
            'category' => 'utility',
            'status' => 'approved',
            'current_revision_id' => 12,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_template_revisions')->insert([
            'id' => 12,
            'template_id' => 2,
            'version' => 1,
            'status' => 'approved',
            'header_type' => 'text',
            'header_text' => 'Confirmación',
            'body_text' => 'Hola {{1}}. Fecha {{2}} Hora {{3}} Médico {{4}} Procedimiento {{5}} Sede {{6}} Ubicación {{7}}.',
            'buttons' => json_encode([
                ['type' => 'quick_reply', 'text' => 'Confirmar'],
                ['type' => 'quick_reply', 'text' => 'Agente'],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        config()->set('whatsapp.transport.dry_run', true);
        config()->set('whatsapp.migration.automation.dry_run', false);
        config()->set('whatsapp.migration.reminders.enabled', true);
        config()->set('whatsapp.migration.reminders.consultation_template_code', 'confirmacion_cita_med_v2');
        config()->set('whatsapp.migration.reminders.image_template_code', 'confirmacion_cita_med_v2');
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

    public function test_it_dispatches_24h_catchup_when_event_was_loaded_late_but_is_still_useful(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-22 15:34:00', 'America/Guayaquil'));

        try {
            \DB::table('patient_data')->insert([
                'hc_number' => 'HC-CATCHUP',
                'fname' => 'Jennifer',
                'lname' => 'Jurado',
                'celular' => '0995167738',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            \DB::table('procedimiento_proyectado')->insert([
                'form_id' => 7001,
                'procedimiento_proyectado' => 'SERVICIOS OFTALMOLOGICOS GENERALES - CONTROL',
                'doctor' => 'Pamela Guillen',
                'hc_number' => 'HC-CATCHUP',
                'sede_departamento' => 'Villa Club',
                'estado_agenda' => 'AGENDADO',
                'fecha' => '2026-05-23',
                'hora' => '08:30:00',
                'sigcenter_present' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $service = app(WhatsappAppointmentReminderService::class);
            $result = $service->dispatchWindow('24h', false, 50);

            $this->assertSame(1, $result['sent']);
            $this->assertDatabaseHas('whatsapp_appointment_reminders', [
                'form_id' => 7001,
                'status' => 'sent',
                'source_type' => 'servicios_oftalmologicos_generales',
            ]);
        } finally {
            Carbon::setTestNow();
        }
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

        $this->assertSame(1, $result['sent'], json_encode($result, JSON_UNESCAPED_UNICODE));
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

    public function test_it_uses_no_code_template_variable_mapping_for_reminders(): void
    {
        $eventAt = Carbon::now('America/Guayaquil')->addMinutes(1440);

        $mapping = json_encode([
            1 => 'patient.name',
            2 => 'appointment.date',
            3 => 'appointment.time',
            4 => 'appointment.doctor',
            5 => 'appointment.procedure',
            6 => 'site.name',
            7 => 'site.maps_url',
        ]);

        \DB::table('patient_data')->insert([
            'hc_number' => 'HC-MAP',
            'fname' => 'Ana',
            'lname' => 'Cedeño',
            'celular' => '0999000777',
            'email' => 'ana@example.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('procedimiento_proyectado')->insert([
            'form_id' => 9001,
            'procedimiento_proyectado' => 'SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT-010 - CONSULTA POSTERIOR PROCEDIMIENTO QUIRURGICO',
            'doctor' => 'Pamela Guillen',
            'hc_number' => 'HC-MAP',
            'sede_departamento' => 'MATRIZ',
            'estado_agenda' => 'AGENDADO',
            'fecha' => $eventAt->toDateString(),
            'hora' => $eventAt->format('H:i:s'),
            'sigcenter_present' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new WhatsappAppointmentReminderService(settingsOverride: [
            'whatsapp_reminder_service_template_code' => 'recordatorio_cita_pni_imagen_villaclub',
            'whatsapp_reminder_service_template_variable_map' => (string) $mapping,
            'whatsapp_reminder_site_maps_matriz' => 'https://maps.app.goo.gl/matriz-test',
        ]);
        $result = $service->dispatchWindow('24h', false, 50);

        $this->assertSame(1, $result['sent'], json_encode($result, JSON_UNESCAPED_UNICODE));

        $reminder = \App\Models\WhatsappAppointmentReminder::query()->where('form_id', 9001)->firstOrFail();
        $payload = is_array($reminder->payload) ? $reminder->payload : [];

        $this->assertSame([
            'Ana Cedeño',
            $eventAt->format('d/m/Y'),
            $eventAt->format('H:i'),
            'Pamela Guillen',
            'CONSULTA POSTERIOR PROCEDIMIENTO QUIRURGICO',
            'Matriz',
            'https://maps.app.goo.gl/matriz-test',
        ], array_slice($payload['template_variables'] ?? [], 0, 7));
    }

    public function test_it_sends_location_header_when_reminder_template_header_type_is_uppercase_location(): void
    {
        $eventAt = Carbon::now('America/Guayaquil')->addMinutes(1440);

        \DB::table('whatsapp_template_revisions')
            ->where('id', 11)
            ->update([
                'header_type' => 'LOCATION',
                'header_text' => null,
            ]);

        \DB::table('patient_data')->insert([
            'hc_number' => 'HC-LOC',
            'fname' => 'Laura',
            'lname' => 'Mora',
            'celular' => '0999000999',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('procedimiento_proyectado')->insert([
            'form_id' => 9201,
            'procedimiento_proyectado' => 'CONSULTA OFTALMOLOGICA CONTROL',
            'doctor' => 'Pamela Guillen',
            'hc_number' => 'HC-LOC',
            'sede_departamento' => 'Villa Club',
            'estado_agenda' => 'AGENDADO',
            'fecha' => $eventAt->toDateString(),
            'hora' => $eventAt->format('H:i:s'),
            'sigcenter_present' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new WhatsappAppointmentReminderService(settingsOverride: [
            'whatsapp_reminder_site_lat_villa_club' => '-1.9254',
            'whatsapp_reminder_site_lng_villa_club' => '-80.0011',
        ]);
        $result = $service->dispatchWindow('24h', false, 50);

        $this->assertSame(1, $result['sent'], json_encode($result, JSON_UNESCAPED_UNICODE));

        $message = \App\Models\WhatsappMessage::query()->latest('id')->firstOrFail();
        $payload = is_array($message->raw_payload) ? $message->raw_payload : [];
        $components = data_get($payload, 'template.components', []);

        $this->assertSame('header', $components[0]['type'] ?? null);
        $this->assertSame('location', data_get($components, '0.parameters.0.type'));
        $this->assertSame('-1.9254', data_get($components, '0.parameters.0.location.latitude'));
        $this->assertSame('-80.0011', data_get($components, '0.parameters.0.location.longitude'));
        $this->assertSame('Villa Club', data_get($components, '0.parameters.0.location.name'));
    }

    public function test_it_records_normalized_failure_reason_when_location_header_lacks_coordinates(): void
    {
        $eventAt = Carbon::now('America/Guayaquil')->addMinutes(1440);

        \DB::table('whatsapp_template_revisions')
            ->where('id', 11)
            ->update([
                'header_type' => 'location',
                'header_text' => null,
            ]);

        \DB::table('patient_data')->insert([
            'hc_number' => 'HC-NOLOC',
            'fname' => 'Pedro',
            'lname' => 'Rivas',
            'celular' => '0999000112',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('procedimiento_proyectado')->insert([
            'form_id' => 9202,
            'procedimiento_proyectado' => 'CONSULTA OFTALMOLOGICA CONTROL',
            'doctor' => 'Pamela Guillen',
            'hc_number' => 'HC-NOLOC',
            'sede_departamento' => 'Villa Club',
            'estado_agenda' => 'AGENDADO',
            'fecha' => $eventAt->toDateString(),
            'hora' => $eventAt->format('H:i:s'),
            'sigcenter_present' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(WhatsappAppointmentReminderService::class);
        $result = $service->dispatchWindow('24h', false, 50);

        $this->assertSame(1, $result['failed']);

        $reminder = \App\Models\WhatsappAppointmentReminder::query()->where('form_id', 9202)->firstOrFail();
        $payload = is_array($reminder->payload) ? $reminder->payload : [];

        $this->assertSame('failed', $reminder->status);
        $this->assertSame('location_header_missing_coordinates', $payload['failure_reason'] ?? null);
    }

    public function test_it_skips_optometry_reminders_even_when_procedure_matches_images(): void
    {
        $eventAt = Carbon::now('America/Guayaquil')->addMinutes(1440);

        \DB::table('patient_data')->insert([
            'hc_number' => 'HC-OPT',
            'fname' => 'Juan',
            'lname' => 'Bravo',
            'celular' => '0999000888',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('procedimiento_proyectado')->insert([
            'form_id' => 9101,
            'procedimiento_proyectado' => 'IMAGENES - EXAMEN VISUAL PROGRAMADO',
            'doctor' => 'OPTOMETRIA OPT',
            'hc_number' => 'HC-OPT',
            'sede_departamento' => 'MATRIZ',
            'estado_agenda' => 'AGENDADO',
            'fecha' => $eventAt->toDateString(),
            'hora' => $eventAt->format('H:i:s'),
            'sigcenter_present' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(WhatsappAppointmentReminderService::class);
        $result = $service->dispatchWindow('24h', false, 50);

        $this->assertSame(0, $result['sent']);
        $this->assertDatabaseCount('whatsapp_appointment_reminders', 0);
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
