<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('whatsapp_operational_booking_attributions')) {
            return;
        }

        Schema::create('whatsapp_operational_booking_attributions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('booking_id')->unique();
            $table->unsignedBigInteger('booking_conversation_id')->nullable()->index();
            $table->unsignedBigInteger('attributed_conversation_id')->nullable()->index();
            $table->unsignedBigInteger('handoff_id')->nullable()->index();
            $table->unsignedBigInteger('event_id')->nullable()->index();
            $table->string('event_type', 64)->index();
            $table->string('attribution_method', 64)->index();
            $table->string('confidence', 24)->index();
            $table->timestamp('event_at')->nullable()->index();
            $table->timestamp('booking_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_operational_booking_attributions');
    }
};
