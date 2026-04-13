<?php

namespace App\Modules\Whatsapp\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WhatsappConfigService
{
    /**
     * @return array{
     *     enabled: bool,
     *     phone_number_id: string,
     *     business_account_id: string,
     *     access_token: string,
     *     api_version: string,
     *     default_country_code: string,
     *     webhook_verify_token: string,
     *     chat_require_assignment_to_reply: bool,
     *     brand: string,
     *     template_languages: string
     * }
     */
    public function get(): array
    {
        $defaults = [
            'enabled' => false,
            'phone_number_id' => '',
            'business_account_id' => '',
            'access_token' => '',
            'api_version' => 'v17.0',
            'default_country_code' => '',
            'webhook_verify_token' => '',
            'chat_require_assignment_to_reply' => true,
            'brand' => 'MedForge',
            'template_languages' => '',
        ];

        $map = $this->loadSettings([
            'whatsapp_cloud_enabled',
            'whatsapp_cloud_phone_number_id',
            'whatsapp_cloud_business_account_id',
            'whatsapp_cloud_access_token',
            'whatsapp_cloud_api_version',
            'whatsapp_cloud_default_country_code',
            'whatsapp_webhook_verify_token',
            'whatsapp_chat_require_assignment_to_reply',
            'whatsapp_template_languages',
            'companyname',
        ]);

        $defaults['enabled'] = (($map['whatsapp_cloud_enabled'] ?? '0') === '1');
        $defaults['phone_number_id'] = trim((string) ($map['whatsapp_cloud_phone_number_id'] ?? ''));
        $defaults['business_account_id'] = trim((string) ($map['whatsapp_cloud_business_account_id'] ?? ''));
        $defaults['access_token'] = trim((string) ($map['whatsapp_cloud_access_token'] ?? ''));

        $apiVersion = trim((string) ($map['whatsapp_cloud_api_version'] ?? ''));
        if ($apiVersion !== '') {
            $defaults['api_version'] = $apiVersion;
        }

        $countryCode = preg_replace('/\D+/', '', (string) ($map['whatsapp_cloud_default_country_code'] ?? ''));
        $defaults['default_country_code'] = $countryCode ?: '';
        $defaults['webhook_verify_token'] = trim((string) ($map['whatsapp_webhook_verify_token'] ?? ''));
        $defaults['chat_require_assignment_to_reply'] = ($map['whatsapp_chat_require_assignment_to_reply'] ?? '1') === '1';
        $defaults['brand'] = trim((string) ($map['companyname'] ?? '')) !== ''
            ? trim((string) $map['companyname'])
            : 'MedForge';
        $defaults['template_languages'] = trim((string) ($map['whatsapp_template_languages'] ?? ''));

        return $defaults;
    }

    /**
     * @param array<int, string> $keys
     * @return array<string, string|null>
     */
    private function loadSettings(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        foreach (['app_settings', 'settings'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            if (!Schema::hasColumns($table, ['name', 'value'])) {
                continue;
            }

            /** @var array<string, string|null> $result */
            $result = DB::table($table)
                ->whereIn('name', $keys)
                ->pluck('value', 'name')
                ->map(fn ($value) => $value === null ? null : (string) $value)
                ->all();

            if ($result !== []) {
                return $result;
            }
        }

        return [];
    }
}
