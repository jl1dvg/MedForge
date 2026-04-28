<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('user_feedback_reports')) {
            return;
        }

        Schema::table('user_feedback_reports', function (Blueprint $table): void {
            if (!Schema::hasColumn('user_feedback_reports', 'resolved_by_user_id')) {
                $table->unsignedBigInteger('resolved_by_user_id')->nullable()->after('attachment_size');
            }
            if (!Schema::hasColumn('user_feedback_reports', 'resolved_at')) {
                $table->timestamp('resolved_at')->nullable()->after('resolved_by_user_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('user_feedback_reports')) {
            return;
        }

        Schema::table('user_feedback_reports', function (Blueprint $table): void {
            foreach (['resolved_at', 'resolved_by_user_id'] as $column) {
                if (Schema::hasColumn('user_feedback_reports', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
