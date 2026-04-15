<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_flow_shadow_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 32)->default('webhook');
            $table->string('wa_number', 32)->nullable()->index();
            $table->unsignedBigInteger('conversation_id')->nullable()->index();
            $table->string('inbound_message_id', 191)->nullable()->index();
            $table->longText('message_text')->nullable();
            $table->boolean('same_match')->default(false)->index();
            $table->boolean('same_scenario')->default(false)->index();
            $table->boolean('same_handoff')->default(false)->index();
            $table->boolean('same_action_types')->default(false)->index();
            $table->json('input_payload')->nullable();
            $table->json('parity_payload')->nullable();
            $table->json('laravel_payload')->nullable();
            $table->json('legacy_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_flow_shadow_runs');
    }
};
