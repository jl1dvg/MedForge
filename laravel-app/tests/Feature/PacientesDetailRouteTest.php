<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PacientesDetailRouteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->string('nombre')->nullable();
            $table->string('email')->nullable();
            $table->string('profile_photo')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->timestamps();
        });

        DB::table('roles')->insert([
            'id' => 1,
            'name' => 'administrativo',
        ]);

        DB::table('users')->insert([
            'id' => 40,
            'username' => 'doctor',
            'password' => 'secret',
            'nombre' => 'Doctor',
            'email' => 'doctor@example.test',
            'profile_photo' => null,
            'role_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_html_detail_route_redirects_to_react_patient_app(): void
    {
        $this->actingAs(\App\Models\User::query()->findOrFail(40));

        $response = $this->withoutMiddleware()
            ->get('/v2/pacientes/detalles?hc_number=0701425019');

        $response
            ->assertRedirect('/v2/pacientes?hc_number=0701425019');
    }

    public function test_patient_flow_view_uses_vite_bundle_without_legacy_script(): void
    {
        $this->actingAs(\App\Models\User::query()->findOrFail(40));

        $response = $this->withoutMiddleware()
            ->get('/v2/pacientes/flujo');

        $response
            ->assertOk()
            ->assertSee('pacientes-flujo')
            ->assertDontSee('/js/pages/pacientes/flujo.js', false);
    }
}
