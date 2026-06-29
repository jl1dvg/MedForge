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
            $table->string('booking_source', 48)->default('bot_api')->index('woba_booking_source_idx');
            $table->string('observed_booking_key', 191)->unique('woba_observed_booking_key_uniq');
            $table->unsignedBigInteger('booking_id')->nullable()->index('woba_booking_id_idx');
            $table->unsignedBigInteger('form_id')->nullable()->index('woba_form_id_idx');
            // Explicit short index names — MySQL 64-char identifier limit
            $table->unsignedBigInteger('booking_conversation_id')->nullable()->index('woba_booking_conv_id_idx');
            $table->unsignedBigInteger('attributed_conversation_id')->nullable()->index('woba_attributed_conv_id_idx');
            $table->unsignedBigInteger('handoff_id')->nullable()->index('woba_handoff_id_idx');
            $table->unsignedBigInteger('event_id')->nullable()->index('woba_event_id_idx');
            $table->string('event_type', 64)->index('woba_event_type_idx');
            $table->string('attribution_method', 64)->index('woba_attribution_method_idx');
            $table->string('confidence', 24)->index('woba_confidence_idx');
            $table->timestamp('event_at')->nullable()->index('woba_event_at_idx');
            $table->timestamp('booking_at')->nullable()->index('woba_booking_at_idx');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_operational_booking_attributions');
    }
};
