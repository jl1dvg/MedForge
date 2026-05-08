<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_sigcenter_bookings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->nullable()->index();
            $table->string('wa_number', 32)->index();
            $table->string('inbound_message_id', 191)->nullable()->index();
            $table->string('status', 32)->default('created')->index();
            $table->string('patient_hc_number', 64)->nullable()->index();
            $table->string('patient_full_name', 191)->nullable();
            $table->string('sigcenter_agenda_id', 64)->nullable();
            $table->string('trabajador_id', 64)->nullable();
            $table->string('medico_nombre', 191)->nullable();
            $table->string('sede_id', 64)->nullable()->index();
            $table->string('sede_nombre', 191)->nullable();
            $table->string('procedimiento_id', 64)->nullable()->index();
            $table->string('procedimiento_nombre', 191)->nullable();
            $table->timestamp('fecha_inicio')->nullable()->index();
            $table->json('payload')->nullable();
            $table->json('response')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('booked_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_sigcenter_bookings');
    }
};
