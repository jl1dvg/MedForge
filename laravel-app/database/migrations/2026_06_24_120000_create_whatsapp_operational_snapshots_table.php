<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('whatsapp_operational_snapshots')) {
            return;
        }

        Schema::create('whatsapp_operational_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->date('snapshot_date')->unique();
            $table->json('payload');
            $table->unsignedInteger('hot_open_total')->default(0);
            $table->unsignedInteger('hot_open_unassigned')->default(0);
            $table->unsignedInteger('hot_open_assigned')->default(0);
            $table->unsignedInteger('hot_open_booked')->default(0);
            $table->unsignedInteger('hot_needs_template_total')->default(0);
            $table->unsignedInteger('hot_needs_template_booked')->default(0);
            $table->unsignedInteger('rescue_total')->default(0);
            $table->unsignedInteger('rescue_booked')->default(0);
            $table->unsignedInteger('backlog_total')->default(0);
            $table->unsignedInteger('lost_total')->default(0);
            $table->unsignedInteger('rescued_bookings')->default(0);
            $table->unsignedInteger('autoassigned_bookings')->default(0);
            $table->unsignedInteger('reminder_confirmations')->default(0);
            $table->unsignedInteger('reminder_failures')->default(0);
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_operational_snapshots');
    }
};
