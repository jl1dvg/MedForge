<?php

namespace Tests\Feature;

use App\Models\CrmProcedureRule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CrmValidateProcedureRulesCommandTest extends TestCase
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

    public function test_exits_0_when_all_codes_have_active_rules(): void
    {
        CrmProcedureRule::create([
            'codigo' => 'CYP-CCA-001', 'nombre' => 'Faco',
            'tipo'   => 'unica',       'activo'  => 1,
        ]);
        \DB::table('solicitud_procedimiento')->insert([
            ['procedimiento' => 'CYP-CCA-001', 'created_at' => now()],
        ]);

        $this->artisan('crm:validate-procedure-rules')
            ->assertExitCode(0);
    }

    public function test_exits_1_and_lists_gaps_when_codes_have_no_rule(): void
    {
        \DB::table('solicitud_procedimiento')->insert([
            ['procedimiento' => 'UNCLASSIFIED-CODE', 'created_at' => now()],
        ]);

        $this->artisan('crm:validate-procedure-rules')
            ->expectsOutputToContain('UNCLASSIFIED-CODE')
            ->assertExitCode(1);
    }

    public function test_inactive_rules_count_as_gaps(): void
    {
        CrmProcedureRule::create([
            'codigo' => '66984', 'nombre' => 'Cataract',
            'tipo'   => 'unica', 'activo'  => 0, // inactive
        ]);
        \DB::table('solicitud_procedimiento')->insert([
            ['procedimiento' => '66984', 'created_at' => now()],
        ]);

        $this->artisan('crm:validate-procedure-rules')
            ->expectsOutputToContain('66984')
            ->assertExitCode(1);
    }

    public function test_ignores_null_and_old_codes(): void
    {
        \DB::table('solicitud_procedimiento')->insert([
            ['procedimiento' => null,       'created_at' => now()],
            ['procedimiento' => 'OLD-CODE', 'created_at' => now()->subDays(91)],
        ]);

        $this->artisan('crm:validate-procedure-rules')
            ->assertExitCode(0);
    }
}
