<?php

namespace Tests\Feature;

use App\Models\CrmProcedureRule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CrmSeedProcedureRulesCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('crm_procedure_rules');
        Schema::dropIfExists('solicitud_procedimiento');

        Schema::create('crm_procedure_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('grupo_codigo', 100)->nullable();
            $table->string('nombre', 200);
            $table->string('tipo', 20)->default('unica');
            $table->unsignedSmallInteger('ventana_dias')->nullable();
            $table->tinyInteger('agrupar_por_ojo')->default(1);
            $table->tinyInteger('genera_oportunidad')->default(1);
            $table->tinyInteger('activo')->default(1);
            $table->timestamps();
        });

        Schema::create('solicitud_procedimiento', function (Blueprint $table): void {
            $table->id();
            $table->string('procedimiento', 100)->nullable();
            $table->timestamps();
        });
    }

    public function test_creates_stub_rules_for_new_codes(): void
    {
        \DB::table('solicitud_procedimiento')->insert([
            ['procedimiento' => 'CYP-CCA-001', 'created_at' => now()],
            ['procedimiento' => '66984',        'created_at' => now()],
        ]);

        $this->artisan('crm:seed-procedure-rules')
            ->assertExitCode(0);

        $this->assertDatabaseHas('crm_procedure_rules', ['codigo' => 'CYP-CCA-001', 'tipo' => 'unica']);
        $this->assertDatabaseHas('crm_procedure_rules', ['codigo' => '66984',        'tipo' => 'unica']);
        $this->assertSame(2, CrmProcedureRule::count());
    }

    public function test_skips_codes_that_already_have_a_rule(): void
    {
        CrmProcedureRule::create([
            'codigo' => 'CYP-CCA-001', 'nombre' => 'Facoemulsificación',
            'tipo'   => 'recurrente',  'activo'  => 1,
        ]);
        \DB::table('solicitud_procedimiento')->insert([
            ['procedimiento' => 'CYP-CCA-001', 'created_at' => now()],
        ]);

        $this->artisan('crm:seed-procedure-rules')
            ->assertExitCode(0);

        // tipo must not have been overwritten
        $this->assertSame('recurrente', CrmProcedureRule::where('codigo', 'CYP-CCA-001')->value('tipo'));
        $this->assertSame(1, CrmProcedureRule::count());
    }

    public function test_ignores_null_procedure_codes(): void
    {
        \DB::table('solicitud_procedimiento')->insert([
            ['procedimiento' => null,          'created_at' => now()],
            ['procedimiento' => 'CYP-RVI-009', 'created_at' => now()],
        ]);

        $this->artisan('crm:seed-procedure-rules')
            ->assertExitCode(0);

        $this->assertSame(1, CrmProcedureRule::count());
        $this->assertDatabaseHas('crm_procedure_rules', ['codigo' => 'CYP-RVI-009']);
    }

    public function test_ignores_codes_older_than_90_days_when_window_specified(): void
    {
        \DB::table('solicitud_procedimiento')->insert([
            ['procedimiento' => 'OLD-CODE', 'created_at' => now()->subDays(91)],
            ['procedimiento' => 'NEW-CODE', 'created_at' => now()],
        ]);

        $this->artisan('crm:seed-procedure-rules', ['--days' => 90])
            ->assertExitCode(0);

        $this->assertDatabaseMissing('crm_procedure_rules', ['codigo' => 'OLD-CODE']);
        $this->assertDatabaseHas('crm_procedure_rules',    ['codigo' => 'NEW-CODE']);
    }
}
