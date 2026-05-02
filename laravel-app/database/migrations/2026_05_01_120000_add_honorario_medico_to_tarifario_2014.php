<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('tarifario_2014', 'honorario_medico')) {
            Schema::table('tarifario_2014', function (Blueprint $table): void {
                $table->decimal('honorario_medico', 12, 4)->nullable()->after('valor_facturar_nivel3');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tarifario_2014', 'honorario_medico')) {
            Schema::table('tarifario_2014', function (Blueprint $table): void {
                $table->dropColumn('honorario_medico');
            });
        }
    }
};
