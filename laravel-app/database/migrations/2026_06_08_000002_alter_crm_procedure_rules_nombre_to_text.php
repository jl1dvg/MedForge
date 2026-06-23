<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_procedure_rules', function (Blueprint $table): void {
            $table->text('nombre')->change();
        });
    }

    public function down(): void
    {
        Schema::table('crm_procedure_rules', function (Blueprint $table): void {
            $table->string('nombre', 200)->change();
        });
    }
};
