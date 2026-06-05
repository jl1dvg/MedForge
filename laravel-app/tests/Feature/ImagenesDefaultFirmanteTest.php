<?php

namespace Tests\Feature;

use App\Modules\Reporting\Support\ImagenesDefaultFirmante;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImagenesDefaultFirmanteTest extends TestCase
{
    private string $previousConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousConnection = DB::getDefaultConnection();
        config()->set('database.connections.imagenes_firmante_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::setDefaultConnection('imagenes_firmante_test');
        $this->createUsersTable();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('users');
        DB::setDefaultConnection($this->previousConnection);
        DB::purge('imagenes_firmante_test');

        parent::tearDown();
    }

    public function test_resolve_uses_jorge_luis_de_vera_when_no_firmante_is_provided(): void
    {
        DB::table('users')->insert([
            'id' => 7,
            'name' => 'Jorge Luis De Vera Gutierrez',
            'email' => 'jorge@example.test',
            'password' => 'secret',
            'first_name' => 'Jorge',
            'middle_name' => 'Luis',
            'last_name' => 'De Vera',
            'second_last_name' => 'Gutierrez',
            'nombre' => 'Jorge Luis De Vera Gutierrez',
            'full_name' => 'Jorge Luis De Vera Gutierrez',
            'cedula' => '0912345678',
            'registro' => 'REG-001',
            'firma' => 'uploads/sellos/jorge.png',
            'signature_path' => 'uploads/firmas/jorge.png',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $firmante = ImagenesDefaultFirmante::resolve(null);

        $this->assertSame('Jorge Luis', $firmante['nombres']);
        $this->assertSame('De Vera', $firmante['apellido1']);
        $this->assertSame('Gutierrez', $firmante['apellido2']);
        $this->assertSame('0912345678', $firmante['documento']);
        $this->assertSame('uploads/sellos/jorge.png', $firmante['firma']);
        $this->assertSame('uploads/firmas/jorge.png', $firmante['signature_path']);
    }

    public function test_default_user_id_resolves_jorge_even_with_accents(): void
    {
        DB::table('users')->insert([
            'id' => 11,
            'name' => 'Jorge Luis De Vera Gutiérrez',
            'email' => 'jorge.accent@example.test',
            'password' => 'secret',
            'nombre' => 'Jorge Luis De Vera Gutiérrez',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(11, ImagenesDefaultFirmante::defaultUserId());
    }

    private function createUsersTable(): void
    {
        Schema::dropIfExists('users');
        Schema::create('users', static function ($table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('nombre')->nullable();
            $table->string('full_name')->nullable();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('second_last_name')->nullable();
            $table->string('cedula')->nullable();
            $table->string('registro')->nullable();
            $table->string('firma')->nullable();
            $table->string('signature_path')->nullable();
            $table->timestamps();
        });
    }
}
