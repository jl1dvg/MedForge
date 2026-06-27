<?php

namespace Tests\Unit;

use App\Modules\Reporting\Services\ImagenesReportDataService;
use ReflectionMethod;
use Tests\TestCase;

class ImagenesReportDataServiceTemplateMappingTest extends TestCase
{
    public function test_tomografia_con_pruebas_provocativas_maps_to_angulo_template(): void
    {
        $service = new ImagenesReportDataService();
        $method = new ReflectionMethod($service, 'mapearPlantillaInforme');
        $method->setAccessible(true);

        $this->assertSame(
            'angulo',
            $method->invoke($service, 'Informe de TOMOGRAFIA CON PRUEBAS PROVOCATIVAS (281032) - Ambos ojos')
        );
    }

    public function test_autofluorescencia_maps_to_auto_template(): void
    {
        $service = new ImagenesReportDataService();
        $method = new ReflectionMethod($service, 'mapearPlantillaInforme');
        $method->setAccessible(true);

        $this->assertSame(
            'auto',
            $method->invoke($service, 'AUTOFLOURESCENCIA - AMBOS OJOS')
        );
    }
}
