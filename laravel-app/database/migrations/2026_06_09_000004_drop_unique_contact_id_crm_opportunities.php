<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_opportunities', function (Blueprint $table): void {
            $table->dropUnique('crm_opportunities_contact_id_unique');
            $table->index('contact_id', 'idx_crm_opp_contact_id');
        });
    }

    /**
     * Rollback re-adds the unique constraint.
     * Will fail if duplicate contact_ids exist — run crm:consolidate-opportunities first.
     */
    public function down(): void
    {
        Schema::table('crm_opportunities', function (Blueprint $table): void {
            $table->dropIndex('idx_crm_opp_contact_id');
            $table->unique('contact_id', 'crm_opportunities_contact_id_unique');
        });
    }
};
