<?php

namespace Tests\Feature;

use App\Modules\Whatsapp\Services\WhatsappAttributedAppointmentSourceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsappAttributedAppointmentSourceServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'whatsapp_sigcenter_bookings',
            'whatsapp_handoff_events',
            'whatsapp_handoffs',
            'whatsapp_messages',
            'whatsapp_conversation_attributions',
            'whatsapp_conversations',
            'procedimiento_proyectado',
            'patient_data',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('first_name')->default('');
            $table->string('last_name')->default('');
            $table->string('nombre')->default('');
            $table->string('email')->default('');
        });

        Schema::create('whatsapp_conversations', function (Blueprint $table): void {
            $table->id();
            $table->string('wa_number', 32)->index();
            $table->string('display_name', 191)->nullable();
            $table->string('patient_full_name', 191)->nullable();
            $table->string('patient_hc_number', 64)->nullable()->index();
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->timestamp('handoff_requested_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_messages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('direction', 16);
            $table->string('sender_type', 16)->nullable();
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->longText('raw_payload')->nullable();
            $table->timestamp('message_timestamp')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_handoffs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('wa_number', 32);
            $table->unsignedBigInteger('assigned_agent_id')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_handoff_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('handoff_id');
            $table->string('event_type', 64);
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('whatsapp_conversation_attributions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('source_category', 48)->nullable();
            $table->string('source_type', 48)->nullable();
            $table->string('source_id')->nullable();
            $table->string('headline')->nullable();
            $table->string('initial_intent', 64)->nullable();
            $table->string('patient_segment', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_sigcenter_bookings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->string('wa_number', 32);
            $table->string('status', 32)->default('created');
            $table->string('patient_hc_number', 64)->nullable();
            $table->string('sede_nombre')->nullable();
            $table->string('medico_nombre')->nullable();
            $table->string('procedimiento_nombre')->nullable();
            $table->timestamp('fecha_inicio')->nullable();
            $table->timestamp('booked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('procedimiento_proyectado', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('form_id')->unique();
            $table->string('hc_number');
            $table->text('procedimiento_proyectado')->nullable();
            $table->string('procedimiento_nombre')->nullable();
            $table->string('medico_nombre')->nullable();
            $table->string('doctor')->nullable();
            $table->string('sede_departamento')->nullable();
            $table->date('fecha')->nullable();
            $table->time('hora')->nullable();
            $table->boolean('sigcenter_present')->default(true);
            $table->timestamps();
        });

        Schema::create('patient_data', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number')->nullable();
            $table->string('celular', 32)->nullable();
            $table->timestamps();
        });

        DB::table('users')->insert([
            'id' => 5,
            'first_name' => 'Maria',
            'last_name' => 'Agente',
            'nombre' => 'Maria Agente',
            'email' => 'maria@example.com',
        ]);
    }

    public function test_it_returns_bot_api_bookings(): void
    {
        $this->seedConversation(1, '593100', 'HC-1', 5, Carbon::parse('2026-06-24 08:00:00'));
        DB::table('whatsapp_sigcenter_bookings')->insert([
            'id' => 10,
            'conversation_id' => 1,
            'wa_number' => '593100',
            'status' => 'created',
            'patient_hc_number' => 'HC-1',
            'sede_nombre' => 'Villa Club',
            'medico_nombre' => 'Dra. Bot',
            'procedimiento_nombre' => 'Consulta',
            'fecha_inicio' => '2026-06-30 09:00:00',
            'booked_at' => '2026-06-24 09:00:00',
            'created_at' => '2026-06-24 09:00:00',
            'updated_at' => '2026-06-24 09:00:00',
        ]);

        $records = app(WhatsappAttributedAppointmentSourceService::class)
            ->attributedAppointments(Carbon::parse('2026-06-24'), Carbon::parse('2026-06-25'));

        $this->assertCount(1, $records);
        $this->assertSame('bot_api', $records[0]['booking_source']);
        $this->assertSame(10, $records[0]['booking_id']);
        $this->assertSame('whatsapp_sigcenter_bookings:10', $records[0]['observed_booking_key']);
        $this->assertSame('whatsapp_sigcenter_bookings', $records[0]['source_table']);
    }

    public function test_it_returns_human_strong_medium_and_weak_attributed_appointments(): void
    {
        $firstHumanAt = Carbon::parse('2026-06-24 09:00:00');
        $this->seedConversation(1, '593101', 'HC-101', 5, $firstHumanAt);
        $this->seedAgentMessage(1, 5, $firstHumanAt);

        $this->seedManualAppointment(101, 'HC-101', '2026-06-24 10:00:00', '2026-06-29', '09:00:00');
        $this->seedManualAppointment(102, 'HC-101', '2026-06-26 10:00:00', '2026-06-30', '09:30:00');
        $this->seedManualAppointment(103, 'HC-101', '2026-07-10 10:00:00', '2026-07-20', '10:00:00');

        $records = app(WhatsappAttributedAppointmentSourceService::class)
            ->attributedAppointments(Carbon::parse('2026-06-24'), Carbon::parse('2026-07-20'));

        $byForm = [];
        foreach ($records as $record) {
            $byForm[(int) $record['form_id']] = $record;
        }

        $this->assertSame('strong', $byForm[101]['attribution_window']);
        $this->assertSame('medium', $byForm[102]['attribution_window']);
        $this->assertSame('weak', $byForm[103]['attribution_window']);
        $this->assertSame('strong', $byForm[101]['confidence']);
        $this->assertSame('manual_sigcenter', $byForm[101]['booking_source']);
        $this->assertSame('procedimiento_proyectado:101', $byForm[101]['observed_booking_key']);
        $this->assertSame(5, $byForm[101]['agent_id']);
    }

    public function test_it_excludes_manual_appointment_that_matches_bot_booking(): void
    {
        $firstHumanAt = Carbon::parse('2026-06-24 09:00:00');
        $this->seedConversation(1, '593102', 'HC-102', 5, $firstHumanAt);
        $this->seedAgentMessage(1, 5, $firstHumanAt);

        DB::table('whatsapp_sigcenter_bookings')->insert([
            'id' => 20,
            'conversation_id' => 1,
            'wa_number' => '593102',
            'status' => 'created',
            'patient_hc_number' => 'HC-102',
            'fecha_inicio' => '2026-06-30 09:00:00',
            'booked_at' => '2026-06-24 09:10:00',
            'created_at' => '2026-06-24 09:10:00',
            'updated_at' => '2026-06-24 09:10:00',
        ]);
        $this->seedManualAppointment(201, 'HC-102', '2026-06-24 09:11:00', '2026-06-30', '09:00:00');

        $records = app(WhatsappAttributedAppointmentSourceService::class)
            ->attributedAppointments(Carbon::parse('2026-06-24'), Carbon::parse('2026-06-25'));

        $this->assertCount(1, $records);
        $this->assertSame('bot_api', $records[0]['booking_source']);
    }

    public function test_optometry_with_ophthalmology_same_hc_date_is_companion(): void
    {
        $firstHumanAt = Carbon::parse('2026-06-24 09:00:00');
        $this->seedConversation(1, '593200', 'HC-200', 5, $firstHumanAt);
        $this->seedAgentMessage(1, 5, $firstHumanAt);

        // Ophthalmology consult
        $this->seedManualAppointmentWithProcedure(201, 'HC-200', '2026-06-24 10:00:00', '2026-06-29', '09:00:00', 'CONSULTA OFTALMOLOGICA');
        // Optometry same HC + same date
        $this->seedManualAppointmentWithProcedure(202, 'HC-200', '2026-06-24 10:05:00', '2026-06-29', '08:30:00', 'CONSULTA OPTOMETRIA');

        $records = app(WhatsappAttributedAppointmentSourceService::class)
            ->attributedAppointments(Carbon::parse('2026-06-24'), Carbon::parse('2026-06-25'));

        $byForm = [];
        foreach ($records as $record) {
            $byForm[(int) $record['form_id']] = $record;
        }

        $this->assertSame('ophthalmology_consult', $byForm[201]['service_category']);
        $this->assertSame('independent_service', $byForm[201]['service_counting_role']);
        $this->assertFalse($byForm[201]['is_companion_service']);
        $this->assertTrue($byForm[201]['is_primary_clinical_appointment']);

        $this->assertSame('optometry', $byForm[202]['service_category']);
        $this->assertSame('companion_service', $byForm[202]['service_counting_role']);
        $this->assertTrue($byForm[202]['is_companion_service']);
        $this->assertFalse($byForm[202]['is_independent_service']);
        $this->assertFalse($byForm[202]['is_primary_clinical_appointment']);
    }

    public function test_optometry_alone_is_independent_and_primary(): void
    {
        $firstHumanAt = Carbon::parse('2026-06-24 09:00:00');
        $this->seedConversation(1, '593201', 'HC-201', 5, $firstHumanAt);
        $this->seedAgentMessage(1, 5, $firstHumanAt);

        $this->seedManualAppointmentWithProcedure(301, 'HC-201', '2026-06-24 10:00:00', '2026-06-29', '09:00:00', 'EXAMEN OPTOMETRICO');

        $records = app(WhatsappAttributedAppointmentSourceService::class)
            ->attributedAppointments(Carbon::parse('2026-06-24'), Carbon::parse('2026-06-25'));

        $this->assertCount(1, $records);
        $this->assertSame('optometry', $records[0]['service_category']);
        $this->assertSame('independent_service', $records[0]['service_counting_role']);
        $this->assertFalse($records[0]['is_companion_service']);
        $this->assertTrue($records[0]['is_independent_service']);
        $this->assertTrue($records[0]['is_primary_clinical_appointment']);
    }

    public function test_diagnostic_service_is_classified_correctly(): void
    {
        $firstHumanAt = Carbon::parse('2026-06-24 09:00:00');
        $this->seedConversation(1, '593202', 'HC-202', 5, $firstHumanAt);
        $this->seedAgentMessage(1, 5, $firstHumanAt);

        $this->seedManualAppointmentWithProcedure(401, 'HC-202', '2026-06-24 10:00:00', '2026-06-29', '10:00:00', 'OCT RETINA');

        $records = app(WhatsappAttributedAppointmentSourceService::class)
            ->attributedAppointments(Carbon::parse('2026-06-24'), Carbon::parse('2026-06-25'));

        $this->assertCount(1, $records);
        $this->assertSame('diagnostic', $records[0]['service_category']);
        $this->assertSame('independent_service', $records[0]['service_counting_role']);
        $this->assertFalse($records[0]['is_primary_clinical_appointment']);
        $this->assertTrue($records[0]['is_independent_service']);
    }

    public function test_anesthesia_is_preop_category(): void
    {
        $firstHumanAt = Carbon::parse('2026-06-24 09:00:00');
        $this->seedConversation(1, '593203', 'HC-203', 5, $firstHumanAt);
        $this->seedAgentMessage(1, 5, $firstHumanAt);

        $this->seedManualAppointmentWithProcedure(501, 'HC-203', '2026-06-24 10:00:00', '2026-06-29', '07:00:00', 'ANESTESIOLOGIA GENERAL');

        $records = app(WhatsappAttributedAppointmentSourceService::class)
            ->attributedAppointments(Carbon::parse('2026-06-24'), Carbon::parse('2026-06-25'));

        $this->assertCount(1, $records);
        $this->assertSame('preop_or_anesthesia', $records[0]['service_category']);
        $this->assertSame('independent_service', $records[0]['service_counting_role']);
        $this->assertFalse($records[0]['is_primary_clinical_appointment']);
        $this->assertTrue($records[0]['is_independent_service']);
    }

    public function test_revision_de_examenes_is_follow_up_review(): void
    {
        $firstHumanAt = Carbon::parse('2026-06-24 09:00:00');
        $this->seedConversation(1, '593204', 'HC-204', 5, $firstHumanAt);
        $this->seedAgentMessage(1, 5, $firstHumanAt);

        $this->seedManualAppointmentWithProcedure(601, 'HC-204', '2026-06-24 10:00:00', '2026-06-29', '11:00:00', 'REVISION DE EXAMENES');

        $records = app(WhatsappAttributedAppointmentSourceService::class)
            ->attributedAppointments(Carbon::parse('2026-06-24'), Carbon::parse('2026-06-25'));

        $this->assertCount(1, $records);
        $this->assertSame('follow_up_review', $records[0]['service_category']);
        $this->assertSame('independent_service', $records[0]['service_counting_role']);
        $this->assertTrue($records[0]['is_primary_clinical_appointment']);
    }

    private function seedConversation(int $id, string $waNumber, string $hc, int $agentId, Carbon $createdAt): void
    {
        DB::table('whatsapp_conversations')->insert([
            'id' => $id,
            'wa_number' => $waNumber,
            'patient_hc_number' => $hc,
            'assigned_user_id' => $agentId,
            'handoff_requested_at' => $createdAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        DB::table('whatsapp_conversation_attributions')->insert([
            'conversation_id' => $id,
            'source_category' => 'ad',
            'source_type' => 'ad',
            'source_id' => 'ad-1',
            'headline' => 'Promo',
            'initial_intent' => 'booking',
            'patient_segment' => 'captacion',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function seedAgentMessage(int $conversationId, int $agentId, Carbon $at): void
    {
        DB::table('whatsapp_messages')->insert([
            'conversation_id' => $conversationId,
            'direction' => 'outbound',
            'sender_type' => 'agent',
            'sender_id' => $agentId,
            'message_timestamp' => $at,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    private function seedManualAppointment(int $formId, string $hc, string $createdAt, string $date, string $time): void
    {
        $this->seedManualAppointmentWithProcedure($formId, $hc, $createdAt, $date, $time, 'Consulta oftalmológica');
    }

    private function seedManualAppointmentWithProcedure(int $formId, string $hc, string $createdAt, string $date, string $time, string $procedure): void
    {
        DB::table('procedimiento_proyectado')->insert([
            'form_id' => $formId,
            'hc_number' => $hc,
            'procedimiento_proyectado' => $procedure,
            'procedimiento_nombre' => $procedure,
            'medico_nombre' => 'Dra. Humana',
            'doctor' => 'Dra. Humana',
            'sede_departamento' => 'Villa Club',
            'fecha' => $date,
            'hora' => $time,
            'sigcenter_present' => true,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
