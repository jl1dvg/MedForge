<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_ai_agent_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('wa_number', 32)->nullable();
            $table->string('scenario_id', 191)->nullable();
            $table->unsignedInteger('action_index')->default(0);
            $table->longText('input_text')->nullable();
            $table->json('filters')->nullable();
            $table->json('matched_documents')->nullable();
            $table->longText('response_text')->nullable();
            $table->string('classification', 64)->nullable();
            $table->decimal('confidence', 5, 2)->default(0);
            $table->boolean('suggested_handoff')->default(false);
            $table->json('context_before')->nullable();
            $table->json('context_after')->nullable();
            $table->string('source', 32)->default('preview');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_ai_agent_runs');
    }
};
