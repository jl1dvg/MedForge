<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_feedback_reports')) {
            return;
        }

        Schema::create('user_feedback_reports', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('report_type', 32);
            $table->string('module_key', 191);
            $table->string('module_label', 191);
            $table->text('message');
            $table->string('current_path', 255)->nullable();
            $table->string('page_title', 191)->nullable();
            $table->string('status', 32)->default('nuevo');
            $table->string('attachment_disk', 32)->nullable();
            $table->string('attachment_path', 255)->nullable();
            $table->string('attachment_original_name', 255)->nullable();
            $table->string('attachment_mime_type', 191)->nullable();
            $table->unsignedBigInteger('attachment_size')->nullable();
            $table->longText('metadata_json')->nullable();
            $table->timestamps();

            $table->index('user_id', 'idx_feedback_reports_user');
            $table->index('status', 'idx_feedback_reports_status');
            $table->index('module_key', 'idx_feedback_reports_module');
            $table->index('created_at', 'idx_feedback_reports_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_feedback_reports');
    }
};
