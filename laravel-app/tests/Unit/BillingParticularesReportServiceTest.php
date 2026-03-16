<?php

namespace Tests\Unit;

use App\Modules\Billing\Services\BillingParticularesReportService;
use App\Modules\Codes\Services\CodePriceService;
use PDO;
use PHPUnit\Framework\TestCase;

class BillingParticularesReportServiceTest extends TestCase
{
    public function test_zero_price_is_treated_as_a_configured_value(): void
    {
        $service = $this->makeServiceWithPricing(['particular' => 0.0]);

        $diagnostic = $this->invokeResolveTarifaDiagnostic($service, '123', [
            'afiliacion' => 'PARTICULAR',
        ]);

        $this->assertSame('PRECIO_CERO', $diagnostic['status']);
        $this->assertSame(0.0, $diagnostic['amount']);
        $this->assertSame('123', $diagnostic['matched_codigo']);
    }

    public function test_missing_price_for_the_resolved_affiliation_is_reported_as_an_error(): void
    {
        $service = $this->makeServiceWithPricing([]);

        $diagnostic = $this->invokeResolveTarifaDiagnostic($service, '123', [
            'afiliacion' => 'PARTICULAR',
        ]);

        $this->assertSame('SIN_PRECIO_AFILIACION', $diagnostic['status']);
        $this->assertSame(0.0, $diagnostic['amount']);
    }

    public function test_zero_price_is_not_classified_as_non_estimable_tariff(): void
    {
        $service = new BillingParticularesReportService(new PDO('sqlite::memory:'));

        $this->assertFalse($this->invokeIsNonEstimableTarifaDiagnostic($service, [
            'status' => 'PRECIO_CERO',
        ]));

        $this->assertTrue($this->invokeIsNonEstimableTarifaDiagnostic($service, [
            'status' => 'SIN_PRECIO_AFILIACION',
        ]));
    }

    public function test_company_filter_only_keeps_rows_for_the_selected_insurer(): void
    {
        $service = new BillingParticularesReportService(new PDO('sqlite::memory:'));

        $filtered = $service->aplicarFiltros($this->insuranceRowsFixture(), [
            'empresa_seguro' => 'salud',
        ]);

        $this->assertCount(3, $filtered);
        $this->assertSame(
            ['SALUD', 'SALUD', 'SALUD'],
            array_values(array_map(
                static fn(array $row): string => (string) ($row['empresa_seguro'] ?? ''),
                $filtered
            ))
        );
    }

    public function test_summary_switches_from_company_breakdown_to_plan_breakdown_when_a_company_is_selected(): void
    {
        $service = new BillingParticularesReportService(new PDO('sqlite::memory:'));

        $summaryByCompany = $service->resumen($this->insuranceRowsFixture());
        $this->assertSame('empresa', $summaryByCompany['insurance_breakdown']['mode']);
        $this->assertSame('Empresa de seguro', $summaryByCompany['insurance_breakdown']['item_label']);
        $this->assertSame('SALUD', $summaryByCompany['top_afiliaciones'][0]['afiliacion']);
        $this->assertSame(3, $summaryByCompany['top_afiliaciones'][0]['cantidad']);

        $companyRows = $service->aplicarFiltros($this->insuranceRowsFixture(), [
            'empresa_seguro' => 'salud',
        ]);
        $summaryByPlan = $service->resumen($companyRows, [
            'empresa_seguro' => 'salud',
        ]);

        $this->assertSame('seguro', $summaryByPlan['insurance_breakdown']['mode']);
        $this->assertSame('Plan de seguro', $summaryByPlan['insurance_breakdown']['item_label']);
        $this->assertSame('Planes de seguro de SALUD', $summaryByPlan['insurance_breakdown']['title']);
        $this->assertSame('SALUD NIVEL 1', $summaryByPlan['top_afiliaciones'][0]['afiliacion']);
        $this->assertSame(2, $summaryByPlan['top_afiliaciones'][0]['cantidad']);
    }

