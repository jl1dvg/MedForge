<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_activities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('opportunity_id')->index();
            // nota | llamada | cambio_etapa | email
            $table->string('type', 30)->default('nota');
            $table->text('description');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['opportunity_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_activities');
    }
};
