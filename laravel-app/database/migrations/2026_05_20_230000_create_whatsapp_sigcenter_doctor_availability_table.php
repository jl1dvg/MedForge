<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'whatsapp_sigcenter_doctor_availability';

    public function up(): void
    {
        Schema::create(self::TABLE, function (Blueprint $table): void {
            $table->id();
            $table->string('trabajador_id', 64)->index();
            $table->string('doctor_nombre', 191);
            $table->string('especialidad', 191)->nullable();
            $table->string('subespecialidad', 191)->index();
            $table->string('sede_id', 64)->index();
            $table->string('sede_nombre', 191);
            $table->date('fecha')->index();
            $table->unsignedInteger('available_slots_count')->default(0);
            $table->time('first_slot_start')->nullable();
            $table->time('last_slot_end')->nullable();
            $table->json('raw_slots')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamp('last_synced_at')->nullable()->index();
            $table->timestamps();

            $table->unique(
                ['trabajador_id', 'subespecialidad', 'sede_id', 'fecha'],
                'wa_sigcenter_doc_availability_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }
};
