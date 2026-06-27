<?php

namespace Tests\Unit;

use App\Modules\Examenes\Services\ImagenesUiService;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

class ImagenesUiServiceTipoFilterTest extends TestCase
{
    /** @param list<string> $expectedTerms */
    #[DataProvider('tipoFilterProvider')]
    public function test_tipo_examen_filter_expands_catalog_keys_to_real_procedure_aliases(string $value, array $expectedTerms): void
    {
        $service = new ImagenesUiService(new PDO('sqlite::memory:'));
        $method = new ReflectionMethod($service, 'resolveTipoExamenFilterTerms');
        $method->setAccessible(true);

        $terms = $method->invoke($service, $value);

        foreach ($expectedTerms as $term) {
            $this->assertContains($term, $terms);
        }
    }

    /**
     * @return array<string,array{value:string,expectedTerms:list<string>}>
     */
    public static function tipoFilterProvider(): array
    {
        return [
            'oct nervio' => [
                'value' => 'OCT_NERVIO',
                'expectedTerms' => ['oct nervio', 'rnfl', 'nervio optico'],
            ],
            'microscopia especular' => [
                'value' => 'MICROESPECULAR',
                'expectedTerms' => ['microscopia especular', 'especular', 'endotelial'],
            ],
            'autofluorescencia' => [
                'value' => 'AUTOFLUORESCENCIA',
                'expectedTerms' => ['autofluorescencia', 'autoflourescencia', 'faf'],
            ],
        ];
    }
}
