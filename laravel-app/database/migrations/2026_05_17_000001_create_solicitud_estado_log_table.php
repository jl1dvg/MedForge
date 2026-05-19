<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitud_estado_log', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('solicitud_id')->index();
            $table->string('estado_anterior', 64)->nullable();
            $table->string('estado_nuevo', 64);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('nota')->nullable();
            $table->string('origen', 32)->default('manual')
                ->comment('manual | sigcenter | sla_job | reminder_job');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_estado_log');
    }
};
