<?php

namespace Tests\Feature;

use App\Modules\Examenes\Services\ImagenesUiService;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class ImagenesRealizadasFiltersTest extends TestCase
{
    public function test_build_filters_preserves_afiliacion_categoria(): void
    {
        $service = (new ReflectionClass(ImagenesUiService::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($service, 'buildFilters');
        $method->setAccessible(true);

        $filters = $method->invoke($service, [
            'fecha_inicio' => '2026-05-01',
            'fecha_fin' => '2026-05-31',
            'afiliacion_categoria' => 'publico',
        ]);

        $this->assertSame('publico', $filters['afiliacion_categoria']);
        $this->assertSame('2026-05-01', $filters['fecha_inicio']);
        $this->assertSame('2026-05-31', $filters['fecha_fin']);
    }

    public function test_realizadas_payload_contract_documents_afiliacion_categoria_options(): void
    {
        $method = new ReflectionMethod(ImagenesUiService::class, 'imagenesRealizadas');
        $returnType = (string) $method->getDocComment();

        $this->assertStringContainsString('afiliacionCategoriaOptions', $returnType);
    }
}
