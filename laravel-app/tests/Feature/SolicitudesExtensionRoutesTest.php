<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SolicitudesExtensionRoutesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('solicitud_procedimiento');
        Schema::create('solicitud_procedimiento', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('hc_number');
            $table->string('form_id');
            $table->unsignedInteger('secuencia')->default(1);
            $table->string('tipo')->nullable();
            $table->string('afiliacion')->nullable();
            $table->string('procedimiento')->nullable();
            $table->string('doctor')->nullable();
            $table->dateTime('fecha')->nullable();
            $table->integer('duracion')->nullable();
            $table->string('ojo')->nullable();
            $table->string('prioridad')->nullable();
            $table->string('producto')->nullable();
            $table->text('observacion')->nullable();
            $table->string('sesiones')->nullable();
            $table->string('lente_id')->nullable();
            $table->string('lente_nombre')->nullable();
            $table->string('lente_poder')->nullable();
            $table->text('lente_observacion')->nullable();
            $table->string('incision')->nullable();
            $table->string('estado')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['hc_number', 'form_id', 'secuencia'], 'unique_solicitud');
        });
    }

    public function test_extension_guardar_aliases_are_routed(): void
    {
        foreach ([
            '/api/solicitudes/guardar.php',
            '/api/solicitudes/guardar',
            '/solicitudes/guardar.php',
            '/solicitudes/guardar',
        ] as $path) {
            $response = $this
                ->postJson($path, [], [
                    'Origin' => 'https://cive.ddns.net:8085',
                ]);

            $this->assertNotSame(404, $response->getStatusCode(), "Route {$path} should be registered.");
        }
    }

    public function test_extension_guardar_accepts_real_extension_payload_and_upserts(): void
    {
        $payload = [
            'hcNumber' => '24488',
            'form_id' => '283416',
            'solicitudes' => [
                [
                    'secuencia' => 1,
                    'tipo' => 'CIRUGIA',
                    'afiliacion' => 'PARTICULAR',
                    'procedimiento' => 'FACOEMULSIFICACION + LIO',
                    'doctor' => 'DR TEST',
                    'fecha' => '2026-06-02 09:00:00',
                    'duracion' => '15',
                    'ojo' => ['DERECHO'],
                    'prioridad' => 'NO',
                    'producto' => null,
                    'observacion' => null,
                    'sesiones' => null,
                    'detalles' => [
                        [
                            'principal' => true,
                            'id_lente_intraocular' => 'LIO-1',
                            'lente' => 'LENTE TEST',
                            'poder' => '+20.00',
                            'observaciones' => 'Sin observacion',
                            'incision' => '2.2',
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/solicitudes/guardar.php', $payload, [
            'Origin' => 'https://cive.ddns.net:8085',
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('solicitud_procedimiento', [
            'hc_number' => '24488',
            'form_id' => '283416',
            'secuencia' => 1,
            'procedimiento' => 'FACOEMULSIFICACION + LIO',
            'duracion' => 15,
            'ojo' => 'DERECHO',
            'prioridad' => 'NO',
            'lente_id' => 'LIO-1',
            'lente_nombre' => 'LENTE TEST',
            'lente_poder' => '+20.00',
            'incision' => '2.2',
            'estado' => 'recibida',
        ]);

        $payload['solicitudes'][0]['doctor'] = 'DR ACTUALIZADO';
        $payload['solicitudes'][0]['procedimiento'] = str_repeat('PROCEDIMIENTO LARGO ', 20);
        $secondResponse = $this->postJson('/api/solicitudes/guardar.php', $payload, [
            'Origin' => 'https://cive.ddns.net:8085',
        ]);

        $secondResponse->assertOk()->assertJsonPath('success', true);
        $this->assertSame(1, DB::table('solicitud_procedimiento')->count());
        $this->assertDatabaseHas('solicitud_procedimiento', [
            'hc_number' => '24488',
            'form_id' => '283416',
            'secuencia' => 1,
            'doctor' => 'DR ACTUALIZADO',
            'prioridad' => 'NO',
        ]);
        $storedProcedimiento = DB::table('solicitud_procedimiento')
            ->where('hc_number', '24488')
            ->where('form_id', '283416')
            ->where('secuencia', 1)
            ->value('procedimiento');
        $this->assertSame(255, strlen((string) $storedProcedimiento));
    }
}
