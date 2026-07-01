<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('control_center_clients', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('ruc')->nullable();
            $table->string('domain')->nullable()->unique();
            $table->string('admin_url')->nullable();
            $table->string('environment')->default('production')->index();
            $table->string('server_label')->nullable();
            $table->string('database_name')->nullable();
            $table->string('database_host')->nullable();
            $table->string('city')->nullable();
            $table->string('timezone')->default('America/Guayaquil');
            $table->string('status')->default('production')->index();
            $table->string('current_version')->nullable();
            $table->string('release_channel')->default('stable');
            $table->string('color', 24)->nullable();
            $table->string('initials', 12)->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();
        });

        Schema::create('control_center_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->decimal('monthly_price', 12, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('user_limit')->nullable();
            $table->unsignedBigInteger('ai_token_limit')->nullable();
            $table->unsignedInteger('whatsapp_message_limit')->nullable();
            $table->unsignedInteger('storage_gb_limit')->nullable();
            $table->decimal('sla_target', 5, 2)->nullable();
            $table->string('support_level')->nullable();
            $table->json('modules_json')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('control_center_contracts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained('control_center_clients')->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('control_center_plans')->nullOnDelete();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->string('payment_status')->default('current')->index();
            $table->string('contract_status')->default('active')->index();
            $table->json('billing_contact_json')->nullable();
            $table->json('technical_contact_json')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['client_id', 'contract_status']);
        });

        Schema::create('control_center_operational_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained('control_center_clients')->cascadeOnDelete();
            $table->string('state')->index();
            $table->timestamp('starts_at')->nullable()->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->text('reason')->nullable();
            $table->text('customer_message')->nullable();
            $table->unsignedBigInteger('changed_by_user_id')->nullable()->index();
            $table->string('changed_by_name')->nullable();
            $table->string('source')->default('manual')->index();
            $table->timestamps();
            $table->index(['client_id', 'state']);
        });

        Schema::create('control_center_features', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('module')->nullable()->index();
            $table->string('risk_level')->default('low');
            $table->string('environment')->default('production')->index();
            $table->boolean('default_enabled')->default(false);
            $table->boolean('requires_review')->default(false);
            $table->string('owner')->nullable();
            $table->timestamps();
        });

        Schema::create('control_center_client_features', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained('control_center_clients')->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained('control_center_features')->cascadeOnDelete();
            $table->boolean('enabled')->default(false)->index();
            $table->string('environment')->default('production')->index();
            $table->unsignedBigInteger('overridden_by_user_id')->nullable()->index();
            $table->text('override_reason')->nullable();
            $table->timestamps();
            $table->unique(['client_id', 'feature_id', 'environment'], 'cc_client_features_unique');
        });

        Schema::create('control_center_services', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('control_center_service_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained('control_center_clients')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('control_center_services')->cascadeOnDelete();
            $table->string('state')->default('operational')->index();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->decimal('uptime_pct', 6, 3)->nullable();
            $table->text('message')->nullable();
            $table->timestamp('checked_at')->nullable()->index();
            $table->json('metadata_json')->nullable();
            $table->timestamps();
            $table->index(['client_id', 'service_id']);
        });

        Schema::create('control_center_releases', function (Blueprint $table): void {
            $table->id();
            $table->string('version')->unique();
            $table->string('channel')->default('stable')->index();
            $table->string('title')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('released_at')->nullable()->index();
            $table->string('status')->default('available')->index();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('control_center_deployments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained('control_center_clients')->cascadeOnDelete();
            $table->foreignId('release_id')->nullable()->constrained('control_center_releases')->nullOnDelete();
            $table->string('version');
            $table->string('available_version')->nullable();
            $table->string('channel')->default('stable')->index();
            $table->string('status')->default('installed')->index();
            $table->timestamp('deployed_at')->nullable()->index();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('responsible')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();
            $table->index(['client_id', 'status']);
        });

        Schema::create('control_center_usage_metrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained('control_center_clients')->cascadeOnDelete();
            $table->string('metric')->index();
            $table->date('period_start')->index();
            $table->date('period_end')->nullable();
            $table->decimal('value', 16, 4);
            $table->string('unit');
            $table->decimal('cost', 12, 2)->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();
            $table->index(['client_id', 'metric', 'period_start'], 'cc_usage_client_metric_period_idx');
        });

        Schema::create('control_center_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained('control_center_clients')->cascadeOnDelete();
            $table->string('event_type')->index();
            $table->string('action')->index();
            $table->unsignedBigInteger('actor_user_id')->nullable()->index();
            $table->string('actor_name')->nullable();
            $table->string('target_type')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->longText('before_json')->nullable();
            $table->longText('after_json')->nullable();
            $table->json('metadata_json')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['client_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('control_center_audit_logs');
        Schema::dropIfExists('control_center_usage_metrics');
        Schema::dropIfExists('control_center_deployments');
        Schema::dropIfExists('control_center_releases');
        Schema::dropIfExists('control_center_service_snapshots');
        Schema::dropIfExists('control_center_services');
        Schema::dropIfExists('control_center_client_features');
        Schema::dropIfExists('control_center_features');
        Schema::dropIfExists('control_center_operational_states');
        Schema::dropIfExists('control_center_contracts');
        Schema::dropIfExists('control_center_plans');
        Schema::dropIfExists('control_center_clients');
    }
};
