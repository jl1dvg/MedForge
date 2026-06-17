<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_data', function (Blueprint $table): void {
            if (!Schema::hasColumn('patient_data', 'telefono_alt')) {
                $table->string('telefono_alt', 64)->nullable()->after('celular');
            }
            if (!Schema::hasColumn('patient_data', 'medico_tratante_id')) {
                $table->unsignedBigInteger('medico_tratante_id')->nullable()->after('direccion')->index();
            }
            if (!Schema::hasColumn('patient_data', 'sede_principal')) {
                $table->string('sede_principal', 64)->nullable()->after('medico_tratante_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('patient_data', function (Blueprint $table): void {
            if (Schema::hasColumn('patient_data', 'sede_principal')) {
                $table->dropIndex(['sede_principal']);
                $table->dropColumn('sede_principal');
            }
            if (Schema::hasColumn('patient_data', 'medico_tratante_id')) {
                $table->dropIndex(['medico_tratante_id']);
                $table->dropColumn('medico_tratante_id');
            }
            if (Schema::hasColumn('patient_data', 'telefono_alt')) {
                $table->dropColumn('telefono_alt');
            }
        });
    }
};
