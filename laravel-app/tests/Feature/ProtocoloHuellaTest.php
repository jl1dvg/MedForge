<?php

namespace Tests\Feature;

use App\Modules\Cirugias\Services\CirugiaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class ProtocoloHuellaTest extends TestCase
{
    private CirugiaService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function ($table) {
            $table->increments('id');
            $table->string('username')->unique();
            $table->string('name')->nullable();
            $table->string('password');
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('protocolo_huellas', function ($table) {
            $table->id();
            $table->unsignedInteger('protocolo_id')->nullable();
            $table->unsignedInteger('usuario_id')->nullable();
            $table->string('evento', 50)->default('guardado');
            $table->dateTime('creado_en');
            $table->dateTime('actualizado_en');
            $table->unique(['protocolo_id', 'usuario_id']);
        });

        // protocolo_auditoria es verificada con tableExists() dentro del service
        Schema::create('protocolo_auditoria', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('protocolo_id')->nullable();
            $table->string('form_id');
            $table->string('hc_number');
            $table->string('evento');
            $table->integer('status')->default(0);
            $table->integer('version')->default(0);
            $table->unsignedInteger('usuario_id')->nullable();
            $table->dateTime('creado_en');
        });

        Schema::create('protocolo_data', function ($table) {
            $table->increments('id');
            $table->string('form_id')->unique();
            $table->string('hc_number');
            $table->integer('status')->default(0);
            $table->integer('version')->default(0);
            $table->unsignedInteger('protocolo_firmado_por')->nullable();
            $table->dateTime('fecha_firma')->nullable();
            $table->timestamps();
        });

        $this->service = new CirugiaService(DB::connection()->getPdo());
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('protocolo_huellas');
        Schema::dropIfExists('protocolo_auditoria');
        Schema::dropIfExists('protocolo_data');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function callRegistrarHuella(int $protocoloId, int $userId, string $evento): void
    {
        $method = new ReflectionMethod(CirugiaService::class, 'registrarHuella');
        $method->setAccessible(true);
        $method->invoke($this->service, $protocoloId, $userId, $evento);
    }

    private function crearUsuario(string $username = 'doctor1', string $password = 'secret'): object
    {
        DB::table('users')->insert([
            'username' => $username,
            'password' => Hash::make($password),
            'email'    => "{$username}@test.com",
        ]);
        return DB::table('users')->where('username', $username)->first();
    }

    private function huellasPara(int $protocoloId): \Illuminate\Support\Collection
    {
        return collect(DB::select(
            'SELECT * FROM protocolo_huellas WHERE protocolo_id = ?',
            [$protocoloId]
        ));
    }

    // -------------------------------------------------------------------------
    // Caso 1: primera huella crea el registro correctamente
    // -------------------------------------------------------------------------

    public function test_primera_huella_crea_registro(): void
    {
        $user = $this->crearUsuario();

        $this->callRegistrarHuella(42, (int) $user->id, 'guardado');

        $huellas = $this->huellasPara(42);

        $this->assertCount(1, $huellas, 'Debe existir exactamente 1 huella.');

        $huella = $huellas->first();
        $this->assertEquals(42, $huella->protocolo_id);
        $this->assertEquals((int) $user->id, $huella->usuario_id);
        $this->assertEquals('guardado', $huella->evento);
        $this->assertNotNull($huella->creado_en);
        $this->assertNotNull($huella->actualizado_en);
    }

    // -------------------------------------------------------------------------
    // Caso 2: mismo usuario reedita → no duplica, actualiza actualizado_en
    // -------------------------------------------------------------------------

    public function test_misma_firma_no_duplica_y_actualiza_fecha(): void
    {
        $user = $this->crearUsuario();

        $this->callRegistrarHuella(42, (int) $user->id, 'guardado');

        $huellaInicial = DB::selectOne(
            'SELECT * FROM protocolo_huellas WHERE protocolo_id = ? AND usuario_id = ?',
            [42, $user->id]
        );

        // Avanzar el tiempo para que actualizado_en sea diferente
        sleep(1);

        $this->callRegistrarHuella(42, (int) $user->id, 'editado');

        $huellas = $this->huellasPara(42);
        $this->assertCount(1, $huellas, 'No debe duplicar el registro.');

        $huellaActualizada = $huellas->first();
        $this->assertEquals('editado', $huellaActualizada->evento, 'El evento debe actualizarse.');
        $this->assertEquals(
            $huellaInicial->creado_en,
            $huellaActualizada->creado_en,
            'creado_en no debe cambiar.'
        );
        $this->assertGreaterThan(
            $huellaInicial->actualizado_en,
            $huellaActualizada->actualizado_en,
            'actualizado_en debe avanzar.'
        );
    }

    // -------------------------------------------------------------------------
    // Caso 3: otro usuario edita el mismo protocolo → crea segundo registro
    //         último editor = el de mayor actualizado_en
    // -------------------------------------------------------------------------

    public function test_otro_usuario_crea_registro_separado_y_es_ultimo_editor(): void
    {
        $doctor  = $this->crearUsuario('doctor1');
        $tecnico = $this->crearUsuario('tecnico1');

        $this->callRegistrarHuella(99, (int) $doctor->id, 'guardado');

        sleep(1);

        $this->callRegistrarHuella(99, (int) $tecnico->id, 'revisado');

        $huellas = $this->huellasPara(99);
        $this->assertCount(2, $huellas, 'Debe haber 2 registros (uno por usuario).');

        $ultimoEditor = DB::selectOne(
            'SELECT ph.*, u.username
             FROM protocolo_huellas ph
             JOIN users u ON u.id = ph.usuario_id
             WHERE ph.protocolo_id = ?
             ORDER BY ph.actualizado_en DESC
             LIMIT 1',
            [99]
        );

        $this->assertEquals('tecnico1', $ultimoEditor->username, 'El último editor debe ser tecnico1.');
    }

    // -------------------------------------------------------------------------
    // Helpers para actualizarStatus
    // -------------------------------------------------------------------------

    private function crearProtocolo(string $formId = 'FORM-STATUS-01', string $hcNumber = 'HC-001', int $status = 0): void
    {
        DB::table('protocolo_data')->insert([
            'form_id'   => $formId,
            'hc_number' => $hcNumber,
            'status'    => $status,
            'version'   => 0,
        ]);
    }

    private function auditoriasPara(string $formId): \Illuminate\Support\Collection
    {
        return collect(DB::select(
            'SELECT * FROM protocolo_auditoria WHERE form_id = ?',
            [$formId]
        ));
    }

    // -------------------------------------------------------------------------
    // Caso de separación: actualizarStatus escribe en protocolo_auditoria
    // -------------------------------------------------------------------------

    public function test_actualizar_status_registra_protocolo_auditoria(): void
    {
        $formId   = 'FORM-AUD-01';
        $hcNumber = 'HC-AUD-01';
        $user     = $this->crearUsuario('auditor1');

        $this->crearProtocolo($formId, $hcNumber, 0);

        $ok = $this->service->actualizarStatus($formId, $hcNumber, 1, (int) $user->id);

        $this->assertTrue($ok, 'actualizarStatus debe retornar true.');

        $auditorias = $this->auditoriasPara($formId);
        $this->assertCount(1, $auditorias, 'Debe existir 1 registro en protocolo_auditoria.');

        $auditoria = $auditorias->first();
        $this->assertEquals($formId,        $auditoria->form_id);
        $this->assertEquals($hcNumber,      $auditoria->hc_number);
        $this->assertEquals('revisado',     $auditoria->evento);
        $this->assertEquals(1,              (int) $auditoria->status);
        $this->assertEquals((int) $user->id, (int) $auditoria->usuario_id);
    }

    // -------------------------------------------------------------------------
    // Caso de separación: actualizarStatus NO escribe en protocolo_huellas
    // -------------------------------------------------------------------------

    public function test_actualizar_status_no_registra_protocolo_huellas(): void
    {
        $formId   = 'FORM-AUD-02';
        $hcNumber = 'HC-AUD-02';
        $user     = $this->crearUsuario('auditor2');

        $this->crearProtocolo($formId, $hcNumber, 0);

        $this->service->actualizarStatus($formId, $hcNumber, 1, (int) $user->id);

        $huellas = $this->huellasPara(
            (int) DB::table('protocolo_data')->where('form_id', $formId)->value('id')
        );

        $this->assertCount(0, $huellas, 'actualizarStatus NO debe escribir en protocolo_huellas.');
    }

    // -------------------------------------------------------------------------
    // Caso de separación: registrarHuella sigue escribiendo en protocolo_huellas
    //   (simula el flujo de guardado desde la extensión CIVE)
    // -------------------------------------------------------------------------

    public function test_extension_guardado_registra_protocolo_huellas(): void
    {
        $user = $this->crearUsuario('cive_extension_user');

        $this->callRegistrarHuella(200, (int) $user->id, 'guardado');

        $huellas = $this->huellasPara(200);
        $this->assertCount(1, $huellas, 'El flujo de extensión debe registrar 1 huella.');
        $this->assertEquals('guardado', $huellas->first()->evento);
        $this->assertEquals((int) $user->id, (int) $huellas->first()->usuario_id);

        // Además, ese flujo de extensión NO debe tocar protocolo_auditoria
        $this->assertCount(
            0,
            collect(DB::select('SELECT * FROM protocolo_auditoria WHERE usuario_id = ?', [(int) $user->id])),
            'registrarHuella (extensión) no debe escribir en protocolo_auditoria.'
        );
    }

    // -------------------------------------------------------------------------
    // Caso 4: sin credenciales → no registra huella, endpoint responde error
    // -------------------------------------------------------------------------

    public function test_sin_credenciales_no_registra_huella_y_responde_error(): void
    {
        $this->crearUsuario();

        $response = $this->postJson('/protocolos/guardar.php', [
            'form_id'   => 'FORM-001',
            'hc_number' => 'HC-001',
            // sin audit_username ni audit_password
        ]);

        $response->assertJson(['success' => false]);
        $this->assertNotEmpty($response->json('message'), 'Debe retornar un mensaje de error.');

        $this->assertCount(
            0,
            $this->huellasPara(0),
            'No debe existir ninguna huella.'
        );
    }

    // -------------------------------------------------------------------------
    // Caso 5: contraseña incorrecta → no registra huella, endpoint responde error
    // -------------------------------------------------------------------------

    public function test_password_incorrecto_no_registra_huella_y_responde_error(): void
    {
        $user = $this->crearUsuario('doctor2', 'correcta');

        $response = $this->postJson('/protocolos/guardar.php', [
            'form_id'        => 'FORM-002',
            'hc_number'      => 'HC-002',
            'audit_username' => 'doctor2',
            'audit_password' => 'incorrecta',
        ]);

        $response->assertJson(['success' => false]);
        $this->assertNotEmpty($response->json('message'), 'Debe retornar un mensaje de error.');

        $this->assertCount(
            0,
            DB::select('SELECT * FROM protocolo_huellas WHERE usuario_id = ?', [(int) $user->id]),
            'No debe registrarse huella con contraseña incorrecta.'
        );
    }
}
