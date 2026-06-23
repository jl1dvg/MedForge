<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('imagenes_file_claims')) {
            return;
        }

        Schema::create('imagenes_file_claims', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('procedimiento_id')->nullable()->index();
            $table->string('form_id', 64)->index();
            $table->string('hc_number', 64)->index();
            $table->string('paciente', 255)->nullable();
            $table->string('cedula', 64)->nullable();
            $table->string('tipo_examen', 255)->nullable();
            $table->string('ojo', 64)->nullable();
            $table->string('afiliacion', 120)->nullable();
            $table->string('sede', 120)->nullable();
            $table->date('fecha_examen')->nullable();
            $table->string('status', 32)->default('abierto')->index();
            $table->text('message')->nullable();
            $table->text('last_check_error')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable()->index();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['form_id', 'hc_number', 'status'], 'idx_img_claim_case_status');
            $table->index(['status', 'updated_at'], 'idx_img_claim_status_updated');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imagenes_file_claims');
    }
};
