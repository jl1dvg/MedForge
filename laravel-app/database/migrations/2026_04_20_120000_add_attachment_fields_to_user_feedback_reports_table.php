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
            if (!Schema::hasColumn('user_feedback_reports', 'attachment_disk')) {
                $table->string('attachment_disk', 32)->nullable()->after('status');
            }
            if (!Schema::hasColumn('user_feedback_reports', 'attachment_path')) {
                $table->string('attachment_path', 255)->nullable()->after('attachment_disk');
            }
            if (!Schema::hasColumn('user_feedback_reports', 'attachment_original_name')) {
                $table->string('attachment_original_name', 255)->nullable()->after('attachment_path');
            }
            if (!Schema::hasColumn('user_feedback_reports', 'attachment_mime_type')) {
                $table->string('attachment_mime_type', 191)->nullable()->after('attachment_original_name');
            }
            if (!Schema::hasColumn('user_feedback_reports', 'attachment_size')) {
                $table->unsignedBigInteger('attachment_size')->nullable()->after('attachment_mime_type');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('user_feedback_reports')) {
            return;
        }

        Schema::table('user_feedback_reports', function (Blueprint $table): void {
            foreach ([
                'attachment_size',
                'attachment_mime_type',
                'attachment_original_name',
                'attachment_path',
                'attachment_disk',
            ] as $column) {
                if (Schema::hasColumn('user_feedback_reports', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
