<?php

namespace Tests\Feature;

use App\Models\WhatsappConversation;
use App\Modules\Whatsapp\Services\FlowRuntimeExecutionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class WhatsappFlowLeadCaptureCrmOpportunityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'crm_activities',
            'crm_opportunities',
            'crm_contacts',
            'crm_leads',
            'whatsapp_conversations',
        ] as $tableName) {
            Schema::dropIfExists($tableName);
        }

        Schema::create('whatsapp_conversations', function (Blueprint $table): void {
            $table->id();
            $table->string('wa_number', 30);
            $table->string('display_name', 255)->nullable();
            $table->string('patient_hc_number', 100)->nullable();
            $table->string('patient_full_name', 255)->nullable();
            $table->boolean('needs_human')->default(false);
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamps();
        });

        Schema::create('crm_contacts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->string('name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('cedula')->nullable()->unique();
            $table->string('resolution', 20)->default('provisional');
            $table->string('source', 30)->default('manual');
            $table->timestamps();
        });

        Schema::create('crm_opportunities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('contact_id')->index();
            $table->string('title');
            $table->string('stage', 30)->default('nuevo');
            $table->string('phase', 20)->default('operational');
            $table->string('source', 30)->default('manual');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_type', 255)->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('lost_reason', 500)->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('escalation_at')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_activities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('opportunity_id')->index();
            $table->string('type', 30)->default('nota');
            $table->text('description');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_type', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('crm_leads', function (Blueprint $table): void {
            $table->id();
            $table->string('hc_number')->nullable();
            $table->string('name')->default('');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default('open');
            $table->string('source')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function test_flow_lead_capture_creates_central_crm_opportunity(): void
    {
        $conversation = WhatsappConversation::query()->create([
            'wa_number' => '+593991234567',
            'display_name' => 'Paciente WhatsApp',
            'patient_hc_number' => '0912345678',
            'patient_full_name' => 'Paciente WhatsApp',
        ]);

        $context = [
            'cedula' => '0912345678',
            'lead_email' => 'paciente@example.com',
            'lead_source' => 'Landing campaña',
            'lead_source_detail' => 'Interés cirugía',
            'patient' => [
                'full_name' => 'Paciente WhatsApp',
            ],
        ];

        $method = new ReflectionMethod(FlowRuntimeExecutionService::class, 'persistLeadCaptureFromContext');
        $method->setAccessible(true);

        $result = $method->invoke(new FlowRuntimeExecutionService(), $conversation, $context);

        $this->assertSame(true, $result['lead_capture_saved']);
        $this->assertNotEmpty($result['crm_lead_id'] ?? null);
        $this->assertNotEmpty($result['crm_opportunity_id'] ?? null);
        $this->assertDatabaseHas('crm_opportunities', [
            'source' => 'whatsapp',
            'source_id' => $conversation->id,
            'source_type' => 'whatsapp_flow_capture',
        ]);
        $this->assertDatabaseHas('crm_activities', [
            'type' => 'whatsapp',
            'source_id' => $conversation->id,
            'source_type' => 'whatsapp_flow_capture',
        ]);
        $this->assertSame(1, DB::table('crm_contacts')->count());
    }
}
