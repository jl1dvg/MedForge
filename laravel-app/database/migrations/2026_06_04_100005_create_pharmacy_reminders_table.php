<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacy_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pharmacy_prescription_id')->constrained('pharmacy_prescriptions')->cascadeOnDelete();
            $table->foreignId('pharmacy_patient_id')->constrained('pharmacy_patients')->cascadeOnDelete();
            $table->foreignId('pharmacy_prescription_item_id')->nullable()->constrained('pharmacy_prescription_items')->nullOnDelete();
            $table->string('descripcion');
            $table->date('fecha_recordatorio');
            $table->enum('estado', ['pendiente', 'enviado', 'completado', 'cancelado'])->default('pendiente');
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_reminders');
    }
};
