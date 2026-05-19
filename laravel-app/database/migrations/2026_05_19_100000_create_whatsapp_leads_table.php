<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_leads', function (Blueprint $table): void {
            $table->id();

            // Conversación de origen (sin FK para compatibilidad con el engine de MySQL)
            $table->unsignedBigInteger('conversation_id')->index();

            // CRM lead vinculado (creado en paralelo)
            $table->unsignedBigInteger('crm_lead_id')->nullable()->index();

            // Datos del contacto (snapshot al momento de la baja)
            $table->string('wa_number', 30);
            $table->string('display_name', 255)->nullable();
            $table->string('hc_number', 100)->nullable();
            $table->string('cedula', 30)->nullable();
            $table->string('patient_full_name', 255)->nullable();

            // Motivo de la baja
            $table->text('motivo_baja');

            // Estado del lead de re-campaña
            $table->string('status', 30)->default('pendiente');
            // pendiente | contactado | cerrado

            // Agente que generó la baja
            $table->unsignedBigInteger('created_by_user_id')->nullable()->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_leads');
    }
};
