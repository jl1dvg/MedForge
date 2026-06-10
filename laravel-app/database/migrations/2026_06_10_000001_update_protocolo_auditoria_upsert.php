<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Eliminar duplicados antes de agregar índice único
        // Mantener el registro más reciente (mayor id) por combinación protocolo_id + usuario_id
        DB::statement('
            DELETE a FROM protocolo_auditoria a
            INNER JOIN protocolo_auditoria b
                ON a.protocolo_id = b.protocolo_id
               AND a.usuario_id   = b.usuario_id
               AND a.id < b.id
        ');

        Schema::table('protocolo_auditoria', function (Blueprint $table) {
            if (!Schema::hasColumn('protocolo_auditoria', 'actualizado_en')) {
                $table->dateTime('actualizado_en')->nullable()->after('creado_en');
            }

            $table->unique(['protocolo_id', 'usuario_id'], 'protocolo_auditoria_protocolo_usuario_unique');
        });
    }

    public function down(): void
    {
        Schema::table('protocolo_auditoria', function (Blueprint $table) {
            $table->dropUnique('protocolo_auditoria_protocolo_usuario_unique');

            if (Schema::hasColumn('protocolo_auditoria', 'actualizado_en')) {
                $table->dropColumn('actualizado_en');
            }
        });
    }
};
