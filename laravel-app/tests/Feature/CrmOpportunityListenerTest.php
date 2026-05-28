<?php

namespace Tests\Feature;

use App\Events\Crm\ExamenSolicitado;
use App\Events\Crm\SolicitudCreada;
use App\Events\Crm\WhatsappLeadQualified;
use App\Models\CrmContact;
use App\Models\CrmOpportunity;
use App\Models\WhatsappLead;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CrmOpportunityListenerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['crm_activities', 'crm_opportunities', 'crm_contacts', 'whatsapp_leads'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::create('crm_contacts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->string('name', 255);
            $table->string('phone', 30);
            $table->string('email', 255)->nullable();
            $table->string('cedula', 30)->nullable()->unique();
            $table->string('resolution', 20)->default('provisional');
            $table->string('source', 30)->default('manual');
            $table->timestamps();
        });
        Schema::create('crm_opportunities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('contact_id')->index();
            $table->string('title', 255);
            $table->string('stage', 30)->default('nuevo');
            $table->string('source', 30)->default('manual');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_type', 255)->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('lost_reason', 500)->nullable();
            $table->timestamps();
        });
        Schema::create('crm_activities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('opportunity_id')->index();
            $table->string('type', 30)->default('nota');
            $table->text('description');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::create('whatsapp_leads', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->index();
            $table->unsignedBigInteger('crm_lead_id')->nullable();
            $table->string('wa_number', 30);
            $table->string('display_name', 255)->nullable();
            $table->string('hc_number', 100)->nullable();
            $table->string('cedula', 30)->nullable();
            $table->string('patient_full_name', 255)->nullable();
            $table->text('motivo_baja');
            $table->string('status', 30)->default('pendiente');
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();
        });
    }

    public function test_whatsapp_lead_creates_nuevo_opportunity(): void
    {
        $wlead = WhatsappLead::query()->create([
            'conversation_id' => 1,
            'wa_number'       => '+593991234567',
            'display_name'    => 'María González',
            'cedula'          => null,
            'motivo_baja'     => 'Interesada en cirugía',
        ]);

        event(new WhatsappLeadQualified($wlead));

        $this->assertEquals(1, CrmContact::query()->count());
        $this->assertEquals(1, CrmOpportunity::query()->count());
        $this->assertEquals('nuevo', CrmOpportunity::query()->first()->stage);
        $this->assertEquals('whatsapp', CrmOpportunity::query()->first()->source);
    }

    public function test_solicitud_creada_creates_interesado_opportunity(): void
    {
        event(new SolicitudCreada(
            solicitudId: 42,
            solicitudData: [
                'paciente_nombre'   => 'Carlos Mendoza',
                'paciente_cedula'   => '0912345678',
                'paciente_telefono' => '+593987654321',
                'servicio'          => 'Consulta cardiología',
            ],
        ));

        $this->assertEquals(1, CrmOpportunity::query()->count());
        $this->assertEquals('interesado', CrmOpportunity::query()->first()->stage);
        $this->assertEquals(42, CrmOpportunity::query()->first()->source_id);
    }

    public function test_examen_solicitado_creates_propuesta_opportunity(): void
    {
        event(new ExamenSolicitado(
            examenId: 77,
            examenData: [
                'paciente_nombre'    => 'Laura Rivas',
                'paciente_cedula'    => '1712345678',
                'paciente_telefono'  => '+593995551234',
                'descripcion_examen' => 'Radiografía de tórax',
            ],
        ));

        $this->assertEquals(1, CrmOpportunity::query()->count());
        $this->assertEquals('propuesta_enviada', CrmOpportunity::query()->first()->stage);
        $this->assertEquals('examen', CrmOpportunity::query()->first()->source);
        $this->assertEquals(77, CrmOpportunity::query()->first()->source_id);
    }

    public function test_duplicate_cedula_reuses_contact(): void
    {
        CrmContact::query()->create([
            'name' => 'Carlos', 'phone' => '+593987654321',
            'cedula' => '0912345678', 'resolution' => 'identified', 'source' => 'whatsapp',
        ]);

        event(new SolicitudCreada(
            solicitudId: 42,
            solicitudData: [
                'paciente_nombre'   => 'Carlos Mendoza',
                'paciente_cedula'   => '0912345678',
                'paciente_telefono' => '+593987654321',
                'servicio'          => 'Consulta cardiología',
            ],
        ));

        $this->assertEquals(1, CrmContact::query()->count());
        $this->assertEquals(1, CrmOpportunity::query()->count());
    }
}
