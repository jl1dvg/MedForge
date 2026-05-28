<?php

namespace Tests\Feature;

use App\Models\CrmContact;
use App\Modules\CRM\Services\CrmContactResolverService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CrmContactResolverServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('crm_contacts');
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
    }

    public function test_creates_provisional_contact_when_no_cedula(): void
    {
        $svc = new CrmContactResolverService();
        $contact = $svc->resolve(
            phone: '+593991234567',
            name: 'María González',
            cedula: null,
            source: 'whatsapp',
        );

        $this->assertInstanceOf(CrmContact::class, $contact);
        $this->assertEquals('provisional', $contact->resolution);
        $this->assertNull($contact->cedula);
    }

    public function test_creates_identified_contact_when_cedula_provided(): void
    {
        $svc = new CrmContactResolverService();
        $contact = $svc->resolve(
            phone: '+593991234567',
            name: 'Carlos Mendoza',
            cedula: '0912345678',
            source: 'solicitud',
        );

        $this->assertEquals('identified', $contact->resolution);
        $this->assertEquals('0912345678', $contact->cedula);
    }

    public function test_reuses_existing_contact_by_cedula(): void
    {
        CrmContact::query()->create([
            'name' => 'Carlos', 'phone' => '+593991111111',
            'cedula' => '0912345678', 'resolution' => 'identified', 'source' => 'whatsapp',
        ]);

        $svc = new CrmContactResolverService();
        $contact = $svc->resolve(
            phone: '+593992222222',
            name: 'Carlos Mendoza',
            cedula: '0912345678',
            source: 'solicitud',
        );

        $this->assertEquals(1, CrmContact::query()->count());
        $this->assertEquals('Carlos Mendoza', $contact->fresh()->name);
    }

    public function test_reuses_provisional_contact_by_phone(): void
    {
        CrmContact::query()->create([
            'name' => 'María', 'phone' => '+593991234567',
            'cedula' => null, 'resolution' => 'provisional', 'source' => 'whatsapp',
        ]);

        $svc = new CrmContactResolverService();
        $contact = $svc->resolve(
            phone: '+593991234567',
            name: 'María González',
            cedula: null,
            source: 'whatsapp',
        );

        $this->assertEquals(1, CrmContact::query()->count());
    }

    public function test_upgrades_provisional_to_identified_when_cedula_arrives(): void
    {
        CrmContact::query()->create([
            'name' => 'Ana', 'phone' => '+593991234567',
            'cedula' => null, 'resolution' => 'provisional', 'source' => 'whatsapp',
        ]);

        $svc = new CrmContactResolverService();
        $contact = $svc->resolve(
            phone: '+593991234567',
            name: 'Ana Torres',
            cedula: '1712345678',
            source: 'whatsapp',
        );

        $this->assertEquals(1, CrmContact::query()->count());
        $this->assertEquals('identified', $contact->fresh()->resolution);
        $this->assertEquals('1712345678', $contact->fresh()->cedula);
    }
}
