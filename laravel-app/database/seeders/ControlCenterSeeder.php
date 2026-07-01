<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ControlCenterSeeder extends Seeder
{
    public function run(): void
    {
        $this->clearTables();

        $now = Carbon::now();

        $plans = [
            ['code' => 'core', 'name' => 'Core', 'monthly_price' => 650, 'user_limit' => 25, 'ai_token_limit' => 250000, 'whatsapp_message_limit' => 4000, 'storage_gb_limit' => 120, 'sla_target' => 99.5, 'support_level' => 'business', 'modules_json' => ['Agenda', 'Pacientes', 'Cirugias', 'Reportes']],
            ['code' => 'growth', 'name' => 'Growth', 'monthly_price' => 950, 'user_limit' => 55, 'ai_token_limit' => 700000, 'whatsapp_message_limit' => 9500, 'storage_gb_limit' => 320, 'sla_target' => 99.7, 'support_level' => 'priority', 'modules_json' => ['Agenda', 'Pacientes', 'Cirugias', 'WhatsApp', 'IA', 'Reportes']],
            ['code' => 'enterprise', 'name' => 'Enterprise', 'monthly_price' => 1450, 'user_limit' => 140, 'ai_token_limit' => 1600000, 'whatsapp_message_limit' => 22000, 'storage_gb_limit' => 900, 'sla_target' => 99.9, 'support_level' => 'internal', 'modules_json' => ['Agenda', 'Pacientes', 'Cirugias', 'WhatsApp', 'IA', 'Billing', 'Reportes']],
        ];

        foreach ($plans as $plan) {
            DB::table('control_center_plans')->insert(array_merge($plan, [
                'currency' => 'USD',
                'is_active' => true,
                'modules_json' => json_encode($plan['modules_json']),
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        $planIds = DB::table('control_center_plans')->pluck('id', 'code');

        $clients = [
            ['slug' => 'cive', 'name' => 'CIVE', 'legal_name' => 'CIVE Plataforma de Convenios', 'domain' => 'cive.medforge.ec', 'admin_url' => 'https://cive.medforge.ec', 'environment' => 'production', 'server_label' => 'srv-quito-01', 'database_name' => 'medforge_cive', 'database_host' => 'db-quito-01', 'city' => 'Quito', 'status' => 'production', 'current_version' => '2026.06.4', 'release_channel' => 'stable', 'color' => '#006b75', 'initials' => 'CI', 'plan' => 'enterprise', 'payment_status' => 'current', 'last_activity_at' => $now->copy()->subMinutes(8)],
            ['slug' => 'alta-vision', 'name' => 'Alta Vision', 'legal_name' => 'Alta Vision Oftalmologia', 'domain' => 'alta.medforge.ec', 'admin_url' => 'https://alta.medforge.ec', 'environment' => 'production', 'server_label' => 'srv-guayaquil-02', 'database_name' => 'medforge_alta', 'database_host' => 'db-guayaquil-02', 'city' => 'Guayaquil', 'status' => 'maintenance', 'current_version' => '2026.06.3', 'release_channel' => 'stable', 'color' => '#355070', 'initials' => 'AV', 'plan' => 'growth', 'payment_status' => 'current', 'last_activity_at' => $now->copy()->subHours(2)],
            ['slug' => 'salud-visual', 'name' => 'Salud Visual', 'legal_name' => 'Salud Visual Ecuador', 'domain' => 'saludvisual.medforge.ec', 'admin_url' => 'https://saludvisual.medforge.ec', 'environment' => 'production', 'server_label' => 'srv-quito-02', 'database_name' => 'medforge_salud_visual', 'database_host' => 'db-quito-02', 'city' => 'Cuenca', 'status' => 'readonly', 'current_version' => '2026.05.9', 'release_channel' => 'stable', 'color' => '#5c677d', 'initials' => 'SV', 'plan' => 'core', 'payment_status' => 'overdue', 'last_activity_at' => $now->copy()->subHours(5)],
            ['slug' => 'clinica-demo', 'name' => 'Clinica Demo', 'legal_name' => 'Clinica Demo MedForge', 'domain' => 'demo.medforge.ec', 'admin_url' => 'https://demo.medforge.ec', 'environment' => 'staging', 'server_label' => 'srv-staging-01', 'database_name' => 'medforge_demo', 'database_host' => 'db-staging-01', 'city' => 'Quito', 'status' => 'production', 'current_version' => '2026.06.4', 'release_channel' => 'preview', 'color' => '#8a5a44', 'initials' => 'CD', 'plan' => 'core', 'payment_status' => 'trial', 'last_activity_at' => $now->copy()->subMinutes(35)],
            ['slug' => 'hospital-quito', 'name' => 'Hospital Quito', 'legal_name' => 'Hospital Quito Norte', 'domain' => 'hospitalquito.medforge.ec', 'admin_url' => 'https://hospitalquito.medforge.ec', 'environment' => 'production', 'server_label' => 'srv-quito-03', 'database_name' => 'medforge_hospital_quito', 'database_host' => 'db-quito-03', 'city' => 'Quito', 'status' => 'suspended', 'current_version' => '2026.04.7', 'release_channel' => 'stable', 'color' => '#7f4f24', 'initials' => 'HQ', 'plan' => 'enterprise', 'payment_status' => 'suspended', 'last_activity_at' => $now->copy()->subDays(2)],
        ];

        foreach ($clients as $client) {
            $planCode = $client['plan'];
            $paymentStatus = $client['payment_status'];
            unset($client['plan']);
            unset($client['payment_status']);

            $clientId = DB::table('control_center_clients')->insertGetId(array_merge($client, [
                'timezone' => 'America/Guayaquil',
                'metadata_json' => json_encode(['source' => 'control-center-mvp-seed']),
                'created_at' => $now,
                'updated_at' => $now,
            ]));

            DB::table('control_center_contracts')->insert([
                'client_id' => $clientId,
                'plan_id' => $planIds[$planCode] ?? null,
                'starts_at' => $now->copy()->subMonths(6)->toDateString(),
                'ends_at' => $now->copy()->addMonths(6)->toDateString(),
                'payment_status' => $paymentStatus,
                'contract_status' => $client['status'] === 'suspended' ? 'suspended' : 'active',
                'billing_contact_json' => json_encode(['name' => 'Administracion ' . $client['name'], 'email' => $client['slug'] . '@example.com']),
                'technical_contact_json' => json_encode(['name' => 'Soporte ' . $client['name'], 'email' => 'tech+' . $client['slug'] . '@medforge.local']),
                'notes' => 'Contrato inicial MVP Control Center',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('control_center_operational_states')->insert([
                'client_id' => $clientId,
                'state' => $client['status'],
                'starts_at' => $now->copy()->subDay(),
                'ends_at' => null,
                'reason' => $this->stateReason($client['status']),
                'customer_message' => null,
                'changed_by_user_id' => null,
                'changed_by_name' => 'Seeder MVP',
                'source' => 'seed',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $features = [
            ['key' => 'ia', 'name' => 'IA clinica', 'description' => 'Resumenes y planes asistidos por IA.', 'module' => 'ai', 'risk_level' => 'medium', 'default_enabled' => true, 'requires_review' => true, 'owner' => 'MedForge Ops'],
            ['key' => 'whatsapp', 'name' => 'WhatsApp operativo', 'description' => 'Bandeja y automatizaciones WhatsApp.', 'module' => 'whatsapp', 'risk_level' => 'medium', 'default_enabled' => true, 'requires_review' => false, 'owner' => 'MedForge Ops'],
            ['key' => 'billing', 'name' => 'Billing avanzado', 'description' => 'Reportes financieros y prefacturacion.', 'module' => 'billing', 'risk_level' => 'high', 'default_enabled' => false, 'requires_review' => true, 'owner' => 'Finance Ops'],
            ['key' => 'reportes-v2', 'name' => 'Reportes v2', 'description' => 'Dashboards ejecutivos por modulo.', 'module' => 'reports', 'risk_level' => 'low', 'default_enabled' => true, 'requires_review' => false, 'owner' => 'Product'],
        ];

        foreach ($features as $feature) {
            DB::table('control_center_features')->insert(array_merge($feature, [
                'environment' => 'production',
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        $featureIds = DB::table('control_center_features')->pluck('id', 'key');
        $clientRows = DB::table('control_center_clients')->get(['id', 'slug']);

        foreach ($clientRows as $client) {
            foreach ($featureIds as $key => $featureId) {
                $enabled = match ($key) {
                    'billing' => in_array($client->slug, ['cive', 'hospital-quito'], true),
                    'ia' => $client->slug !== 'clinica-demo',
                    default => true,
                };

                DB::table('control_center_client_features')->insert([
                    'client_id' => $client->id,
                    'feature_id' => $featureId,
                    'enabled' => $enabled,
                    'environment' => 'production',
                    'overridden_by_user_id' => null,
                    'override_reason' => 'Configuracion inicial MVP',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $services = [
            ['key' => 'app', 'name' => 'Laravel App', 'icon' => 'mdi-server'],
            ['key' => 'db', 'name' => 'Base de datos', 'icon' => 'mdi-database'],
            ['key' => 'queue', 'name' => 'Colas', 'icon' => 'mdi-timeline-clock'],
            ['key' => 'storage', 'name' => 'Storage', 'icon' => 'mdi-folder-network'],
            ['key' => 'whatsapp', 'name' => 'WhatsApp API', 'icon' => 'mdi-whatsapp'],
        ];

        foreach ($services as $service) {
            DB::table('control_center_services')->insert(array_merge($service, [
                'description' => 'Servicio central registrado para seguimiento manual MVP.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        $serviceIds = DB::table('control_center_services')->pluck('id', 'key');
        foreach ($clientRows as $client) {
            foreach ($serviceIds as $key => $serviceId) {
                DB::table('control_center_service_snapshots')->insert([
                    'client_id' => $client->id,
                    'service_id' => $serviceId,
                    'state' => $client->slug === 'hospital-quito' ? 'paused' : ($client->slug === 'salud-visual' && $key === 'whatsapp' ? 'degraded' : 'operational'),
                    'latency_ms' => $key === 'db' ? 28 : 120,
                    'uptime_pct' => $client->slug === 'hospital-quito' ? 0 : 99.82,
                    'message' => 'Dato manual MVP; health check real queda para Fase 2.',
                    'checked_at' => $now->copy()->subMinutes(15),
                    'metadata_json' => json_encode(['source' => 'seed']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $releaseId = DB::table('control_center_releases')->insertGetId([
            'version' => '2026.06.4',
            'channel' => 'stable',
            'title' => 'MVP operacional',
            'notes' => 'Version estable registrada manualmente para Control Center MVP.',
            'released_at' => $now->copy()->subDays(4),
            'status' => 'available',
            'created_by' => 'MedForge Ops',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($clientRows as $client) {
            $clientVersion = DB::table('control_center_clients')->where('id', $client->id)->value('current_version');
            DB::table('control_center_deployments')->insert([
                'client_id' => $client->id,
                'release_id' => $clientVersion === '2026.06.4' ? $releaseId : null,
                'version' => $clientVersion,
                'available_version' => '2026.06.4',
                'channel' => $client->slug === 'clinica-demo' ? 'preview' : 'stable',
                'status' => $clientVersion === '2026.06.4' ? 'installed' : 'update_available',
                'deployed_at' => $now->copy()->subDays(rand(3, 24)),
                'scheduled_at' => null,
                'responsible' => 'MedForge Ops',
                'metadata_json' => json_encode(['source' => 'seed', 'real_deploy' => false]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ($clientRows as $client) {
            foreach ([['ai_tokens', 125000, 'tokens', 18.50], ['whatsapp_messages', 2200, 'messages', 32.00], ['storage', 86, 'gb', 0.00]] as [$metric, $value, $unit, $cost]) {
                DB::table('control_center_usage_metrics')->insert([
                    'client_id' => $client->id,
                    'metric' => $metric,
                    'period_start' => $now->copy()->startOfMonth()->toDateString(),
                    'period_end' => $now->copy()->endOfMonth()->toDateString(),
                    'value' => $value + ($client->id * 37),
                    'unit' => $unit,
                    'cost' => $cost,
                    'metadata_json' => json_encode(['source' => 'manual_seed']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        DB::table('control_center_audit_logs')->insert([
            'client_id' => 1,
            'event_type' => 'seed',
            'action' => 'control_center.seeded',
            'actor_user_id' => null,
            'actor_name' => 'Seeder MVP',
            'target_type' => 'control_center',
            'target_id' => null,
            'before_json' => null,
            'after_json' => json_encode(['clients' => 5]),
            'metadata_json' => json_encode(['source' => 'seed']),
            'ip_address' => null,
            'user_agent' => null,
            'created_at' => $now,
        ]);
    }

    private function clearTables(): void
    {
        foreach ([
            'control_center_audit_logs',
            'control_center_usage_metrics',
            'control_center_deployments',
            'control_center_releases',
            'control_center_service_snapshots',
            'control_center_services',
            'control_center_client_features',
            'control_center_features',
            'control_center_operational_states',
            'control_center_contracts',
            'control_center_plans',
            'control_center_clients',
        ] as $table) {
            DB::table($table)->delete();
        }
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
