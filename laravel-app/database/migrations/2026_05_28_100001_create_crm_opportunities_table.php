<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_opportunities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('contact_id')->index();
            $table->string('title', 255);
            // nuevo | en_contacto | interesado | propuesta_enviada | ganado | perdido
            $table->string('stage', 30)->default('nuevo');
            // whatsapp | solicitud | examen | manual
            $table->string('source', 30)->default('manual');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_type', 255)->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable()->index();
            $table->string('lost_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['stage']);
            $table->index(['source', 'source_id']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_opportunities');
    }
};
