<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_activities', function (Blueprint $table): void {
            // source_id + source_type: link to the clinical record (consulta_examenes, solicitud_procedimiento, etc.)
            $table->unsignedBigInteger('source_id')->nullable()->after('user_id');
            $table->string('source_type', 100)->nullable()->after('source_id');
        });
    }

    public function down(): void
    {
        Schema::table('crm_activities', function (Blueprint $table): void {
            $table->dropColumn(['source_id', 'source_type']);
        });
    }
};
