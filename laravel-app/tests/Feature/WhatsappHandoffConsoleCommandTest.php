<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsappHandoffConsoleCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('whatsapp_handoff_events');
        Schema::dropIfExists('whatsapp_handoffs');
        Schema::dropIfExists('whatsapp_conversations');

        Schema::create('whatsapp_conversations', function (Blueprint $table): void {
            $table->id();
            $table->string('wa_number', 32)->unique();
            $table->string('display_name', 191)->nullable();
            $table->boolean('needs_human')->default(false);
            $table->text('handoff_notes')->nullable();
            $table->unsignedBigInteger('handoff_role_id')->nullable();
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamps();
        });

        Schema::create('whatsapp_handoffs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->string('wa_number', 32);
            $table->string('status', 24)->default('queued');
            $table->string('priority', 24)->default('normal');
            $table->unsignedBigInteger('handoff_role_id')->nullable();
            $table->unsignedBigInteger('assigned_agent_id')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('assigned_until')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_handoff_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('handoff_id');
            $table->string('event_type', 64);
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function test_it_previews_expired_handoffs_from_console(): void
    {
        \DB::table('whatsapp_conversations')->insert([
            'id' => 801,
            'wa_number' => '593999111801',
            'display_name' => 'Paciente Preview',
            'needs_human' => 1,
            'assigned_user_id' => 10,
            'assigned_at' => now()->subDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_handoffs')->insert([
            'id' => 1801,
            'conversation_id' => 801,
            'wa_number' => '593999111801',
            'status' => 'assigned',
            'priority' => 'normal',
            'assigned_agent_id' => 10,
            'assigned_at' => now()->subDay(),
            'assigned_until' => now()->subMinute(),
            'queued_at' => now()->subDay(),
            'last_activity_at' => now()->subMinute(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Artisan::call('whatsapp:handoff-requeue-expired', ['--dry-run' => true]);

        $this->assertStringContainsString('1801', Artisan::output());
        $this->assertDatabaseHas('whatsapp_handoffs', [
            'id' => 1801,
            'status' => 'assigned',
        ]);
    }

    public function test_it_requeues_expired_handoffs_from_console(): void
    {
        \DB::table('whatsapp_conversations')->insert([
            'id' => 802,
            'wa_number' => '593999111802',
            'display_name' => 'Paciente Execute',
            'needs_human' => 1,
            'assigned_user_id' => 10,
            'assigned_at' => now()->subDay(),
            'handoff_role_id' => 9,
            'handoff_notes' => 'Seguimiento',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \DB::table('whatsapp_handoffs')->insert([
            'id' => 1802,
            'conversation_id' => 802,
            'wa_number' => '593999111802',
            'status' => 'assigned',
            'priority' => 'normal',
            'handoff_role_id' => 9,
            'assigned_agent_id' => 10,
            'assigned_at' => now()->subDay(),
            'assigned_until' => now()->subMinute(),
            'queued_at' => now()->subDay(),
            'last_activity_at' => now()->subMinute(),
            'notes' => 'Seguimiento',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Artisan::call('whatsapp:handoff-requeue-expired');

        $this->assertStringContainsString('1802', Artisan::output());
        $this->assertDatabaseHas('whatsapp_handoffs', [
            'id' => 1802,
            'status' => 'queued',
            'assigned_agent_id' => null,
        ]);
        $this->assertDatabaseHas('whatsapp_conversations', [
            'id' => 802,
            'assigned_user_id' => null,
            'handoff_role_id' => 9,
            'needs_human' => 1,
        ]);
    }
}
