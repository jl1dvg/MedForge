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
        $this->seedClients($now);
        $this->seedFeatures($now);
        $this->seedServices($now);
        $this->seedReleases($now);
        $this->seedDeployments($now);
        $this->seedUsage($now);
        $this->seedAudit($now);
    }

    private function seedPlans(Carbon $now): void
    {
        $plans = [
            ['code' => 'core', 'name' => 'Core', 'monthly_price' => 650, 'user_limit' => 25, 'ai_token_limit' => 250000, 'whatsapp_message_limit' => 4000, 'storage_gb_limit' => 120, 'sla_target' => 99.5, 'support_level' => 'business', 'modules_json' => ['Agenda', 'Pacientes', 'Cirugias', 'Reportes']],
            ['code' => 'growth', 'name' => 'Growth', 'monthly_price' => 950, 'user_limit' => 55, 'ai_token_limit' => 700000, 'whatsapp_message_limit' => 9500, 'storage_gb_limit' => 320, 'sla_target' => 99.7, 'support_level' => 'priority', 'modules_json' => ['Agenda', 'Pacientes', 'Cirugias', 'WhatsApp', 'IA', 'Reportes']],
            ['code' => 'enterprise', 'name' => 'Enterprise', 'monthly_price' => 1450, 'user_limit' => 140, 'ai_token_limit' => 1600000, 'whatsapp_message_limit' => 22000, 'storage_gb_limit' => 900, 'sla_target' => 99.9, 'support_level' => 'internal', 'modules_json' => ['Agenda', 'Pacientes', 'Cirugias', 'WhatsApp', 'IA', 'Billing', 'Reportes']],
        ];

        foreach ($plans as $plan) {
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

    private function seedClients(Carbon $now): void
    {
        $planIds = DB::table('control_center_plans')->pluck('id', 'code');
        $clients = [
            ['slug' => 'cive', 'name' => 'CIVE', 'legal_name' => 'CIVE Plataforma de Convenios', 'domain' => 'cive.medforge.ec', 'admin_url' => 'https://cive.medforge.ec', 'environment' => 'production', 'server_label' => 'srv-quito-01', 'database_name' => 'medforge_cive', 'database_host' => 'db-quito-01', 'city' => 'Quito', 'status' => 'production', 'current_version' => '2026.06.4', 'release_channel' => 'stable', 'color' => '#006b75', 'initials' => 'CI', 'plan' => 'enterprise', 'payment_status' => 'current', 'last_activity_at' => $now->copy()->subMinutes(8)],
            ['slug' => 'alta-vision', 'name' => 'Alta Vision', 'legal_name' => 'Alta Vision Oftalmologia', 'domain' => 'alta.medforge.ec', 'admin_url' => 'https://alta.medforge.ec', 'environment' => 'production', 'server_label' => 'srv-guayaquil-02', 'database_name' => 'medforge_alta', 'database_host' => 'db-guayaquil-02', 'city' => 'Guayaquil', 'status' => 'maintenance', 'current_version' => '2026.06.3', 'release_channel' => 'stable', 'color' => '#355070', 'initials' => 'AV', 'plan' => 'growth', 'payment_status' => 'current', 'last_activity_at' => $now->copy()->subHours(2)],
            ['slug' => 'salud-visual', 'name' => 'Salud Visual', 'legal_name' => 'Salud Visual Ecuador', 'domain' => 'saludvisual.medforge.ec', 'admin_url' => 'https://saludvisual.medforge.ec', 'environment' => 'production', 'server_label' => 'srv-quito-02', 'database_name' => 'medforge_salud_visual', 'database_host' => 'db-quito-02', 'city' => 'Cuenca', 'status' => 'readonly', 'current_version' => '2026.05.9', 'release_channel' => 'stable', 'color' => '#5c677d', 'initials' => 'SV', 'plan' => 'core', 'payment_status' => 'overdue', 'last_activity_at' => $now->copy()->subHours(5)],
            ['slug' => 'clinica-demo', 'name' => 'Clinica Demo', 'legal_name' => 'Clinica Demo MedForge', 'domain' => 'demo.medforge.ec', 'admin_url' => 'https://demo.medforge.ec', 'environment' => 'staging', 'server_label' => 'srv-staging-01', 'database_name' => 'medforge_demo', 'database_host' => 'db-staging-01', 'city' => 'Quito', 'status' => 'production', 'current_version' => '2026.06.4', 'release_channel' => 'preview', 'color' => '#8a5a44', 'initials' => 'CD', 'plan' => 'core', 'payment_status' => 'trial', 'last_activity_at' => $now->copy()->subMinutes(35)],
            ['slug' => 'hospital-quito', 'name' => 'Hospital Quito', 'legal_name' => 'Hospital Quito Norte', 'domain' => 'hospitalquito.medforge.ec', 'admin_url' => 'https://hospitalquito.medforge.ec', 'environment' => 'production', 'server_label' => 'srv-quito-03', 'database_name' => 'medforge_hospital_quito', 'database_host' => 'db-quito-03', 'city' => 'Quito', 'status' => 'suspended', 'current_version' => '2026.04.7', 'release_channel' => 'stable', 'color' => '#7f4f24', 'initials' => 'HQ', 'plan' => 'enterprise', 'payment_status' => 'suspended', 'last_activity_at' => $now->copy()->subDays(2)],
        ];

        foreach ($clients as $client) {
            $existing = DB::table('control_center_clients')->where('slug', $client['slug'])->first();
            $clientData = [
                'name' => $client['name'],
                'legal_name' => $client['legal_name'],
                'domain' => $client['domain'],
                'admin_url' => $client['admin_url'],
                'environment' => $client['environment'],
                'server_label' => $client['server_label'],
                'database_name' => $client['database_name'],
                'database_host' => $client['database_host'],
                'city' => $client['city'],
                'timezone' => 'America/Guayaquil',
                'release_channel' => $client['release_channel'],
                'color' => $client['color'],
                'initials' => $client['initials'],
                'metadata_json' => json_encode(['source' => 'control-center-mvp-seed']),
                'updated_at' => $now,
            ];

            if ($existing === null) {
                $clientData['slug'] = $client['slug'];
                $clientData['status'] = $client['status'];
                $clientData['current_version'] = $client['current_version'];
                $clientData['last_activity_at'] = $client['last_activity_at'];
                $clientData['created_at'] = $now;
                DB::table('control_center_clients')->insert($clientData);
            } else {
                DB::table('control_center_clients')->where('slug', $client['slug'])->update($clientData);
            }

            $clientId = (int) DB::table('control_center_clients')->where('slug', $client['slug'])->value('id');
            DB::table('control_center_contracts')->updateOrInsert(
                ['client_id' => $clientId],
                [
                    'plan_id' => $planIds[$client['plan']] ?? null,
                    'starts_at' => $now->copy()->subMonths(6)->toDateString(),
                    'ends_at' => $now->copy()->addMonths(6)->toDateString(),
                    'payment_status' => $client['payment_status'],
                    'contract_status' => $client['status'] === 'suspended' ? 'suspended' : 'active',
                    'billing_contact_json' => json_encode(['name' => 'Administracion ' . $client['name'], 'email' => $client['slug'] . '@example.com']),
                    'technical_contact_json' => json_encode(['name' => 'Soporte ' . $client['name'], 'email' => 'tech+' . $client['slug'] . '@medforge.local']),
                    'notes' => 'Contrato inicial MVP Control Center',
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            DB::table('control_center_operational_states')->updateOrInsert(
                ['client_id' => $clientId, 'source' => 'seed'],
                [
                    'state' => $client['status'],
                    'starts_at' => $now->copy()->subDay(),
                    'ends_at' => null,
                    'reason' => $this->stateReason($client['status']),
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
        $features = [
            ['key' => 'ia', 'name' => 'IA clinica', 'description' => 'Resumenes y planes asistidos por IA.', 'module' => 'ai', 'risk_level' => 'medium', 'default_enabled' => true, 'requires_review' => true, 'owner' => 'MedForge Ops'],
            ['key' => 'whatsapp', 'name' => 'WhatsApp operativo', 'description' => 'Bandeja y automatizaciones WhatsApp.', 'module' => 'whatsapp', 'risk_level' => 'medium', 'default_enabled' => true, 'requires_review' => false, 'owner' => 'MedForge Ops'],
            ['key' => 'billing', 'name' => 'Billing avanzado', 'description' => 'Reportes financieros y prefacturacion.', 'module' => 'billing', 'risk_level' => 'high', 'default_enabled' => false, 'requires_review' => true, 'owner' => 'Finance Ops'],
            ['key' => 'reportes-v2', 'name' => 'Reportes v2', 'description' => 'Dashboards ejecutivos por modulo.', 'module' => 'reports', 'risk_level' => 'low', 'default_enabled' => true, 'requires_review' => false, 'owner' => 'Product'],
        ];

        foreach ($features as $feature) {
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

        $featureIds = DB::table('control_center_features')->pluck('id', 'key');
        $clientRows = DB::table('control_center_clients')->whereIn('slug', $this->seedClientSlugs())->get(['id', 'slug']);

        foreach ($clientRows as $client) {
            foreach ($featureIds as $key => $featureId) {
                $existing = DB::table('control_center_client_features')
                    ->where('client_id', $client->id)
                    ->where('feature_id', $featureId)
                    ->where('environment', 'production')
                    ->first();
                $enabled = $existing !== null ? (bool) $existing->enabled : $this->initialFeatureEnabled((string) $client->slug, (string) $key);

                DB::table('control_center_client_features')->updateOrInsert(
                    ['client_id' => $client->id, 'feature_id' => $featureId, 'environment' => 'production'],
                    [
                        'enabled' => $enabled,
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
        $services = [
            ['key' => 'app', 'name' => 'Laravel App', 'icon' => 'mdi-server'],
            ['key' => 'db', 'name' => 'Base de datos', 'icon' => 'mdi-database'],
            ['key' => 'queue', 'name' => 'Colas', 'icon' => 'mdi-timeline-clock'],
            ['key' => 'storage', 'name' => 'Storage', 'icon' => 'mdi-folder-network'],
            ['key' => 'whatsapp', 'name' => 'WhatsApp API', 'icon' => 'mdi-whatsapp'],
        ];

        foreach ($services as $service) {
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

        $serviceIds = DB::table('control_center_services')->pluck('id', 'key');
        $clientRows = DB::table('control_center_clients')->whereIn('slug', $this->seedClientSlugs())->get(['id', 'slug']);

        foreach ($clientRows as $client) {
            foreach ($serviceIds as $key => $serviceId) {
                $existing = DB::table('control_center_service_snapshots')
                    ->where('client_id', $client->id)
                    ->where('service_id', $serviceId)
                    ->first();

                DB::table('control_center_service_snapshots')->updateOrInsert(
                    ['client_id' => $client->id, 'service_id' => $serviceId],
                    [
                        'state' => $existing->state ?? $this->initialServiceState((string) $client->slug, (string) $key),
                        'latency_ms' => $existing->latency_ms ?? ($key === 'db' ? 28 : 120),
                        'uptime_pct' => $existing->uptime_pct ?? ($client->slug === 'hospital-quito' ? 0 : 99.82),
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
        $clientRows = DB::table('control_center_clients')->whereIn('slug', $this->seedClientSlugs())->get(['id', 'slug', 'current_version']);

        foreach ($clientRows as $client) {
            $existing = DB::table('control_center_deployments')
                ->where('client_id', $client->id)
                ->where('channel', $client->slug === 'clinica-demo' ? 'preview' : 'stable')
                ->first();
            $version = $existing->version ?? $client->current_version;

            DB::table('control_center_deployments')->updateOrInsert(
                ['client_id' => $client->id, 'channel' => $client->slug === 'clinica-demo' ? 'preview' : 'stable'],
                [
                    'release_id' => $version === '2026.06.4' ? $releaseId : null,
                    'version' => $version,
                    'available_version' => $existing->available_version ?? '2026.06.4',
                    'status' => $existing->status ?? ($version === '2026.06.4' ? 'installed' : 'update_available'),
                    'deployed_at' => $existing->deployed_at ?? $now->copy()->subDays(7 + (int) $client->id),
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
        $clientRows = DB::table('control_center_clients')->whereIn('slug', $this->seedClientSlugs())->get(['id']);
        $periodStart = $now->copy()->startOfMonth()->toDateString();
        $periodEnd = $now->copy()->endOfMonth()->toDateString();

        foreach ($clientRows as $client) {
            foreach ([['ai_tokens', 125000, 'tokens', 18.50], ['whatsapp_messages', 2200, 'messages', 32.00], ['storage', 86, 'gb', 0.00]] as [$metric, $value, $unit, $cost]) {
                $existing = DB::table('control_center_usage_metrics')
                    ->where('client_id', $client->id)
                    ->where('metric', $metric)
                    ->where('period_start', $periodStart)
                    ->first();

                DB::table('control_center_usage_metrics')->updateOrInsert(
                    ['client_id' => $client->id, 'metric' => $metric, 'period_start' => $periodStart],
                    [
                        'period_end' => $existing->period_end ?? $periodEnd,
                        'value' => $existing->value ?? ($value + ((int) $client->id * 37)),
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
        $clientId = DB::table('control_center_clients')->where('slug', 'cive')->value('id');

        DB::table('control_center_audit_logs')->updateOrInsert(
            ['event_type' => 'seed', 'action' => 'control_center.seeded', 'actor_name' => 'Seeder MVP'],
            [
                'client_id' => $clientId,
                'actor_user_id' => null,
                'target_type' => 'control_center',
                'target_id' => null,
                'before_json' => null,
                'after_json' => json_encode(['clients' => 5]),
                'metadata_json' => json_encode(['source' => 'seed']),
                'ip_address' => null,
                'user_agent' => null,
                'created_at' => $now,
            ]
        );
    }

    /**
     * @return array<int, string>
     */
    private function seedClientSlugs(): array
    {
        return ['cive', 'alta-vision', 'salud-visual', 'clinica-demo', 'hospital-quito'];
    }

    private function initialFeatureEnabled(string $clientSlug, string $featureKey): bool
    {
        return match ($featureKey) {
            'billing' => in_array($clientSlug, ['cive', 'hospital-quito'], true),
            'ia' => $clientSlug !== 'clinica-demo',
            default => true,
        };
    }

    private function initialServiceState(string $clientSlug, string $serviceKey): string
    {
        if ($clientSlug === 'hospital-quito') {
            return 'paused';
        }

        if ($clientSlug === 'salud-visual' && $serviceKey === 'whatsapp') {
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
