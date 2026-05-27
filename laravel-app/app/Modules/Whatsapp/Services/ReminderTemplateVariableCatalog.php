<?php

namespace App\Modules\Whatsapp\Services;

use App\Models\WhatsappMessageTemplate;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Schema;

class ReminderTemplateVariableCatalog
{
    public const SERVICE_MAPPING_KEY = 'whatsapp_reminder_service_template_variable_map';
    public const IMAGING_MAPPING_KEY = 'whatsapp_reminder_imaging_template_variable_map';

    /**
     * @return array<string,array{label:string,options:array<string,string>}>
     */
    public static function optionGroups(): array
    {
        return [
            'Paciente' => [
                'label' => 'Paciente',
                'options' => [
                    'patient.name' => 'Nombre completo',
                    'patient.first_name' => 'Primer nombre',
                    'patient.last_name' => 'Apellidos',
                    'patient.hc_number' => 'HC / identificación',
                    'patient.phone' => 'Teléfono clínico',
                    'patient.wa_number' => 'WhatsApp',
                    'patient.email' => 'Email',
                    'patient.affiliation' => 'Afiliación',
                    'patient.gender' => 'Sexo',
                    'patient.birth_date' => 'Fecha de nacimiento',
                ],
            ],
            'Cita' => [
                'label' => 'Cita',
                'options' => [
                    'appointment.date' => 'Fecha de cita',
                    'appointment.date_iso' => 'Fecha ISO',
                    'appointment.time' => 'Hora de cita',
                    'appointment.datetime' => 'Fecha y hora',
                    'appointment.doctor' => 'Médico tratante',
                    'appointment.procedure' => 'Procedimiento limpio',
                    'appointment.procedure_short' => 'Procedimiento resumido',
                    'appointment.procedure_full' => 'Procedimiento completo original',
                    'appointment.service_type' => 'Tipo de servicio',
                    'appointment.form_id' => 'Form ID',
                    'appointment.status' => 'Estado de agenda',
                    'appointment.source_type' => 'Tipo técnico',
                ],
            ],
            'Sede' => [
                'label' => 'Sede',
                'options' => [
                    'site.name' => 'Nombre de sede',
                    'site.address' => 'Dirección',
                    'site.maps_url' => 'Link Google Maps',
                    'site.phone' => 'Teléfono de sede',
                    'site.contact_center' => 'Contact Center',
                ],
            ],
            'Clínica' => [
                'label' => 'Clínica',
                'options' => [
                    'clinic.name' => 'Nombre comercial',
                    'clinic.short_name' => 'Nombre corto',
                    'clinic.website' => 'Sitio web',
                    'clinic.phone' => 'Teléfono principal',
                ],
            ],
            'Recordatorio' => [
                'label' => 'Recordatorio',
                'options' => [
                    'reminder.window' => 'Ventana',
                    'reminder.type' => 'Tipo de recordatorio',
                    'reminder.group_count' => 'Cantidad agrupada',
                    'fallback.empty' => 'Texto seguro',
                ],
            ],
        ];
    }

    /**
     * @return array<string,array<int,string>>
     */
    public static function recommendedMappings(): array
    {
        return [
            'confirmacion_cita_med_v2' => [
                1 => 'patient.name',
                2 => 'appointment.date',
                3 => 'appointment.time',
                4 => 'appointment.doctor',
            ],
            'recordatorio_cita_pni_imagen_villaclub' => [
                1 => 'patient.name',
                2 => 'appointment.date',
                3 => 'appointment.time',
                4 => 'appointment.doctor',
                5 => 'appointment.procedure',
                6 => 'site.name',
                7 => 'site.maps_url',
            ],
        ];
    }

    /**
     * @return array<string,string>
     */
    public static function sampleValues(): array
    {
        return [
            'patient.name' => 'María Pérez',
            'patient.first_name' => 'María',
            'patient.last_name' => 'Pérez',
            'patient.hc_number' => '0912345678',
            'patient.phone' => '0999999999',
            'patient.wa_number' => '593999999999',
            'patient.email' => 'paciente@ejemplo.com',
            'patient.affiliation' => 'Particular',
            'patient.gender' => 'F',
            'patient.birth_date' => '01/01/1990',
            'appointment.date' => '25/05/2026',
            'appointment.date_iso' => '2026-05-25',
            'appointment.time' => '09:30',
            'appointment.datetime' => '25/05/2026 09:30',
            'appointment.doctor' => 'Pamela Guillén',
            'appointment.procedure' => 'Consulta oftalmológica',
            'appointment.procedure_short' => 'Consulta oftalmológica',
            'appointment.service_type' => 'Servicios oftalmológicos generales',
            'appointment.form_id' => '281193',
            'appointment.status' => 'AGENDADO',
            'appointment.source_type' => 'servicios_oftalmologicos_generales',
            'site.name' => 'Villa Club',
            'site.address' => 'Parroquia satélite La Aurora de Daule, km 12 Av. León Febres-Cordero. Junto a la Piazza Villa Club.',
            'site.maps_url' => 'https://maps.app.goo.gl/i1ryHLC6JUzkefHa6',
            'site.phone' => '043710160',
            'site.contact_center' => '043710160',
            'clinic.name' => 'Clínica Internacional de la Visión del Ecuador',
            'clinic.short_name' => 'CIVE',
            'clinic.website' => 'https://cive.ec/',
            'clinic.phone' => '043710160',
            'reminder.window' => '24h',
            'reminder.type' => 'Servicios oftalmológicos generales',
            'reminder.group_count' => '3',
            'fallback.empty' => 'Por confirmar',
        ];
    }

