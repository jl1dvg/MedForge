<?php

namespace Tests\Feature;

use App\Modules\Examenes\Services\ImagenesDashboardV3Service;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImagenesDashboardV3ServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'billing_facturacion_real',
            'imagenes_nas_index',
            'imagenes_informes',
            'consulta_examenes',
            'procedimiento_proyectado',
            'patient_data',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('patient_data', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number')->unique();
            $table->string('fname')->nullable();
            $table->string('lname')->nullable();
            $table->string('afiliacion')->nullable();
        });

        Schema::create('procedimiento_proyectado', function (Blueprint $table): void {
            $table->id();
            $table->string('form_id')->unique();
            $table->string('hc_number');
            $table->date('fecha')->nullable();
            $table->string('hora')->nullable();
            $table->text('procedimiento_proyectado')->nullable();
            $table->string('estado_agenda')->nullable();
            $table->string('afiliacion')->nullable();
            $table->string('doctor')->nullable();
            $table->string('id_sede')->nullable();
            $table->string('sede')->nullable();
            $table->integer('sigcenter_present')->default(1);
        });

        Schema::create('consulta_examenes', function (Blueprint $table): void {
            $table->id();
            $table->string('form_id');
            $table->string('hc_number');
            $table->dateTime('consulta_fecha')->nullable();
            $table->string('examen_codigo')->nullable();
            $table->string('examen_nombre')->nullable();
            $table->string('doctor_solicitante')->nullable();
            $table->string('estado')->default('solicitado');
        });

        Schema::create('imagenes_informes', function (Blueprint $table): void {
            $table->id();
            $table->string('form_id');
            $table->dateTime('updated_at')->nullable();
            $table->string('firmado_por')->nullable();
        });

        Schema::create('imagenes_nas_index', function (Blueprint $table): void {
            $table->id();
            $table->string('form_id')->unique();
            $table->integer('has_files')->default(0);
            $table->integer('files_count')->default(0);
        });

        Schema::create('billing_facturacion_real', function (Blueprint $table): void {
            $table->id();
            $table->string('form_id')->unique();
            $table->dateTime('fecha_facturacion')->nullable();
            $table->dateTime('fecha_atencion')->nullable();
            $table->decimal('monto_honorario', 14, 4)->default(0);
            $table->decimal('monto_facturado', 14, 4)->default(0);
            $table->string('numero_factura')->nullable();
            $table->string('factura_id')->nullable();
            $table->string('estado')->nullable();
            $table->string('afiliacion')->nullable();
        });
    }

    public function test_dashboard_separates_real_billing_pending_billing_and_pending_collection(): void
    {
        $this->seedDashboardRows();
        $service = new ImagenesDashboardV3Service(DB::connection()->getPdo());

        $payload = $service->dashboardData([
            'fecha_inicio' => '2026-05-01',
            'fecha_fin' => '2026-05-31',
        ]);

        $this->assertSame(2, $payload['billing']['estudios_con_billing_real']);
        $this->assertSame(300.0, $payload['billing']['monto_honorario_real']);
        $this->assertSame(420.0, $payload['billing']['monto_facturado_real']);
        $this->assertSame(1, $payload['billing']['realizados_sin_billing_real']);
        $this->assertSame(1, $payload['billing']['pendiente_de_pago']);
        $this->assertSame(1, $payload['billing']['pendiente_de_facturar']);
        $this->assertSame(5, $payload['solicitudes']['solicitudes_recibidas']);
        $this->assertSame(1, $payload['solicitudes']['solicitudes_sin_agenda']);
        $this->assertTrue($payload['meta']['cacheable']);
    }

    public function test_detail_rows_are_paginated_and_capped_at_one_hundred_rows(): void
    {
        $this->seedDashboardRows();
        for ($i = 10; $i < 140; $i++) {
            DB::table('procedimiento_proyectado')->insert([
                'form_id' => (string) $i,
                'hc_number' => 'HC' . $i,
                'fecha' => '2026-05-10',
                'hora' => '08:00',
                'procedimiento_proyectado' => 'IMAGENES 012 ECOGRAFIA',
                'estado_agenda' => 'ATENDIDO',
                'sigcenter_present' => 1,
            ]);
        }
        $service = new ImagenesDashboardV3Service(DB::connection()->getPdo());

        $detail = $service->detailRows([
            'fecha_inicio' => '2026-05-01',
            'fecha_fin' => '2026-05-31',
            'per_page' => '500',
        ]);

        $this->assertSame(100, $detail['per_page']);
        $this->assertCount(100, $detail['rows']);
        $this->assertGreaterThan(100, $detail['total']);
    }

    public function test_dashboard_applies_sede_and_affiliation_filters_to_aggregates(): void
    {
        $this->seedDashboardRows();
        $service = new ImagenesDashboardV3Service(DB::connection()->getPdo());

        $payload = $service->dashboardData([
            'fecha_inicio' => '2026-05-01',
            'fecha_fin' => '2026-05-31',
            'sede' => 'CEIBOS',
            'afiliacion' => 'PARTICULAR',
        ]);

        $this->assertSame(1, $payload['operacion']['agendas_periodo']);
        $this->assertSame(1, $payload['billing']['estudios_con_billing_real']);
        $this->assertSame(300.0, $payload['billing']['monto_facturado_real']);
        $this->assertSame(1, $payload['billing']['pendiente_de_pago']);
        $this->assertSame(0, $payload['billing']['pendiente_de_facturar']);
    }

    public function test_ranges_over_one_hundred_twenty_days_disable_interactive_detail(): void
    {
        $this->seedDashboardRows();
        $service = new ImagenesDashboardV3Service(DB::connection()->getPdo());

        $payload = $service->dashboardData([
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-05-31',
        ]);
        $detail = $service->detailRows([
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-05-31',
        ]);

        $this->assertTrue($payload['meta']['summary_mode']);
        $this->assertSame([], $detail['rows']);
        $this->assertSame('Rango mayor a 120 días; use export/resumen para evitar cargar detalle masivo.', $detail['message']);
    }

    private function seedDashboardRows(): void
    {
        DB::table('patient_data')->insert([
            ['hc_number' => '1', 'fname' => 'Ana', 'lname' => 'Uno', 'afiliacion' => 'IESS'],
            ['hc_number' => '2', 'fname' => 'Luis', 'lname' => 'Dos', 'afiliacion' => 'PARTICULAR'],
            ['hc_number' => '3', 'fname' => 'Eva', 'lname' => 'Tres', 'afiliacion' => 'IESS'],
            ['hc_number' => '4', 'fname' => 'No', 'lname' => 'Agenda', 'afiliacion' => 'IESS'],
            ['hc_number' => '5', 'fname' => 'Cancel', 'lname' => 'Ada', 'afiliacion' => 'IESS'],
        ]);

        DB::table('consulta_examenes')->insert([
            ['form_id' => '1', 'hc_number' => '1', 'consulta_fecha' => '2026-05-01 08:00:00', 'examen_codigo' => '012', 'examen_nombre' => 'ECOGRAFIA', 'doctor_solicitante' => 'DR. A'],
            ['form_id' => '2', 'hc_number' => '2', 'consulta_fecha' => '2026-05-02 08:00:00', 'examen_codigo' => '013', 'examen_nombre' => 'TOMOGRAFIA', 'doctor_solicitante' => 'DR. B'],
            ['form_id' => '3', 'hc_number' => '3', 'consulta_fecha' => '2026-05-03 08:00:00', 'examen_codigo' => '014', 'examen_nombre' => 'RESONANCIA', 'doctor_solicitante' => 'DR. A'],
            ['form_id' => '4', 'hc_number' => '4', 'consulta_fecha' => '2026-05-04 08:00:00', 'examen_codigo' => '015', 'examen_nombre' => 'RX', 'doctor_solicitante' => 'DR. C'],
            ['form_id' => '5', 'hc_number' => '5', 'consulta_fecha' => '2026-05-05 08:00:00', 'examen_codigo' => '016', 'examen_nombre' => 'ECOGRAFIA', 'doctor_solicitante' => 'DR. C'],
        ]);

        DB::table('procedimiento_proyectado')->insert([
            ['form_id' => '1', 'hc_number' => '1', 'fecha' => '2026-05-01', 'hora' => '09:00', 'procedimiento_proyectado' => 'IMAGENES 012 ECOGRAFIA', 'estado_agenda' => 'ATENDIDO', 'afiliacion' => 'IESS', 'doctor' => 'DR. IMG', 'sede' => 'MATRIZ', 'sigcenter_present' => 1],
            ['form_id' => '2', 'hc_number' => '2', 'fecha' => '2026-05-02', 'hora' => '09:00', 'procedimiento_proyectado' => 'IMAGENES 013 TOMOGRAFIA', 'estado_agenda' => 'ATENDIDO', 'afiliacion' => 'PARTICULAR', 'doctor' => 'DR. IMG', 'sede' => 'CEIBOS', 'sigcenter_present' => 1],
            ['form_id' => '3', 'hc_number' => '3', 'fecha' => '2026-05-03', 'hora' => '09:00', 'procedimiento_proyectado' => 'IMAGENES 014 RESONANCIA', 'estado_agenda' => 'ATENDIDO', 'afiliacion' => 'IESS', 'doctor' => 'DR. IMG', 'sede' => 'MATRIZ', 'sigcenter_present' => 1],
            ['form_id' => '5', 'hc_number' => '5', 'fecha' => '2026-05-05', 'hora' => '09:00', 'procedimiento_proyectado' => 'IMAGENES 016 ECOGRAFIA', 'estado_agenda' => 'CANCELADO', 'afiliacion' => 'IESS', 'doctor' => 'DR. IMG', 'sede' => 'MATRIZ', 'sigcenter_present' => 1],
        ]);

        DB::table('imagenes_nas_index')->insert([
            ['form_id' => '1', 'has_files' => 1, 'files_count' => 2],
            ['form_id' => '3', 'has_files' => 1, 'files_count' => 1],
        ]);

        DB::table('imagenes_informes')->insert([
            ['form_id' => '1', 'updated_at' => '2026-05-01 12:00:00', 'firmado_por' => 'RAD'],
        ]);

        DB::table('billing_facturacion_real')->insert([
            ['form_id' => '1', 'fecha_facturacion' => '2026-05-01 13:00:00', 'fecha_atencion' => '2026-05-01 09:00:00', 'monto_honorario' => 100, 'monto_facturado' => 120, 'numero_factura' => 'F1', 'factura_id' => 'B1', 'estado' => 'PAGADO', 'afiliacion' => 'IESS'],
            ['form_id' => '2', 'fecha_facturacion' => '2026-05-02 13:00:00', 'fecha_atencion' => '2026-05-02 09:00:00', 'monto_honorario' => 200, 'monto_facturado' => 300, 'numero_factura' => 'F2', 'factura_id' => 'B2', 'estado' => 'PENDIENTE CARTERA', 'afiliacion' => 'PARTICULAR'],
        ]);
    }
}
