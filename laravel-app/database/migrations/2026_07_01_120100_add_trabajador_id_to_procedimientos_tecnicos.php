<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('procedimientos_tecnicos', 'trabajador_id')) {
            Schema::table('procedimientos_tecnicos', function (Blueprint $table): void {
                $table->unsignedInteger('trabajador_id')->nullable()->after('funcion');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('procedimientos_tecnicos', 'trabajador_id')) {
            Schema::table('procedimientos_tecnicos', function (Blueprint $table): void {
                $table->dropColumn('trabajador_id');
            });
        }
    }
};
