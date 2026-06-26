<?php

namespace Tests\Unit;

use App\Modules\Reporting\Services\ReportPdfService;
use ReflectionMethod;
use Tests\TestCase;

class ReportPdfServicePackageFileSelectionTest extends TestCase
{
    public function test_oct_nervio_selects_matching_report_images(): void
    {
        $selected = $this->selectFiles('OCT NERVIO OPTICO RNFL', [
            $this->file('paciente_raw_001.jpg', 'jpg', 300),
            $this->file('paciente_hno_001.jpg', 'jpg', 200),
            $this->file('paciente_rnfl_002.jpg', 'jpg', 100),
        ]);

        $this->assertSame(['paciente_hno_001.jpg', 'paciente_rnfl_002.jpg'], array_column($selected, 'name'));
    }

    public function test_device_report_prefers_pdf_files(): void
    {
        $selected = $this->selectFiles('TOPOGRAFIA CORNEAL', [
            $this->file('captura_001.jpg', 'jpg', 300),
            $this->file('topografia_reporte.pdf', 'pdf', 200),
        ]);

        $this->assertSame(['topografia_reporte.pdf'], array_column($selected, 'name'));
    }

    public function test_unknown_exam_keeps_all_files(): void
    {
        $files = [
            $this->file('uno.jpg', 'jpg', 300),
            $this->file('dos.jpg', 'jpg', 200),
        ];

        $selected = $this->selectFiles('EXAMEN NO CLASIFICADO', $files);

        $this->assertSame($files, $selected);
    }

    public function test_angiografia_con_fluoresceina_is_detected_as_retinal_angiography(): void
    {
        $service = new ReportPdfService();
        $method = new ReflectionMethod($service, 'isAngiografiaRetinal');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($service, 'Angiografía con fluoresceína'));
    }

    public function test_tomografia_con_pruebas_provocativas_is_detected_as_oct_angulo(): void
    {
        $service = new ReportPdfService();
        $method = new ReflectionMethod($service, 'isOctAngulo');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($service, 'Informe de TOMOGRAFIA CON PRUEBAS PROVOCATIVAS (281032) - Ambos ojos'));
    }

    public function test_oct_angulo_prefers_angle_files_over_other_oct_variants(): void
    {
        $selected = $this->selectFiles('Informe de TOMOGRAFIA CON PRUEBAS PROVOCATIVAS (281032) - Ambos ojos', [
            $this->file('sanchez_rnfl_001.jpg', 'jpg', 300),
            $this->file('sanchez_macula_002.jpg', 'jpg', 200),
            $this->file('sanchez_angle_003.jpg', 'jpg', 100),
            $this->file('sanchez_angulo_004.jpg', 'jpg', 90),
        ]);

        $this->assertSame(['sanchez_angle_003.jpg', 'sanchez_angulo_004.jpg'], array_column($selected, 'name'));
    }

    /**
     * @param array<int, array<string, mixed>> $files
     * @return array<int, array<string, mixed>>
     */
    private function selectFiles(string $tipoExamen, array $files): array
    {
        $service = new ReportPdfService();
        $method = new ReflectionMethod($service, 'selectPackageFilesForExam');
        $method->setAccessible(true);

        return $method->invoke($service, $files, $tipoExamen, [
            'form_id' => '281556',
            'hc_number' => '0908931660',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function file(string $name, string $ext, int $mtime): array
    {
        return [
            'name' => $name,
            'filename' => $name,
            'size' => 1000,
            'mtime' => $mtime,
            'ext' => $ext,
            'type' => $ext === 'pdf' ? 'application/pdf' : 'image/jpeg',
            'source' => 'nas',
        ];
    }
}
