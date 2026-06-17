<?php

namespace Tests\Feature;

use App\Models\PatientDatum;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PacientesV2LaravelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
        $this->createPatientDataTable();

        $user = new User();
        $user->id = 7;
        $user->exists = true;
        $this->actingAs($user);
    }

    public function test_edit_patient_rejects_missing_hc_number(): void
    {
        $this->putJson('/v2/pacientes/editar', ['fname' => 'TOMAS'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['hc_number']);
    }

    public function test_edit_patient_accepts_empty_optional_relationship_fields(): void
    {
        PatientDatum::query()->create([
            'hc_number' => '0701425019',
            'fname' => 'TOMAS',
            'lname' => 'ROMERO',
            'telefono_alt' => '042681140',
            'medico_tratante_id' => 99,
            'sede_principal' => 'matriz',
        ]);

        $this->putJson('/v2/pacientes/editar', [
            'hc_number' => '0701425019',
            'cedula' => '0701425019',
            'fname' => 'TOMAS',
            'mname' => 'DAVID',
            'lname' => 'ROMERO',
            'lname2' => 'MONTOYA',
            'fecha_nacimiento' => '1959-02-15',
            'sexo' => 'M',
            'celular' => '',
            'telefono_alt' => '',
            'afiliacion' => 'ISSFA',
            'ciudad' => '(no definido)',
            'email' => 'info@cive.ec',
            'direccion' => '',
            'medico_tratante_id' => '',
            'sede_principal' => '',
        ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('patient_data', [
            'hc_number' => '0701425019',
            'cedula' => '0701425019',
            'fname' => 'TOMAS',
            'mname' => 'DAVID',
            'lname' => 'ROMERO',
            'lname2' => 'MONTOYA',
            'telefono_alt' => null,
            'medico_tratante_id' => null,
            'sede_principal' => null,
            'updated_by_type' => 'user',
            'updated_by_identifier' => 'user:7',
        ]);
    }

    public function test_create_patient_uses_eloquent_and_splits_names(): void
    {
        PatientDatum::query()->create([
            'hc_number' => '10001',
            'fname' => 'BASE',
            'lname' => 'PACIENTE',
        ]);

        $this->postJson('/v2/pacientes/crear', [
            'nombres' => 'MARIA FERNANDA',
            'apellidos' => 'CORDERO PLUAS',
            'fecha_nac' => '1990-01-01',
            'sexo' => 'F',
            'telefono' => '0999999999',
            'telefono_alt' => '',
            'afiliacion' => 'PARTICULAR',
            'ciudad' => 'Guayaquil',
            'email' => 'maria@example.test',
            'direccion' => 'Direccion',
            'medico' => '',
            'sede' => '',
        ])
            ->assertCreated()
            ->assertJson(['hc_number' => '010002']);

        $this->assertDatabaseHas('patient_data', [
            'hc_number' => '010002',
            'fname' => 'MARIA',
            'mname' => 'FERNANDA',
            'lname' => 'CORDERO',
            'lname2' => 'PLUAS',
            'telefono_alt' => null,
            'medico_tratante_id' => null,
            'sede_principal' => null,
            'created_by_type' => 'user',
            'created_by_identifier' => 'user:7',
        ]);
    }

    private function createPatientDataTable(): void
    {
        Schema::dropIfExists('patient_data');
        Schema::create('patient_data', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number')->nullable()->unique();
            $table->string('cedula', 64)->nullable();
            $table->date('fecha_caducidad')->nullable();
            $table->string('lname', 100);
            $table->string('lname2', 100)->nullable();
            $table->string('fname', 100);
            $table->string('mname', 100)->nullable();
            $table->string('afiliacion')->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('sexo', 10)->nullable();
            $table->string('celular', 15)->nullable();
            $table->string('telefono_alt', 64)->nullable();
            $table->string('ciudad', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('direccion')->nullable();
            $table->unsignedBigInteger('medico_tratante_id')->nullable();
            $table->string('sede_principal', 64)->nullable();
            $table->string('created_by_type', 20)->nullable();
            $table->string('created_by_identifier', 191)->nullable();
            $table->string('updated_by_type', 20)->nullable();
            $table->string('updated_by_identifier', 191)->nullable();
            $table->timestamps();
        });
    }
}
