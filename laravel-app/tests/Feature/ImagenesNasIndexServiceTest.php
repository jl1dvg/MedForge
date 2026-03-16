<?php

namespace Tests\Feature;

use App\Modules\Examenes\Services\ImagenesNasIndexService;
use App\Modules\Examenes\Services\NasImagenesService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class ImagenesNasIndexServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-03-16 08:00:00');

        Schema::dropIfExists('imagenes_nas_index');
        Schema::dropIfExists('procedimiento_proyectado');

        Schema::create('procedimiento_proyectado', function (Blueprint $table): void {
            $table->id();
            $table->string('form_id', 50)->nullable();
            $table->string('hc_number', 50)->nullable();
            $table->string('procedimiento_proyectado', 255)->nullable();
            $table->dateTime('fecha')->nullable();
        });

        Schema::create('imagenes_nas_index', function (Blueprint $table): void {
            $table->id();
            $table->string('form_id', 50)->unique();
            $table->string('hc_number', 50)->index();
            $table->boolean('has_files')->default(false)->index();
            $table->unsignedInteger('files_count')->default(0);
            $table->unsignedInteger('image_count')->default(0);
            $table->unsignedInteger('pdf_count')->default(0);
            $table->unsignedBigInteger('total_bytes')->default(0);
            $table->dateTime('latest_file_mtime')->nullable();
            $table->string('sample_file', 255)->nullable();
            $table->string('scan_status', 30)->default('pending')->index();
            $table->text('last_error')->nullable();
            $table->unsignedInteger('scan_duration_ms')->nullable();
            $table->timestamp('last_scanned_at')->nullable()->index();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();

        parent::tearDown();
    }

    public function test_scan_uses_days_filter_when_no_explicit_range_is_provided(): void
    {
        $this->insertProcedimiento('FORM-OLD', 'HC-OLD', 'IMAGENES RX', '2026-02-12 09:00:00');
        $this->insertProcedimiento('FORM-IN', 'HC-IN', 'IMAGENES RX', '2026-03-01 09:00:00');
        $this->insertProcedimiento('FORM-FUTURE', 'HC-FUTURE', 'IMAGENES RX', '2026-03-20 09:00:00');
        $this->insertProcedimiento('FORM-LAB', 'HC-LAB', 'LABORATORIO', '2026-03-10 09:00:00');

        $service = new ImagenesNasIndexService($this->makeNasMock(2));
        $result = $service->scan([
            'days' => 30,
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['candidates']);
        $this->assertSame(2, $result['processed']);
        $this->assertSame(['FORM-FUTURE', 'FORM-IN'], $this->indexedForms());
    }

    public function test_scan_uses_explicit_date_range_instead_of_days(): void
    {
        $this->insertProcedimiento('FORM-BEFORE', 'HC-BEFORE', 'IMAGENES RX', '2026-03-04 10:00:00');
        $this->insertProcedimiento('FORM-START', 'HC-START', 'IMAGENES RX', '2026-03-05 11:00:00');
        $this->insertProcedimiento('FORM-END', 'HC-END', 'IMAGENES RX', '2026-03-12 12:00:00');
        $this->insertProcedimiento('FORM-AFTER', 'HC-AFTER', 'IMAGENES RX', '2026-03-13 13:00:00');

        $service = new ImagenesNasIndexService($this->makeNasMock(2));
        $result = $service->scan([
            'days' => 365,
            'from_date' => '2026-03-05',
            'to_date' => '2026-03-12',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['candidates']);
        $this->assertSame(2, $result['processed']);
        $this->assertSame(['FORM-END', 'FORM-START'], $this->indexedForms());
    }

    public function test_scan_rejects_inverted_date_ranges(): void
    {
        $nas = Mockery::mock(NasImagenesService::class);
        $nas->shouldReceive('isAvailable')->never();

        $service = new ImagenesNasIndexService($nas);
        $result = $service->scan([
            'from_date' => '2026-03-20',
            'to_date' => '2026-03-10',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('La opción --from-date no puede ser mayor que --to-date.', $result['error']);
        $this->assertSame(0, $result['processed']);
        $this->assertSame(0, $result['candidates']);
    }

    private function insertProcedimiento(
        string $formId,
        string $hcNumber,
        string $procedimiento,
        string $fecha
    ): void {
        DB::table('procedimiento_proyectado')->insert([
            'form_id' => $formId,
            'hc_number' => $hcNumber,
            'procedimiento_proyectado' => $procedimiento,
            'fecha' => $fecha,
        ]);
    }

    /**
     * @return list<string>
     */
    private function indexedForms(): array
    {
        $forms = DB::table('imagenes_nas_index')
            ->orderBy('form_id')
            ->pluck('form_id')
            ->all();

        return array_values($forms);
    }

    private function makeNasMock(int $expectedScans): NasImagenesService
    {
        $nas = Mockery::mock(NasImagenesService::class);
        $nas->shouldReceive('isAvailable')->once()->andReturn(true);
        $nas->shouldReceive('listFiles')->times($expectedScans)->andReturn([]);
        $nas->shouldReceive('getLastError')->times($expectedScans)->andReturn(null);

        return $nas;
    }
}
