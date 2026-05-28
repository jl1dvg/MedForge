<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('solicitud_procedimiento', 'crm_opportunity_id')) {
            Schema::table('solicitud_procedimiento', function (Blueprint $table): void {
                $table->unsignedBigInteger('crm_opportunity_id')->nullable()->index()->after('id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('solicitud_procedimiento', 'crm_opportunity_id')) {
            Schema::table('solicitud_procedimiento', function (Blueprint $table): void {
                $table->dropColumn('crm_opportunity_id');
            });
        }
    }
};
