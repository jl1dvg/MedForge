<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('procedimientos_codigos', 'codigo')) {
            Schema::table('procedimientos_codigos', function (Blueprint $table): void {
                $table->string('codigo', 20)->nullable()->after('procedimiento_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('procedimientos_codigos', 'codigo')) {
            Schema::table('procedimientos_codigos', function (Blueprint $table): void {
                $table->dropColumn('codigo');
            });
        }
    }
};
