<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imagenes_nas_index', function (Blueprint $table): void {
            $table->id();
            $table->string('form_id', 50)->unique();
            $table->string('hc_number', 50)->index();
            $table->boolean('has_files')->default(false)->index();
            $table->unsignedInteger('files_count')->default(0);
            $table->unsignedInteger('image_count')->default(0);
            $table->unsignedInteger('pdf_count')->default(0);
            $table->unsignedBigInteger('total_bytes')->default(0);
            $table->dateTime('latest_file_mtime')->nullable();
            $table->string('sample_file', 255)->nullable();
            $table->string('scan_status', 30)->default('pending')->index();
            $table->text('last_error')->nullable();
            $table->unsignedInteger('scan_duration_ms')->nullable();
            $table->timestamp('last_scanned_at')->nullable()->index();
            $table->timestamps();

            $table->index(['scan_status', 'has_files']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imagenes_nas_index');
    }
};
