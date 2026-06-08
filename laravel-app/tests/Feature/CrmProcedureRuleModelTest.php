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
            $table->text('nombre');
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

    // --- parseProcedureCode ---

    public function test_parse_three_segment_cyp_code(): void
    {
        $result = CrmProcedureRule::parseProcedureCode('CIRUGIAS - CYP-CCA-001 - CATARATA CON FACOEMULSIFICACION');
        $this->assertSame('CYP-CCA-001', $result['codigo']);
        $this->assertSame('CATARATA CON FACOEMULSIFICACION', $result['nombre']);
    }

    public function test_parse_two_segment_cyp_code(): void
    {
        $result = CrmProcedureRule::parseProcedureCode('CYP-RVI-009 - FOTOCOAGULACIÓN FOCAL');
        $this->assertSame('CYP-RVI-009', $result['codigo']);
        $this->assertSame('FOTOCOAGULACIÓN FOCAL', $result['nombre']);
    }

    public function test_parse_three_segment_numeric_code(): void
    {
        $result = CrmProcedureRule::parseProcedureCode('CIRUGIAS - 66984 - REMOCION DE CATARATA');
        $this->assertSame('66984', $result['codigo']);
        $this->assertSame('REMOCION DE CATARATA', $result['nombre']);
    }

    public function test_parse_two_segment_numeric_code(): void
    {
        $result = CrmProcedureRule::parseProcedureCode('66984 - REMOCION DE CATARATA');
        $this->assertSame('66984', $result['codigo']);
        $this->assertSame('REMOCION DE CATARATA', $result['nombre']);
    }

    public function test_parse_long_category_name(): void
    {
        $result = CrmProcedureRule::parseProcedureCode('DERECHO DE USO DE EQUIPOS ESPECIALES - 800003 - EQUIPO CROSS LINKING');
        $this->assertSame('800003', $result['codigo']);
        $this->assertSame('EQUIPO CROSS LINKING', $result['nombre']);
    }

    public function test_parse_normalizes_codigo_to_uppercase(): void
    {
        $result = CrmProcedureRule::parseProcedureCode('cyp-cca-001 - Catarata');
        $this->assertSame('CYP-CCA-001', $result['codigo']);
    }

    public function test_parse_returns_null_for_empty_string(): void
    {
        $this->assertNull(CrmProcedureRule::parseProcedureCode(''));
        $this->assertNull(CrmProcedureRule::parseProcedureCode('   '));
    }

    public function test_parse_returns_null_when_no_valid_code_segment(): void
    {
        // All-letters category with no numeric/hyphenated code segment
        $this->assertNull(CrmProcedureRule::parseProcedureCode('CONSULTA GENERAL'));
    }
}
