<?php

namespace Tests\Feature;

use App\Models\CrmProcedureRule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CrmProcedureRuleModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('crm_procedure_rules');
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
        Cache::flush();
    }

    public function test_forCodigo_returns_rule_when_exists(): void
    {
        CrmProcedureRule::create([
            'codigo'   => 'CYP-CCA-001',
            'nombre'   => 'Facoemulsificación',
            'tipo'     => 'unica',
            'activo'   => 1,
        ]);

        $rule = CrmProcedureRule::forCodigo('CYP-CCA-001');

        $this->assertNotNull($rule);
        $this->assertSame('unica', $rule['tipo']);
        $this->assertSame(1, $rule['agrupar_por_ojo']);
        $this->assertSame(1, $rule['genera_oportunidad']);
    }

    public function test_forCodigo_returns_fallback_when_no_rule(): void
    {
        $rule = CrmProcedureRule::forCodigo('NONEXISTENT-CODE');

        $this->assertSame('unica', $rule['tipo']);
        $this->assertSame(1, $rule['agrupar_por_ojo']);
        $this->assertSame(1, $rule['genera_oportunidad']);
        $this->assertNull($rule['grupo_codigo']);
        $this->assertFalse($rule['matched']);
    }

    public function test_forCodigo_returns_fallback_when_rule_inactive(): void
    {
        CrmProcedureRule::create([
            'codigo'  => '66984',
            'nombre'  => 'Cataract surgery',
            'tipo'    => 'unica',
            'activo'  => 0,
        ]);

        $rule = CrmProcedureRule::forCodigo('66984');

        $this->assertFalse($rule['matched']);
    }

    public function test_forCodigo_caches_result(): void
    {
        CrmProcedureRule::create([
            'codigo'  => 'CYP-RVI-009',
            'nombre'  => 'Avastin intravítreo',
            'tipo'    => 'recurrente',
            'activo'  => 1,
        ]);

        CrmProcedureRule::forCodigo('CYP-RVI-009'); // first call — populates cache

        // Delete from DB; cache should still serve the result
        CrmProcedureRule::where('codigo', 'CYP-RVI-009')->delete();

        $rule = CrmProcedureRule::forCodigo('CYP-RVI-009');
        $this->assertSame('recurrente', $rule['tipo']);
    }
}
