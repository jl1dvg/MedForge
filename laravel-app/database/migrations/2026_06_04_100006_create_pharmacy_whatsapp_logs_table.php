<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_whatsapp_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pharmacy_patient_id')->nullable()->constrained('pharmacy_patients')->nullOnDelete();
            $table->foreignId('pharmacy_prescription_id')->nullable()->constrained('pharmacy_prescriptions')->nullOnDelete();
            $table->enum('tipo', ['receta_recibida', 'lista_para_entrega', 'recordatorio_recompra', 'entrega_en_camino', 'otro']);
            $table->text('mensaje');
            $table->string('numero_destino');
            $table->enum('estado', ['simulado', 'enviado', 'fallido'])->default('simulado');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_whatsapp_logs');
    }
};
