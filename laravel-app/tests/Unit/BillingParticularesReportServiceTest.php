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

        $this->assertSame('OK', $diagnostic['status']);
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
}
