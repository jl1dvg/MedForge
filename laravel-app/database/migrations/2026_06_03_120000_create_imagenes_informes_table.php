<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('imagenes_informes')) {
            Schema::create('imagenes_informes', function (Blueprint $table): void {
                $table->id();
                $table->string('form_id', 64)->unique();
                $table->string('hc_number', 64)->nullable()->index();
                $table->string('tipo_examen', 255);
                $table->string('plantilla', 50)->index();
                $table->longText('payload_json');
                $table->unsignedBigInteger('firmado_por')->nullable()->index();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();

                $table->index(['form_id', 'tipo_examen'], 'idx_imagenes_informe_form_tipo');
            });

            return;
        }

        Schema::table('imagenes_informes', function (Blueprint $table): void {
            if (!Schema::hasColumn('imagenes_informes', 'hc_number')) {
                $table->string('hc_number', 64)->nullable()->after('form_id')->index();
            }
            if (!Schema::hasColumn('imagenes_informes', 'tipo_examen')) {
                $table->string('tipo_examen', 255)->after('hc_number');
            }
            if (!Schema::hasColumn('imagenes_informes', 'plantilla')) {
                $table->string('plantilla', 50)->after('tipo_examen')->index();
            }
            if (!Schema::hasColumn('imagenes_informes', 'payload_json')) {
                $table->longText('payload_json')->after('plantilla');
            }
            if (!Schema::hasColumn('imagenes_informes', 'firmado_por')) {
                $table->unsignedBigInteger('firmado_por')->nullable()->after('payload_json')->index();
            }
            if (!Schema::hasColumn('imagenes_informes', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('firmado_por');
            }
            if (!Schema::hasColumn('imagenes_informes', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
            }
            if (!Schema::hasColumn('imagenes_informes', 'created_at')) {
                $table->timestamp('created_at')->nullable()->useCurrent();
            }
            if (!Schema::hasColumn('imagenes_informes', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();
            }
        });
    }

    public function down(): void
    {
        // No-op: this migration may only complete an existing legacy table in production.
    }
};
