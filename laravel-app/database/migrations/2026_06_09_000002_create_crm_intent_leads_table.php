<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_intent_leads', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('contact_id');
            $table->string('source', 30)->default('whatsapp');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_type', 100)->nullable();

            $table->string('motivo', 500)->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();

            $table->enum('status', [
                'nuevo',
                'contactado',
                'calificado',
                'convertido',
                'descartado',
            ])->default('nuevo');

            $table->unsignedBigInteger('opportunity_id')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('discarded_at')->nullable();

            $table->timestamps();

            $table->index('contact_id',                'idx_crm_intent_leads_contact');
            $table->index('status',                    'idx_crm_intent_leads_status');
            $table->index(['source', 'source_id'],     'idx_crm_intent_leads_source');
            $table->index('opportunity_id',            'idx_crm_intent_leads_opp');
            $table->index('created_at',                'idx_crm_intent_leads_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_intent_leads');
    }
};
