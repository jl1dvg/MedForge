<?php

namespace Tests\Feature;

use App\Http\Middleware\LegacySessionBridge;
use App\Http\Middleware\RequireAppPermission;
use App\Http\Middleware\RequireAppSession;
use App\Http\Middleware\RequireLegacyPermission;
use App\Http\Middleware\RequireLegacySession;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AgendaSchedulingControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['procedimiento_proyectado_estado', 'procedimiento_proyectado', 'patient_data', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username');
            $table->string('password')->default('');
            $table->string('email')->default('');
            $table->string('nombre')->default('');
        });

        Schema::create('patient_data', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number', 64)->unique();
            $table->string('fname', 191)->nullable();
            $table->string('mname', 191)->nullable();
            $table->string('lname', 191)->nullable();
            $table->string('lname2', 191)->nullable();
            $table->string('celular', 64)->nullable();
            $table->string('afiliacion', 191)->nullable();
            $table->timestamps();
        });

        Schema::create('procedimiento_proyectado', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('form_id')->unique();
            $table->string('procedimiento_proyectado', 191);
            $table->string('doctor', 191)->nullable();
            $table->string('hc_number', 64)->index();
            $table->string('sede_departamento', 191)->nullable();
            $table->integer('id_sede')->nullable();
            $table->string('estado_agenda', 64)->nullable();
            $table->string('afiliacion', 64)->nullable();
            $table->date('fecha')->nullable();
            $table->time('hora')->nullable();
            $table->boolean('sigcenter_present')->default(true);
            $table->timestamp('sigcenter_last_seen_at')->nullable();
            $table->timestamp('sigcenter_missing_at')->nullable();
            $table->integer('visita_id')->nullable();
            $table->timestamps();
        });

        Schema::create('procedimiento_proyectado_estado', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('form_id');
            $table->string('estado');
            $table->timestamp('fecha_hora_cambio')->nullable();
        });
    }

    public function test_it_creates_a_manual_agenda_appointment_from_react_form_payload(): void
    {
        $user = User::query()->create(['username' => 'agenda', 'email' => 'agenda@test.com']);

        $response = $this
            ->actingAs($user)
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
                RequireAppSession::class,
                RequireAppPermission::class,
            ])
            ->postJson('/v2/api/agenda/citas', [
                'hc_number' => 'HC-200',
                'paciente' => 'Maria Perez Mora',
                'telefono' => '0999999999',
                'fecha' => '2026-06-09',
                'hora' => '09:30',
                'tipo_atencion' => 'CONSULTA',
                'codigo_atencion' => 'SER-OFT-004',
                'detalle_atencion' => 'CONSULTA OFTALMOLOGICA CITA MEDICA',
                'doctor' => 'DRA. ANA LOPEZ',
                'sede' => 'NORTE',
                'afiliacion' => 'Particular',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.hc_number', 'HC-200')
            ->assertJsonPath('data.estado_agenda', 'AGENDADO')
            ->assertJsonPath('data.procedimiento_proyectado', 'CONSULTA - SER-OFT-004 - CONSULTA OFTALMOLOGICA CITA MEDICA');

        $this->assertDatabaseHas('patient_data', [
            'hc_number' => 'HC-200',
            'fname' => 'Maria',
            'lname' => 'Perez',
            'lname2' => 'Mora',
            'celular' => '0999999999',
        ]);

        $this->assertDatabaseHas('procedimiento_proyectado', [
            'hc_number' => 'HC-200',
            'procedimiento_proyectado' => 'CONSULTA - SER-OFT-004 - CONSULTA OFTALMOLOGICA CITA MEDICA',
            'doctor' => 'DRA. ANA LOPEZ',
            'fecha' => '2026-06-09',
            'hora' => '09:30',
            'estado_agenda' => 'AGENDADO',
        ]);
    }
}
