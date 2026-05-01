<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_proposals') && !Schema::hasColumn('crm_proposals', 'public_hash')) {
            Schema::table('crm_proposals', function (Blueprint $table): void {
                $table->string('public_hash', 64)->nullable()->after('id');
                $table->unique('public_hash', 'idx_crm_proposals_public_hash');
            });
        }

        if (!Schema::hasTable('crm_proposal_activity')) {
            Schema::create('crm_proposal_activity', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('proposal_id');
                $table->string('event', 64);
                $table->unsignedInteger('actor_id')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();
                $table->index('proposal_id', 'idx_proposal_activity_proposal');
                $table->index('event', 'idx_proposal_activity_event');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_proposal_activity');

        if (Schema::hasTable('crm_proposals') && Schema::hasColumn('crm_proposals', 'public_hash')) {
            Schema::table('crm_proposals', function (Blueprint $table): void {
                $table->dropUnique('idx_crm_proposals_public_hash');
                $table->dropColumn('public_hash');
            });
        }
    }
};
