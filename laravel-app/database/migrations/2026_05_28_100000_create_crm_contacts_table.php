<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_contacts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('patient_id')->nullable()->index();
            $table->string('name', 255);
            $table->string('phone', 30);
            $table->string('email', 255)->nullable();
            $table->string('cedula', 30)->nullable();
            // provisional | identified | linked
            $table->string('resolution', 20)->default('provisional');
            // whatsapp | solicitud | examen | manual
            $table->string('source', 30)->default('manual');
            $table->timestamps();

            $table->unique(['cedula'], 'uq_crm_contacts_cedula');
            $table->index(['phone']);
            $table->index(['resolution']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_contacts');
    }
};
