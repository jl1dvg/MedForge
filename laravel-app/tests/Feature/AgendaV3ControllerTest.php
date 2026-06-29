<?php

namespace Tests\Feature;

use App\Http\Middleware\LegacySessionBridge;
use App\Http\Middleware\RequireAppPermission;
use App\Http\Middleware\RequireAppSession;
use App\Http\Middleware\RequireLegacyPermission;
use App\Http\Middleware\RequireLegacySession;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AgendaV3ControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        foreach ([
            'agenda_citas_v3',
            'agenda_bloqueos',
            'agenda_horarios',
            'agenda_tipos_cita',
            'agenda_salas',
            'agenda_medicos',
            'agenda_sedes',
            'procedimiento_proyectado',
            'patient_data',
            'visitas',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username')->default('');
            $table->string('password')->default('');
            $table->string('email')->default('');
            $table->string('nombre')->default('');
            $table->string('especialidad')->nullable();
            $table->string('subespecialidad')->nullable();
            $table->string('sede')->nullable();
        });

        Schema::create('agenda_sedes', function (Blueprint $table): void {
            $table->string('id', 32)->primary();
            $table->string('label', 80);
            $table->string('abrev', 8)->default('');
            $table->time('apertura')->default('08:00:00');
            $table->time('cierre')->default('18:00:00');
            $table->boolean('activo')->default(true);
        });

        Schema::create('agenda_medicos', function (Blueprint $table): void {
            $table->string('id', 32)->primary();
            $table->string('nombre', 120);
            $table->string('especialidad', 120)->default('');
            $table->json('areas');
            $table->string('sede_id', 32);
            $table->string('color', 16)->default('#5156be');
            $table->string('iniciales', 4)->default('');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->boolean('activo')->default(true);
        });

        Schema::create('agenda_salas', function (Blueprint $table): void {
            $table->string('id', 32)->primary();
            $table->string('sede_id', 32);
            $table->string('label', 80);
            $table->string('tipo', 32);
            $table->string('area', 32);
            $table->unsignedTinyInteger('cap')->default(1);
            $table->boolean('activo')->default(true);
        });

        Schema::create('agenda_tipos_cita', function (Blueprint $table): void {
            $table->string('id', 32)->primary();
            $table->string('label', 120);
            $table->string('area', 32);
            $table->unsignedSmallInteger('dur_minutos')->default(20);
            $table->json('requiere_tipo_sala');
            $table->boolean('activo')->default(true);
        });

        Schema::create('agenda_horarios', function (Blueprint $table): void {
            $table->id();
            $table->string('medico_id', 32);
            $table->unsignedTinyInteger('dia_semana');
            $table->time('hora_ini');
            $table->time('hora_fin');
            $table->string('sede_id', 32);
            $table->boolean('activo')->default(true);
        });

        Schema::create('agenda_bloqueos', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 8);
            $table->string('ref_id', 32);
            $table->date('fecha');
            $table->time('hora_ini');
            $table->time('hora_fin');
            $table->string('motivo', 200)->default('');
            $table->string('tipo', 32)->default('otro');
            $table->unsignedBigInteger('creado_por')->nullable();
            $table->timestamps();
        });

        Schema::create('agenda_citas_v3', function (Blueprint $table): void {
            $table->id();
            $table->date('fecha');
            $table->string('sede_id', 32);
            $table->string('medico_id', 32);
            $table->string('sala_id', 32);
            $table->string('tipo_id', 32);
            $table->string('paciente', 200);
            $table->string('hc_number', 64)->default('');
            $table->unsignedTinyInteger('edad')->nullable();
            $table->string('afiliacion', 64)->default('');
            $table->string('tel', 32)->default('');
            $table->time('hora_ini');
            $table->time('hora_fin');
            $table->string('estado', 32)->default('agendado');
            $table->string('whatsapp_estado', 32)->default('na');
            $table->time('hora_llegada')->nullable();
            $table->time('hora_sala')->nullable();
            $table->time('hora_consulta')->nullable();
            $table->time('hora_fin_atencion')->nullable();
            $table->text('notas')->nullable();
            $table->boolean('sobreturno')->default(false);
            $table->boolean('hc_llena')->default(false);
            $table->json('hc_data')->nullable();
            $table->unsignedBigInteger('creado_por')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('patient_data', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number', 64)->unique();
            $table->string('fname', 191)->nullable();
            $table->string('mname', 191)->nullable();
            $table->string('lname', 191)->nullable();
            $table->string('lname2', 191)->nullable();
        });

        Schema::create('visitas', function (Blueprint $table): void {
            $table->id();
            $table->date('fecha_visita')->nullable();
            $table->time('hora_llegada')->nullable();
        });

        Schema::create('procedimiento_proyectado', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number', 64)->index();
            $table->string('procedimiento_proyectado', 191)->nullable();
            $table->string('doctor', 191)->nullable();
            $table->date('fecha')->nullable();
            $table->time('hora')->nullable();
            $table->string('sede_departamento', 191)->nullable();
            $table->string('estado_agenda', 64)->nullable();
            $table->string('afiliacion', 64)->nullable();
            $table->boolean('sigcenter_present')->default(true);
            $table->integer('visita_id')->nullable();
        });

        DB::table('agenda_sedes')->insert([
            ['id' => 'ceibos', 'label' => 'Ceibos', 'abrev' => 'CB', 'apertura' => '08:00:00', 'cierre' => '18:00:00', 'activo' => true],
        ]);

        DB::table('agenda_salas')->insert([
            ['id' => 's_cons1', 'sede_id' => 'ceibos', 'label' => 'Consultorio 1', 'tipo' => 'consultorio', 'area' => 'consulta', 'cap' => 1, 'activo' => true],
        ]);

        DB::table('agenda_tipos_cita')->insert([
            ['id' => 't_cons', 'label' => 'Consulta oftalmológica', 'area' => 'consulta', 'dur_minutos' => 20, 'requiere_tipo_sala' => '["consultorio"]', 'activo' => true],
        ]);
    }

    public function test_config_syncs_real_doctors_from_users(): void
    {
        $user = User::query()->create([
            'username' => 'doctor',
            'email' => 'doctor@test.com',
            'nombre' => 'DRA. MARIA LOPEZ',
            'especialidad' => 'OFTALMOLOGIA',
            'subespecialidad' => 'RETINA',
            'sede' => 'Ceibos',
        ]);

        $response = $this->actingAs($user)
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
                RequireAppSession::class,
                RequireAppPermission::class,
            ])
            ->getJson('/v3/api/agenda/config');

        $response->assertOk();
        $response->assertJsonPath('MEDICOS.0.id', 'usr_' . $user->id);
        $response->assertJsonPath('MEDICOS.0.sede', 'ceibos');

        $this->assertDatabaseHas('agenda_medicos', [
            'id' => 'usr_' . $user->id,
            'user_id' => $user->id,
            'sede_id' => 'ceibos',
            'activo' => true,
        ]);
    }

    public function test_legacy_sigcenter_citas_have_visible_fallback_resources(): void
    {
        $user = User::query()->create([
            'username' => 'agenda',
            'email' => 'agenda@test.com',
            'nombre' => 'DRA. MARIA LOPEZ',
            'especialidad' => 'OFTALMOLOGIA',
            'subespecialidad' => 'RETINA',
            'sede' => 'Ceibos',
        ]);

        DB::table('patient_data')->insert([
            'hc_number' => 'HC-300',
            'fname' => 'Ana',
            'mname' => null,
            'lname' => 'Vera',
            'lname2' => 'Mora',
        ]);

        DB::table('procedimiento_proyectado')->insert([
            'id' => 501,
            'hc_number' => 'HC-300',
            'procedimiento_proyectado' => 'CONSULTA - SER-OFT-004 - CONSULTA OFTALMOLOGICA',
            'doctor' => 'DOCTOR NO SINCRONIZADO',
            'fecha' => '2026-06-05',
            'hora' => '09:15:00',
            'sede_departamento' => 'Ceibos',
            'estado_agenda' => 'CONFIRMADO',
            'afiliacion' => 'IESS',
            'sigcenter_present' => true,
        ]);

        $response = $this->actingAs($user)
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
                RequireAppSession::class,
                RequireAppPermission::class,
            ])
            ->getJson('/v3/api/agenda/citas?fecha=2026-06-05&sede=ceibos');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', 501);
        $response->assertJsonPath('data.0._source', 'pp');
        $response->assertJsonPath('data.0._readonly', true);
        $response->assertJsonPath('data.0.medico_id', 'usr_' . $user->id);
        $response->assertJsonPath('data.0.sala_id', 's_cons1');
        $response->assertJsonPath('data.0.tipo_id', 't_cons');
        $response->assertJsonPath('data.0.paciente', 'Ana Vera Mora');
        $response->assertJsonPath('data.0.estado', 'confirmado');
    }
}
