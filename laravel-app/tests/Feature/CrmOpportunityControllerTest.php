<?php

namespace Tests\Feature;

use App\Http\Middleware\LegacySessionBridge;
use App\Http\Middleware\RequireLegacyPermission;
use App\Http\Middleware\RequireLegacySession;
use App\Models\CrmContact;
use App\Models\CrmOpportunity;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CrmOpportunityControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['crm_activities', 'crm_opportunities', 'crm_contacts', 'users', 'roles'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::create('roles', fn (Blueprint $t) => $t->id());
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username');
            $table->string('password')->default('');
            $table->string('email')->default('');
            $table->string('nombre')->default('');
            $table->string('cedula')->default('');
            $table->string('registro')->default('');
            $table->string('sede')->default('');
            $table->string('especialidad')->default('');
            $table->text('permisos')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
        });
        Schema::create('crm_contacts', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('patient_id')->nullable();
            $table->string('name'); $table->string('phone');
            $table->string('email')->nullable(); $table->string('cedula')->nullable()->unique();
            $table->string('resolution', 20)->default('provisional');
            $table->string('source', 30)->default('manual'); $table->timestamps();
        });
        Schema::create('crm_opportunities', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('contact_id')->index();
            $table->string('title'); $table->string('stage', 30)->default('nuevo');
            $table->string('source', 30)->default('manual');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_type', 255)->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('lost_reason', 500)->nullable(); $table->timestamps();
        });
        Schema::create('crm_activities', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('opportunity_id')->index();
            $table->string('type', 30)->default('nota'); $table->text('description');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    private function makeUser(): User
    {
        return User::query()->create(['username' => 'test', 'email' => 'test@test.com']);
    }

    private function makeContact(): CrmContact
    {
        return CrmContact::query()->create(['name' => 'María', 'phone' => '+5931', 'source' => 'whatsapp']);
    }

    public function test_index_returns_paginated_opportunities(): void
    {
        $contact = $this->makeContact();
        CrmOpportunity::query()->create(['contact_id' => $contact->id, 'title' => 'Op 1', 'stage' => 'nuevo', 'source' => 'whatsapp']);
        CrmOpportunity::query()->create(['contact_id' => $contact->id, 'title' => 'Op 2', 'stage' => 'interesado', 'source' => 'solicitud']);

        $this->actingAs($this->makeUser())
            ->withoutMiddleware([LegacySessionBridge::class, RequireLegacySession::class, RequireLegacyPermission::class])
            ->getJson('/v2/crm/opportunities')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_index_filters_by_stage(): void
    {
        $contact = $this->makeContact();
        CrmOpportunity::query()->create(['contact_id' => $contact->id, 'title' => 'Nuevo', 'stage' => 'nuevo', 'source' => 'whatsapp']);
        CrmOpportunity::query()->create(['contact_id' => $contact->id, 'title' => 'Interesado', 'stage' => 'interesado', 'source' => 'solicitud']);

        $this->actingAs($this->makeUser())
            ->withoutMiddleware([LegacySessionBridge::class, RequireLegacySession::class, RequireLegacyPermission::class])
            ->getJson('/v2/crm/opportunities?stage=nuevo')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.stage', 'nuevo');
    }

    public function test_update_changes_stage(): void
    {
        $contact = $this->makeContact();
        $opp = CrmOpportunity::query()->create(['contact_id' => $contact->id, 'title' => 'Test', 'stage' => 'nuevo', 'source' => 'whatsapp']);

        $this->actingAs($this->makeUser())
            ->withoutMiddleware([LegacySessionBridge::class, RequireLegacySession::class, RequireLegacyPermission::class])
            ->patchJson("/v2/crm/opportunities/{$opp->id}", ['stage' => 'en_contacto'])
            ->assertOk()
            ->assertJsonPath('data.stage', 'en_contacto');
    }

    public function test_update_rejects_invalid_stage(): void
    {
        $contact = $this->makeContact();
        $opp = CrmOpportunity::query()->create(['contact_id' => $contact->id, 'title' => 'Test', 'stage' => 'nuevo', 'source' => 'whatsapp']);

        $this->actingAs($this->makeUser())
            ->withoutMiddleware([LegacySessionBridge::class, RequireLegacySession::class, RequireLegacyPermission::class])
            ->patchJson("/v2/crm/opportunities/{$opp->id}", ['stage' => 'etapa_falsa'])
            ->assertStatus(422);
    }
}
