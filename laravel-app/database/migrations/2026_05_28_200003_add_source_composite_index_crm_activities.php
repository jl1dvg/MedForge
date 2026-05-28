<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_activities', function (Blueprint $table): void {
            $table->index(['source_type', 'source_id'], 'crm_activities_source_type_source_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('crm_activities', function (Blueprint $table): void {
            $table->dropIndex('crm_activities_source_type_source_id_index');
        });
    }
};
