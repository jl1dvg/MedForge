<?php

namespace Tests\Unit;

use App\Modules\Dashboard\Services\DashboardParityService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class DashboardParityServiceTest extends TestCase
{
    public function test_matriz_sede_filter_matches_villa_club_variants(): void
    {
        $where = $this->invokeSedeWhere('MATRIZ');

        $this->assertStringContainsString('CASE', $where);
        $this->assertStringContainsString("LIKE '%matriz%'", $where);
        $this->assertStringContainsString("LIKE '%villa%'", $where);
        $this->assertStringContainsString("LIKE '%vclub%'", $where);
        $this->assertStringContainsString("= 'MATRIZ'", $where);
    }

    public function test_ceibos_sede_filter_matches_ceibos_variants_only(): void
    {
        $where = $this->invokeSedeWhere('CEIBOS');

        $this->assertStringContainsString('CASE', $where);
        $this->assertStringContainsString("LIKE '%ceib%'", $where);
        $this->assertStringContainsString("= 'CEIBOS'", $where);
        $this->assertStringNotContainsString("= 'MATRIZ'", $where);
    }

    public function test_empty_sede_does_not_filter_unknown_locations(): void
    {
        $this->assertSame('', $this->invokeSedeWhere(''));
    }

    public function test_requested_villa_club_sede_is_normalized_to_matriz(): void
    {
        $service = new DashboardParityService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('normalizeSedeFilter');
        $method->setAccessible(true);

        foreach (['villa', 'VILLA CLUB', 'vclub', 'Villa Club / Matriz'] as $input) {
            $this->assertSame('MATRIZ', $method->invoke($service, $input));
        }
    }

    public function test_dashboard_v3_operational_filter_requires_sede_and_excludes_generated_rows(): void
    {
        $service = new DashboardParityService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('dashboardV3OperationalWhere');
        $method->setAccessible(true);

        $where = (string) $method->invoke($service, 'pp');

        $this->assertStringContainsString('pp.sede_departamento IS NOT NULL', $where);
        $this->assertStringContainsString("TRIM(pp.sede_departamento) != ''", $where);
        $this->assertStringContainsString("UPPER(TRIM(COALESCE(pp.estado_agenda, ''))) != 'GENERADAS'", $where);
    }

    private function invokeSedeWhere(string $sede): string
    {
        $service = new DashboardParityService();
        $reflection = new ReflectionClass($service);

        $sedeProperty = $reflection->getProperty('sede');
        $sedeProperty->setAccessible(true);
        $sedeProperty->setValue($service, $sede);

        $method = $reflection->getMethod('sedeWhere');
        $method->setAccessible(true);

        return (string) $method->invoke($service, 'pp');
    }
}
