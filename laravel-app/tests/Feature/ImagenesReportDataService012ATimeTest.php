<?php

namespace Tests\Feature;

use App\Modules\Reporting\Services\ImagenesReportDataService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImagenesReportDataService012ATimeTest extends TestCase
{
    public function test_012a_selected_items_use_procedure_time_when_frontend_only_sends_date(): void
    {
        DB::statement('CREATE TABLE procedimiento_proyectado (
            id INTEGER PRIMARY KEY,
            form_id TEXT NOT NULL,
            hc_number TEXT NOT NULL,
            procedimiento_proyectado TEXT,
            fecha TEXT,
            hora TEXT,
            afiliacion TEXT,
            estado_agenda TEXT
        )');

        DB::table('procedimiento_proyectado')->insert([
            'id' => 10,
            'form_id' => '281556',
            'hc_number' => '0908931660',
            'procedimiento_proyectado' => 'IMAGENES - 281032-OCT MACULAR (AO)',
            'fecha' => '2026-06-12',
            'hora' => '14:30:00',
            'afiliacion' => 'ISSFA',
            'estado_agenda' => 'CONFIRMADO',
        ]);

        $service = new ImagenesReportDataService();
        $method = new \ReflectionMethod($service, 'resolveSelectedItemsExamDateTime');
        $method->setAccessible(true);

        $result = $method->invoke($service, [[
            'form_id' => '281556',
            'hc_number' => '0908931660',
            'fecha_examen' => '2026-06-12',
        ]]);

        $this->assertSame([
            'raw' => '2026-06-12 14:30:00',
            'date' => '2026-06-12',
            'time' => '14:30',
        ], $result);
    }

    public function test_012a_selected_items_do_not_invent_midnight_when_only_date_is_available(): void
    {
        DB::statement('CREATE TABLE procedimiento_proyectado (
            id INTEGER PRIMARY KEY,
            form_id TEXT NOT NULL,
            hc_number TEXT NOT NULL,
            procedimiento_proyectado TEXT,
            fecha TEXT,
            hora TEXT,
            afiliacion TEXT,
            estado_agenda TEXT
        )');

        $service = new ImagenesReportDataService();
        $method = new \ReflectionMethod($service, 'resolveSelectedItemsExamDateTime');
        $method->setAccessible(true);

        $result = $method->invoke($service, [[
            'form_id' => '281557',
            'hc_number' => '0908931660',
            'fecha_examen' => '2026-06-12',
        ]]);

        $this->assertNull($result);
    }
}
