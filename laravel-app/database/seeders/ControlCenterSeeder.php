<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ControlCenterSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $this->seedPlans($now);
        $this->seedOrganizations($now);
        $this->seedInstances($now);
        $this->seedFeatures($now);
        $this->seedServices($now);
        $this->seedReleases($now);
        $this->seedDeployments($now);
        $this->seedUsage($now);
        $this->seedAudit($now);
    }

    private function seedPlans(Carbon $now): void
    {
        foreach ($this->plans() as $plan) {
            DB::table('control_center_plans')->updateOrInsert(
                ['code' => $plan['code']],
                [
                    'name' => $plan['name'],
                    'monthly_price' => $plan['monthly_price'],
                    'currency' => 'USD',
                    'user_limit' => $plan['user_limit'],
                    'ai_token_limit' => $plan['ai_token_limit'],
                    'whatsapp_message_limit' => $plan['whatsapp_message_limit'],
                    'storage_gb_limit' => $plan['storage_gb_limit'],
                    'sla_target' => $plan['sla_target'],
                    'support_level' => $plan['support_level'],
                    'modules_json' => json_encode($plan['modules_json']),
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    private function seedOrganizations(Carbon $now): void
    {
        foreach ($this->organizations() as $organization) {
            DB::table('control_center_organizations')->updateOrInsert(
                ['slug' => $organization['slug']],
                [
                    'name' => $organization['name'],
                    'legal_name' => $organization['legal_name'],
                    'ruc' => $organization['ruc'],
                    'commercial_name' => $organization['commercial_name'],
                    'city' => $organization['city'],
                    'timezone' => 'America/Guayaquil',
                    'color' => $organization['color'],
                    'initials' => $organization['initials'],
                    'metadata_json' => json_encode(['source' => 'control-center-mvp-seed']),
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    private function seedInstances(Carbon $now): void
    {
        $organizationIds = DB::table('control_center_organizations')->pluck('id', 'slug');
        $planIds = DB::table('control_center_plans')->pluck('id', 'code');

        foreach ($this->instances() as $instance) {
            $existing = DB::table('control_center_instances')->where('slug', $instance['slug'])->first();
            $organizationId = $organizationIds[$instance['organization_slug']] ?? null;
            if ($organizationId === null) {
                continue;
            }

            $instanceData = [
                'organization_id' => $organizationId,
                'name' => $instance['name'],
                'domain' => $instance['domain'],
                'admin_url' => $instance['admin_url'],
                'environment' => $instance['environment'],
                'server_label' => $instance['server_label'],
                'database_name' => $instance['database_name'],
                'database_host' => $instance['database_host'],
                'release_channel' => $instance['release_channel'],
                'metadata_json' => json_encode(['source' => 'control-center-mvp-seed']),
                'updated_at' => $now,
            ];

            if ($existing === null) {
                $instanceData['slug'] = $instance['slug'];
                $instanceData['status'] = $instance['status'];
                $instanceData['current_version'] = $instance['current_version'];
                $instanceData['last_activity_at'] = $instance['last_activity_at'];
                $instanceData['created_at'] = $now;
                DB::table('control_center_instances')->insert($instanceData);
            } else {
                DB::table('control_center_instances')->where('slug', $instance['slug'])->update($instanceData);
            }

            $instanceId = (int) DB::table('control_center_instances')->where('slug', $instance['slug'])->value('id');
            DB::table('control_center_contracts')->updateOrInsert(
                ['organization_id' => $organizationId, 'instance_id' => null],
                [
                    'plan_id' => $planIds[$instance['plan']] ?? null,
                    'starts_at' => $now->copy()->subMonths(6)->toDateString(),
                    'ends_at' => $now->copy()->addMonths(6)->toDateString(),
                    'payment_status' => $instance['payment_status'],
                    'contract_status' => $instance['status'] === 'suspended' ? 'suspended' : 'active',
                    'scope' => 'organization',
                    'billing_contact_json' => json_encode(['name' => 'Administracion ' . $instance['organization_slug'], 'email' => $instance['organization_slug'] . '@example.com']),
                    'technical_contact_json' => json_encode(['name' => 'Soporte ' . $instance['name'], 'email' => 'tech+' . $instance['slug'] . '@medforge.local']),
                    'notes' => 'Contrato/licencia MVP a nivel organizacion; overrides por instancia quedan para Fase 2.',
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            DB::table('control_center_operational_states')->updateOrInsert(
                ['instance_id' => $instanceId, 'source' => 'seed'],
                [
                    'state' => $instance['status'],
                    'starts_at' => $now->copy()->subDay(),
                    'ends_at' => null,
                    'reason' => $this->stateReason($instance['status']),
                    'customer_message' => null,
                    'changed_by_user_id' => null,
                    'changed_by_name' => 'Seeder MVP',
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    private function seedFeatures(Carbon $now): void
    {
        foreach ($this->features() as $feature) {
            DB::table('control_center_features')->updateOrInsert(
                ['key' => $feature['key']],
                [
                    'name' => $feature['name'],
                    'description' => $feature['description'],
                    'module' => $feature['module'],
                    'risk_level' => $feature['risk_level'],
                    'environment' => 'production',
                    'default_enabled' => $feature['default_enabled'],
                    'requires_review' => $feature['requires_review'],
                    'owner' => $feature['owner'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        $featureKeys = array_map(static fn (array $feature): string => $feature['key'], $this->features());
        $featureIds = DB::table('control_center_features')->whereIn('key', $featureKeys)->pluck('id', 'key');
        $instances = DB::table('control_center_instances')->whereIn('slug', $this->seedInstanceSlugs())->get(['id', 'slug']);

        foreach ($instances as $instance) {
            foreach ($featureIds as $key => $featureId) {
                $existing = DB::table('control_center_instance_features')
                    ->where('instance_id', $instance->id)
                    ->where('feature_id', $featureId)
                    ->where('environment', 'production')
                    ->first();

                DB::table('control_center_instance_features')->updateOrInsert(
                    ['instance_id' => $instance->id, 'feature_id' => $featureId, 'environment' => 'production'],
                    [
                        'enabled' => $existing !== null ? (bool) $existing->enabled : $this->initialFeatureEnabled((string) $instance->slug, (string) $key),
                        'overridden_by_user_id' => $existing->overridden_by_user_id ?? null,
                        'override_reason' => $existing->override_reason ?? 'Configuracion inicial MVP',
                        'updated_at' => $now,
                        'created_at' => $existing->created_at ?? $now,
                    ]
                );
            }
        }
    }

    private function seedServices(Carbon $now): void
    {
        foreach ($this->services() as $service) {
            DB::table('control_center_services')->updateOrInsert(
                ['key' => $service['key']],
                [
                    'name' => $service['name'],
                    'description' => 'Servicio central registrado para seguimiento manual MVP.',
                    'icon' => $service['icon'],
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        $serviceKeys = array_map(static fn (array $service): string => $service['key'], $this->services());
        $serviceIds = DB::table('control_center_services')->whereIn('key', $serviceKeys)->pluck('id', 'key');
        $instances = DB::table('control_center_instances')->whereIn('slug', $this->seedInstanceSlugs())->get(['id', 'slug']);

        foreach ($instances as $instance) {
            foreach ($serviceIds as $key => $serviceId) {
                $existing = DB::table('control_center_service_snapshots')
                    ->where('instance_id', $instance->id)
                    ->where('service_id', $serviceId)
                    ->first();

                DB::table('control_center_service_snapshots')->updateOrInsert(
                    ['instance_id' => $instance->id, 'service_id' => $serviceId],
                    [
                        'state' => $existing->state ?? $this->initialServiceState((string) $instance->slug, (string) $key),
                        'latency_ms' => $existing->latency_ms ?? ($key === 'db' ? 28 : 120),
                        'uptime_pct' => $existing->uptime_pct ?? ($instance->slug === 'hospital-quito-suspended' ? 0 : 99.82),
                        'message' => $existing->message ?? 'Dato manual MVP; health check real queda para Fase 2.',
                        'checked_at' => $existing->checked_at ?? $now->copy()->subMinutes(15),
                        'metadata_json' => $existing->metadata_json ?? json_encode(['source' => 'seed']),
                        'updated_at' => $now,
                        'created_at' => $existing->created_at ?? $now,
                    ]
                );
            }
        }
    }

    private function seedReleases(Carbon $now): void
    {
        DB::table('control_center_releases')->updateOrInsert(
            ['version' => '2026.06.4'],
            [
                'channel' => 'stable',
                'title' => 'MVP operacional',
                'notes' => 'Version estable registrada manualmente para Control Center MVP.',
                'released_at' => $now->copy()->subDays(4),
                'status' => 'available',
                'created_by' => 'MedForge Ops',
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    private function seedDeployments(Carbon $now): void
    {
        $releaseId = DB::table('control_center_releases')->where('version', '2026.06.4')->value('id');
        $instances = DB::table('control_center_instances')->whereIn('slug', $this->seedInstanceSlugs())->get(['id', 'slug', 'current_version', 'release_channel']);

        foreach ($instances as $instance) {
            $existing = DB::table('control_center_deployments')
                ->where('instance_id', $instance->id)
                ->where('channel', $instance->release_channel)
                ->first();
            $version = $existing->version ?? $instance->current_version;

            DB::table('control_center_deployments')->updateOrInsert(
                ['instance_id' => $instance->id, 'channel' => $instance->release_channel],
                [
                    'release_id' => $version === '2026.06.4' ? $releaseId : null,
                    'version' => $version,
                    'available_version' => $existing->available_version ?? '2026.06.4',
                    'status' => $existing->status ?? ($version === '2026.06.4' ? 'installed' : 'update_available'),
                    'deployed_at' => $existing->deployed_at ?? $now->copy()->subDays(7 + (int) $instance->id),
                    'scheduled_at' => $existing->scheduled_at ?? null,
                    'responsible' => $existing->responsible ?? 'MedForge Ops',
                    'metadata_json' => $existing->metadata_json ?? json_encode(['source' => 'seed', 'real_deploy' => false]),
                    'updated_at' => $now,
                    'created_at' => $existing->created_at ?? $now,
                ]
            );
        }
    }

    private function seedUsage(Carbon $now): void
    {
        $instances = DB::table('control_center_instances')->whereIn('slug', $this->seedInstanceSlugs())->get(['id', 'organization_id']);
        $periodStart = $now->copy()->startOfMonth()->toDateString();
        $periodEnd = $now->copy()->endOfMonth()->toDateString();

        foreach ($instances as $instance) {
            foreach ([['ai_tokens', 125000, 'tokens', 18.50], ['whatsapp_messages', 2200, 'messages', 32.00], ['storage', 86, 'gb', 0.00]] as [$metric, $value, $unit, $cost]) {
                $existing = DB::table('control_center_usage_metrics')
                    ->where('instance_id', $instance->id)
                    ->where('metric', $metric)
                    ->where('period_start', $periodStart)
                    ->first();

                DB::table('control_center_usage_metrics')->updateOrInsert(
                    ['instance_id' => $instance->id, 'metric' => $metric, 'period_start' => $periodStart],
                    [
                        'organization_id' => $instance->organization_id,
                        'period_end' => $existing->period_end ?? $periodEnd,
                        'value' => $existing->value ?? ($value + ((int) $instance->id * 37)),
                        'unit' => $existing->unit ?? $unit,
                        'cost' => $existing->cost ?? $cost,
                        'metadata_json' => $existing->metadata_json ?? json_encode(['source' => 'manual_seed']),
                        'updated_at' => $now,
                        'created_at' => $existing->created_at ?? $now,
                    ]
                );
            }
        }
    }

    private function seedAudit(Carbon $now): void
    {
        $organizationId = DB::table('control_center_organizations')->where('slug', 'cive')->value('id');
        $instanceId = DB::table('control_center_instances')->where('slug', 'cive-production')->value('id');

        DB::table('control_center_audit_logs')->updateOrInsert(
            ['event_type' => 'seed', 'action' => 'control_center.seeded', 'actor_name' => 'Seeder MVP'],
            [
                'organization_id' => $organizationId,
                'instance_id' => $instanceId,
                'actor_user_id' => null,
                'target_type' => 'control_center',
                'target_id' => null,
                'before_json' => null,
                'after_json' => json_encode(['organizations' => 5, 'instances' => 6]),
                'metadata_json' => json_encode(['source' => 'seed']),
                'ip_address' => null,
                'user_agent' => null,
                'created_at' => $now,
            ]
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function plans(): array
    {
        return [
            ['code' => 'core', 'name' => 'Core', 'monthly_price' => 650, 'user_limit' => 25, 'ai_token_limit' => 250000, 'whatsapp_message_limit' => 4000, 'storage_gb_limit' => 120, 'sla_target' => 99.5, 'support_level' => 'business', 'modules_json' => ['Agenda', 'Pacientes', 'Cirugias', 'Reportes']],
            ['code' => 'growth', 'name' => 'Growth', 'monthly_price' => 950, 'user_limit' => 55, 'ai_token_limit' => 700000, 'whatsapp_message_limit' => 9500, 'storage_gb_limit' => 320, 'sla_target' => 99.7, 'support_level' => 'priority', 'modules_json' => ['Agenda', 'Pacientes', 'Cirugias', 'WhatsApp', 'IA', 'Reportes']],
            ['code' => 'enterprise', 'name' => 'Enterprise', 'monthly_price' => 1450, 'user_limit' => 140, 'ai_token_limit' => 1600000, 'whatsapp_message_limit' => 22000, 'storage_gb_limit' => 900, 'sla_target' => 99.9, 'support_level' => 'internal', 'modules_json' => ['Agenda', 'Pacientes', 'Cirugias', 'WhatsApp', 'IA', 'Billing', 'Reportes']],
        ];
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function organizations(): array
    {
        return [
            ['slug' => 'cive', 'name' => 'CIVE', 'legal_name' => 'CIVE Plataforma de Convenios', 'ruc' => null, 'commercial_name' => 'CIVE', 'city' => 'Quito', 'color' => '#006b75', 'initials' => 'CI'],
            ['slug' => 'alta-vision', 'name' => 'Alta Vision', 'legal_name' => 'Alta Vision Oftalmologia', 'ruc' => null, 'commercial_name' => 'Alta Vision', 'city' => 'Guayaquil', 'color' => '#355070', 'initials' => 'AV'],
            ['slug' => 'salud-visual', 'name' => 'Salud Visual', 'legal_name' => 'Salud Visual Ecuador', 'ruc' => null, 'commercial_name' => 'Salud Visual', 'city' => 'Cuenca', 'color' => '#5c677d', 'initials' => 'SV'],
            ['slug' => 'clinica-demo', 'name' => 'Clinica Demo', 'legal_name' => 'Clinica Demo MedForge', 'ruc' => null, 'commercial_name' => 'Clinica Demo', 'city' => 'Quito', 'color' => '#8a5a44', 'initials' => 'CD'],
            ['slug' => 'hospital-quito', 'name' => 'Hospital Quito', 'legal_name' => 'Hospital Quito Norte', 'ruc' => null, 'commercial_name' => 'Hospital Quito', 'city' => 'Quito', 'color' => '#7f4f24', 'initials' => 'HQ'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function instances(): array
    {
        $now = Carbon::now();

        return [
            ['organization_slug' => 'cive', 'slug' => 'cive-production', 'name' => 'CIVE Produccion', 'domain' => 'cive.medforge.ec', 'admin_url' => 'https://cive.medforge.ec', 'environment' => 'production', 'server_label' => 'srv-quito-01', 'database_name' => 'medforge_cive', 'database_host' => 'db-quito-01', 'status' => 'production', 'current_version' => '2026.06.4', 'release_channel' => 'stable', 'plan' => 'enterprise', 'payment_status' => 'current', 'last_activity_at' => $now->copy()->subMinutes(8)],
            ['organization_slug' => 'cive', 'slug' => 'cive-staging', 'name' => 'CIVE Staging', 'domain' => 'staging-cive.medforge.ec', 'admin_url' => 'https://staging-cive.medforge.ec', 'environment' => 'staging', 'server_label' => 'srv-staging-01', 'database_name' => 'medforge_cive_staging', 'database_host' => 'db-staging-01', 'status' => 'production', 'current_version' => '2026.06.4', 'release_channel' => 'preview', 'plan' => 'enterprise', 'payment_status' => 'current', 'last_activity_at' => $now->copy()->subMinutes(25)],
            ['organization_slug' => 'alta-vision', 'slug' => 'alta-vision-production', 'name' => 'Alta Vision Produccion', 'domain' => 'alta.medforge.ec', 'admin_url' => 'https://alta.medforge.ec', 'environment' => 'production', 'server_label' => 'srv-guayaquil-02', 'database_name' => 'medforge_alta', 'database_host' => 'db-guayaquil-02', 'status' => 'maintenance', 'current_version' => '2026.06.3', 'release_channel' => 'stable', 'plan' => 'growth', 'payment_status' => 'current', 'last_activity_at' => $now->copy()->subHours(2)],
            ['organization_slug' => 'salud-visual', 'slug' => 'salud-visual-production', 'name' => 'Salud Visual Produccion', 'domain' => 'saludvisual.medforge.ec', 'admin_url' => 'https://saludvisual.medforge.ec', 'environment' => 'production', 'server_label' => 'srv-quito-02', 'database_name' => 'medforge_salud_visual', 'database_host' => 'db-quito-02', 'status' => 'readonly', 'current_version' => '2026.05.9', 'release_channel' => 'stable', 'plan' => 'core', 'payment_status' => 'overdue', 'last_activity_at' => $now->copy()->subHours(5)],
            ['organization_slug' => 'clinica-demo', 'slug' => 'clinica-demo-beta', 'name' => 'Clinica Demo Beta', 'domain' => 'beta-demo.medforge.ec', 'admin_url' => 'https://beta-demo.medforge.ec', 'environment' => 'beta', 'server_label' => 'srv-staging-02', 'database_name' => 'medforge_demo_beta', 'database_host' => 'db-staging-02', 'status' => 'production', 'current_version' => '2026.06.4', 'release_channel' => 'beta', 'plan' => 'core', 'payment_status' => 'trial', 'last_activity_at' => $now->copy()->subMinutes(35)],
            ['organization_slug' => 'hospital-quito', 'slug' => 'hospital-quito-suspended', 'name' => 'Hospital Quito Suspendido', 'domain' => 'hospitalquito.medforge.ec', 'admin_url' => 'https://hospitalquito.medforge.ec', 'environment' => 'production', 'server_label' => 'srv-quito-03', 'database_name' => 'medforge_hospital_quito', 'database_host' => 'db-quito-03', 'status' => 'suspended', 'current_version' => '2026.04.7', 'release_channel' => 'stable', 'plan' => 'enterprise', 'payment_status' => 'suspended', 'last_activity_at' => $now->copy()->subDays(2)],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function features(): array
    {
        return [
            ['key' => 'ia', 'name' => 'IA clinica', 'description' => 'Resumenes y planes asistidos por IA.', 'module' => 'ai', 'risk_level' => 'medium', 'default_enabled' => true, 'requires_review' => true, 'owner' => 'MedForge Ops'],
            ['key' => 'whatsapp', 'name' => 'WhatsApp operativo', 'description' => 'Bandeja y automatizaciones WhatsApp.', 'module' => 'whatsapp', 'risk_level' => 'medium', 'default_enabled' => true, 'requires_review' => false, 'owner' => 'MedForge Ops'],
            ['key' => 'billing', 'name' => 'Billing avanzado', 'description' => 'Reportes financieros y prefacturacion.', 'module' => 'billing', 'risk_level' => 'high', 'default_enabled' => false, 'requires_review' => true, 'owner' => 'Finance Ops'],
            ['key' => 'reportes-v2', 'name' => 'Reportes v2', 'description' => 'Dashboards ejecutivos por modulo.', 'module' => 'reports', 'risk_level' => 'low', 'default_enabled' => true, 'requires_review' => false, 'owner' => 'Product'],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function services(): array
    {
        return [
            ['key' => 'app', 'name' => 'Laravel App', 'icon' => 'mdi-server'],
            ['key' => 'db', 'name' => 'Base de datos', 'icon' => 'mdi-database'],
            ['key' => 'queue', 'name' => 'Colas', 'icon' => 'mdi-timeline-clock'],
            ['key' => 'storage', 'name' => 'Storage', 'icon' => 'mdi-folder-network'],
            ['key' => 'whatsapp', 'name' => 'WhatsApp API', 'icon' => 'mdi-whatsapp'],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function seedInstanceSlugs(): array
    {
        return ['cive-production', 'cive-staging', 'alta-vision-production', 'salud-visual-production', 'clinica-demo-beta', 'hospital-quito-suspended'];
    }

    private function initialFeatureEnabled(string $instanceSlug, string $featureKey): bool
    {
        return match ($featureKey) {
            'billing' => in_array($instanceSlug, ['cive-production', 'hospital-quito-suspended'], true),
            'ia' => $instanceSlug !== 'clinica-demo-beta',
            default => true,
        };
    }

    private function initialServiceState(string $instanceSlug, string $serviceKey): string
    {
        if ($instanceSlug === 'hospital-quito-suspended') {
            return 'paused';
        }

        if ($instanceSlug === 'salud-visual-production' && $serviceKey === 'whatsapp') {
            return 'degraded';
        }

        return 'operational';
    }

    private function stateReason(string $state): string
    {
        return match ($state) {
            'maintenance' => 'Ventana operativa programada',
            'readonly' => 'Modo solo lectura por revision administrativa',
            'suspended' => 'Instancia suspendida por decision administrativa',
            default => 'Operacion normal',
        };
    }
}
