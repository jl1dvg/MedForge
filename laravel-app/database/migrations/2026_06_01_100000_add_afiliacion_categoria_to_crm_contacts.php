<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_contacts', function (Blueprint $table): void {
            // publico | privado | particular | fundacional | null (unknown)
            $table->string('afiliacion_categoria', 20)->nullable()->after('source');
            $table->index(['afiliacion_categoria']);
        });
    }

    public function down(): void
    {
        Schema::table('crm_contacts', function (Blueprint $table): void {
            $table->dropIndex(['afiliacion_categoria']);
            $table->dropColumn('afiliacion_categoria');
        });
    }
};
