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
            $table->text('nombre');
            $table->string('tipo', 20)->default('unica');
            $table->unsignedSmallInteger('ventana_dias')->nullable();
            $table->tinyInteger('agrupar_por_ojo')->default(1);
            $table->tinyInteger('genera_oportunidad')->default(1);
            $table->tinyInteger('activo')->default(1);
            $table->timestamps();
        });

        Schema::create('solicitud_procedimiento', function (Blueprint $table): void {
            $table->id();
            $table->string('procedimiento', 200)->nullable();
            $table->timestamps();
        });
    }

    public function test_parses_and_creates_rules_from_raw_strings(): void
    {
        \DB::table('solicitud_procedimiento')->insert([
            ['procedimiento' => 'CIRUGIAS - CYP-CCA-001 - CATARATA CON FACOEMULSIFICACION', 'created_at' => now()],
            ['procedimiento' => 'CIRUGIAS - 66984 - REMOCION DE CATARATA',                  'created_at' => now()],
        ]);

        $this->artisan('crm:seed-procedure-rules')->assertExitCode(0);

        $this->assertDatabaseHas('crm_procedure_rules', ['codigo' => 'CYP-CCA-001', 'nombre' => 'CATARATA CON FACOEMULSIFICACION']);
        $this->assertDatabaseHas('crm_procedure_rules', ['codigo' => '66984',        'nombre' => 'REMOCION DE CATARATA']);
        $this->assertSame(2, CrmProcedureRule::count());
    }

    public function test_deduplicates_same_code_from_different_raw_strings(): void
    {
        // Same code appears in two raw formats; most frequent raw string wins for nombre
        \DB::table('solicitud_procedimiento')->insert([
            ['procedimiento' => 'CIRUGIAS - CYP-CCA-001 - CATARATA (nombre A)', 'created_at' => now()],
            ['procedimiento' => 'CIRUGIAS - CYP-CCA-001 - CATARATA (nombre A)', 'created_at' => now()],
            ['procedimiento' => 'CYP-CCA-001 - CATARATA (nombre B)',            'created_at' => now()],
        ]);

        $this->artisan('crm:seed-procedure-rules')->assertExitCode(0);

        $this->assertSame(1, CrmProcedureRule::count());
        $this->assertSame('CATARATA (nombre A)', CrmProcedureRule::where('codigo', 'CYP-CCA-001')->value('nombre'));
    }

    public function test_skips_codes_that_already_have_a_rule(): void
    {
        CrmProcedureRule::create([
            'codigo' => 'CYP-CCA-001', 'nombre' => 'Facoemulsificación clasificada',
            'tipo'   => 'recurrente',  'activo'  => 1,
        ]);
        \DB::table('solicitud_procedimiento')->insert([
            ['procedimiento' => 'CIRUGIAS - CYP-CCA-001 - CATARATA', 'created_at' => now()],
        ]);

        $this->artisan('crm:seed-procedure-rules')->assertExitCode(0);

        $this->assertSame('recurrente', CrmProcedureRule::where('codigo', 'CYP-CCA-001')->value('tipo'));
        $this->assertSame(1, CrmProcedureRule::count());
    }

    public function test_dry_run_does_not_insert(): void
    {
        \DB::table('solicitud_procedimiento')->insert([
            ['procedimiento' => 'CIRUGIAS - CYP-CCA-001 - CATARATA', 'created_at' => now()],
        ]);

        $this->artisan('crm:seed-procedure-rules', ['--dry-run' => true])->assertExitCode(0);

        $this->assertSame(0, CrmProcedureRule::count());
    }

    public function test_ignores_codes_older_than_days_window(): void
    {
        \DB::table('solicitud_procedimiento')->insert([
            ['procedimiento' => 'CIRUGIAS - OLD-CODE-001 - OLD PROC', 'created_at' => now()->subDays(91)],
            ['procedimiento' => 'CIRUGIAS - NEW-CODE-001 - NEW PROC', 'created_at' => now()],
        ]);

        $this->artisan('crm:seed-procedure-rules', ['--days' => 90])->assertExitCode(0);

        $this->assertDatabaseMissing('crm_procedure_rules', ['codigo' => 'OLD-CODE-001']);
        $this->assertDatabaseHas('crm_procedure_rules',    ['codigo' => 'NEW-CODE-001']);
    }
}