    /**
     * @param array<string, float> $prices
     */
    private function makeServiceWithPricing(array $prices): BillingParticularesReportService
    {
        $service = new BillingParticularesReportService(new PDO('sqlite::memory:'));
        $levels = [[
            'level_key' => 'particular',
            'storage_key' => 'particular',
            'title' => 'PARTICULAR',
            'category' => 'particular',
            'source' => 'test',
        ]];

        $priceService = $this->createMock(CodePriceService::class);
        $priceService->expects($this->once())
            ->method('resolveLevelKey')
            ->with('PARTICULAR', $levels)
            ->willReturn('particular');
        $priceService->expects($this->once())
            ->method('pricesForCode')
            ->with(1, $levels)
            ->willReturn($prices);

        $this->setPrivateProperty($service, 'codePriceLevelsCache', $levels);
        $this->setPrivateProperty($service, 'codePriceService', $priceService);
        $this->setPrivateProperty($service, 'afiliacionCategoriaMapCache', []);
        $this->setPrivateProperty($service, 'tarifaCodeCache', [
            '123' => [
                'id' => 1,
                'codigo' => '123',
                'descripcion' => 'Consulta de prueba',
            ],
        ]);

        return $service;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function insuranceRowsFixture(): array
    {
        return [
            [
                'fecha' => '2026-03-10 08:00:00',
                'hc_number' => '1',
                'tipo' => 'consulta',
                'tipo_atencion' => 'CONSULTA',
                'afiliacion' => 'SALUD NIVEL 1',
                'empresa_seguro' => 'SALUD',
                'empresa_seguro_key' => 'salud',
                'categoria_cliente' => 'privado',
                'estado_encuentro' => 'ATENDIDO',
                'sede' => 'MATRIZ',
                'doctor' => 'DR. A',
                'procedimiento_proyectado' => 'CONSULTA GENERAL',
            ],
            [
                'fecha' => '2026-03-11 09:00:00',
                'hc_number' => '2',
                'tipo' => 'consulta',
                'tipo_atencion' => 'CONSULTA',
                'afiliacion' => 'SALUD NIVEL 1',
                'empresa_seguro' => 'SALUD',
                'empresa_seguro_key' => 'salud',
                'categoria_cliente' => 'privado',
                'estado_encuentro' => 'ATENDIDO',
                'sede' => 'MATRIZ',
                'doctor' => 'DR. B',
                'procedimiento_proyectado' => 'CONSULTA GENERAL',
            ],
            [
                'fecha' => '2026-03-12 10:00:00',
                'hc_number' => '3',
                'tipo' => 'consulta',
                'tipo_atencion' => 'CONSULTA',
                'afiliacion' => 'SALUD NIVEL 2',
                'empresa_seguro' => 'SALUD',
                'empresa_seguro_key' => 'salud',
                'categoria_cliente' => 'privado',
                'estado_encuentro' => 'ATENDIDO',
                'sede' => 'CEIBOS',
                'doctor' => 'DR. C',
                'procedimiento_proyectado' => 'CONSULTA GENERAL',
            ],
            [
                'fecha' => '2026-03-13 11:00:00',
                'hc_number' => '4',
                'tipo' => 'consulta',
                'tipo_atencion' => 'CONSULTA',
                'afiliacion' => 'SEGURO GENERAL MONTEPIO',
                'empresa_seguro' => 'IESS',
                'empresa_seguro_key' => 'iess',
                'categoria_cliente' => 'privado',
                'estado_encuentro' => 'ATENDIDO',
                'sede' => 'MATRIZ',
                'doctor' => 'DR. D',
                'procedimiento_proyectado' => 'CONSULTA GENERAL',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array{amount:float,status:string,reason:string,level_key:string,level_title:string,matched_codigo:string,matched_descripcion:string}
     */
    private function invokeResolveTarifaDiagnostic(
        BillingParticularesReportService $service,
        string $codigo,
        array $row
    ): array {
        $resolver = \Closure::bind(
            function (string $codigo, array $row): array {
                return $this->resolveTarifaDiagnostic($codigo, $row);
            },
            $service,
            BillingParticularesReportService::class
        );

        return $resolver($codigo, $row);
    }

    private function setPrivateProperty(
        BillingParticularesReportService $service,
        string $property,
        mixed $value
    ): void {
        $setter = \Closure::bind(
            function (string $property, mixed $value): void {
                $this->{$property} = $value;
            },
            $service,
            BillingParticularesReportService::class
        );

        $setter($property, $value);
    }

    /**
     * @param array{status?:string} $tarifaDiagnostic
     */
    private function invokeIsNonEstimableTarifaDiagnostic(
        BillingParticularesReportService $service,
        array $tarifaDiagnostic
    ): bool {
        $resolver = \Closure::bind(
            function (array $tarifaDiagnostic): bool {
                return $this->isNonEstimableTarifaDiagnostic($tarifaDiagnostic);
            },
            $service,
            BillingParticularesReportService::class
        );

        return $resolver($tarifaDiagnostic);
    }
}
