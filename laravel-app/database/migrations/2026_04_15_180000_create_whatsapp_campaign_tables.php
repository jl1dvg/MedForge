<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('whatsapp_campaigns')) {
            Schema::create('whatsapp_campaigns', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 160);
                $table->string('status', 32)->default('draft');
                $table->unsignedBigInteger('template_id')->nullable();
                $table->string('template_name', 191)->nullable();
                $table->json('audience_payload')->nullable();
                $table->unsignedInteger('audience_count')->default(0);
                $table->boolean('dry_run')->default(true);
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamp('last_executed_at')->nullable();
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->unsignedBigInteger('updated_by_user_id')->nullable();
                $table->timestamps();

                $table->index(['status', 'updated_at'], 'idx_wa_campaigns_status_updated');
            });
        }

        if (!Schema::hasTable('whatsapp_campaign_deliveries')) {
            Schema::create('whatsapp_campaign_deliveries', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('campaign_id');
                $table->string('wa_number', 32);
                $table->string('contact_name', 191)->nullable();
                $table->string('status', 32)->default('pending');
                $table->string('template_name', 191)->nullable();
                $table->json('payload')->nullable();
                $table->timestamp('executed_at')->nullable();
                $table->text('error_detail')->nullable();
                $table->timestamps();

                $table->index(['campaign_id', 'status'], 'idx_wa_campaign_deliveries_campaign_status');
                $table->index('wa_number', 'idx_wa_campaign_deliveries_number');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_campaign_deliveries');
        Schema::dropIfExists('whatsapp_campaigns');
    }
};