    /**
     * @return array<string,array{label:string,body:string,variable_count:int}>
     */
    public function templateMetadata(): array
    {
        if (!Schema::hasTable('whatsapp_message_templates') || !Schema::hasTable('whatsapp_template_revisions')) {
            return [];
        }

        try {
            $templates = WhatsappMessageTemplate::query()
                ->with('whatsapp_template_revision')
                ->orderBy('template_code')
                ->get();
        } catch (\Throwable) {
            return [];
        }

        $metadata = [];
        foreach ($templates as $template) {
            $bodyText = (string) ($template->whatsapp_template_revision?->body_text ?? '');
            $metadata[(string) $template->template_code] = [
                'label' => (string) ($template->display_name ?: $template->template_code),
                'body' => mb_substr($bodyText, 0, 500, 'UTF-8'),
                'variable_count' => $this->countTemplateVariables($bodyText),
            ];
        }

        return $metadata;
    }

    public function countTemplateVariables(string $bodyText): int
    {
        if (trim($bodyText) === '') {
            return 0;
        }

        preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $bodyText, $matches);
        $positions = array_map('intval', $matches[1] ?? []);

        return $positions !== [] ? max($positions) : 0;
    }

    /**
     * @param array<string,string> $settings
     * @return array<int,string>
     */
    public function resolveVariables(
        string $sourceType,
        string $templateCode,
        int $expectedCount,
        array $settings,
        array $context
    ): array {
        $mapping = $this->mappingForSource($sourceType, $settings);
        if ($mapping === []) {
            $mapping = self::recommendedMappings()[$templateCode] ?? [];
        }

        if ($mapping === []) {
            return [];
        }

        $count = max($expectedCount, count($mapping));
        $variables = [];
        for ($position = 1; $position <= $count; $position++) {
            $key = trim((string) ($mapping[$position] ?? ''));
            if ($key === '') {
                $variables[] = $this->safeFallback($position, $context);
                continue;
            }

            $variables[] = $this->resolveValue($key, $context);
        }

        return $variables;
    }

    /**
     * @param array<string,string> $settings
     * @return array<int,string>
     */
    private function mappingForSource(string $sourceType, array $settings): array
    {
        $key = $sourceType === 'imagenes' ? self::IMAGING_MAPPING_KEY : self::SERVICE_MAPPING_KEY;
        $raw = trim((string) ($settings[$key] ?? ''));
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $mapping = [];
        foreach ($decoded as $position => $fieldKey) {
            $position = (int) $position;
            $fieldKey = trim((string) $fieldKey);
            if ($position > 0 && $fieldKey !== '') {
                $mapping[$position] = $fieldKey;
            }
        }

        ksort($mapping);

        return $mapping;
    }

    /**
     * @param array<string,mixed> $context
     */
    private function resolveValue(string $key, array $context): string
    {
        $value = data_get($context, $key);
        if ($value instanceof CarbonInterface) {
            return $value->format('d/m/Y H:i');
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : $this->fallbackForKey($key);
    }

    private function fallbackForKey(string $key): string
    {
        return match (true) {
            str_starts_with($key, 'patient.') => 'Paciente',
            $key === 'appointment.doctor' => 'Por confirmar',
            $key === 'appointment.procedure', $key === 'appointment.procedure_short' => 'Atención programada',
            str_starts_with($key, 'site.') => 'Comunícate con nuestro equipo para confirmar la ubicación.',
            $key === 'clinic.name' => 'Clínica Internacional de la Visión del Ecuador',
            default => 'Por confirmar',
        };
    }

    /**
     * @param array<string,mixed> $context
     */
    private function safeFallback(int $position, array $context): string
    {
        return match ($position) {
            1 => trim((string) data_get($context, 'patient.name')) ?: 'Paciente',
            2 => trim((string) data_get($context, 'appointment.date')) ?: 'Por confirmar',
            3 => trim((string) data_get($context, 'appointment.time')) ?: 'Por confirmar',
            4 => trim((string) data_get($context, 'appointment.doctor')) ?: 'Por confirmar',
            5 => trim((string) data_get($context, 'appointment.procedure')) ?: 'Atención programada',
            6 => trim((string) data_get($context, 'site.name')) ?: 'Sede por confirmar',
            7 => trim((string) data_get($context, 'site.maps_url')) ?: 'Comunícate con nuestro equipo para confirmar la ubicación.',
            default => 'Por confirmar',
        };
    }
}
