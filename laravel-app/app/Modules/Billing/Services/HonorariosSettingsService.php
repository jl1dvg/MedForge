<?php

namespace App\Modules\Billing\Services;

use Illuminate\Support\Facades\DB;

class HonorariosSettingsService
{
    private const SETTING_NAME = 'billing_honorarios_rules';

    /**
     * @return array<int, array{tipo_atencion:string,categoria_afiliacion:string,modo:string,porcentaje:float|null}>
     */
    public function rules(): array
    {
        $stored = $this->storedRules();

        return $stored !== [] ? $stored : $this->defaultRules();
    }

    /**
     * @return array<int, array{tipo_atencion:string,categoria_afiliacion:string,modo:string,porcentaje:float|null}>
     */
    public function defaultRules(): array
    {
        return [
            ['tipo_atencion' => 'servicios_oftalmologicos', 'categoria_afiliacion' => '*', 'modo' => 'porcentaje', 'porcentaje' => 50.0],
            ['tipo_atencion' => 'cirugias', 'categoria_afiliacion' => 'publico', 'modo' => 'porcentaje', 'porcentaje' => 30.0],
            ['tipo_atencion' => 'cirugias', 'categoria_afiliacion' => 'privado', 'modo' => 'honorario_codigo', 'porcentaje' => null],
            ['tipo_atencion' => 'cirugias', 'categoria_afiliacion' => 'particular', 'modo' => 'honorario_codigo', 'porcentaje' => null],
            ['tipo_atencion' => 'cirugias', 'categoria_afiliacion' => 'fundacional', 'modo' => 'honorario_codigo', 'porcentaje' => null],
            ['tipo_atencion' => 'cirugias', 'categoria_afiliacion' => 'otros', 'modo' => 'honorario_codigo', 'porcentaje' => null],
            ['tipo_atencion' => 'pni', 'categoria_afiliacion' => 'publico', 'modo' => 'porcentaje', 'porcentaje' => 30.0],
            ['tipo_atencion' => 'pni', 'categoria_afiliacion' => 'privado', 'modo' => 'honorario_codigo', 'porcentaje' => null],
            ['tipo_atencion' => 'pni', 'categoria_afiliacion' => 'particular', 'modo' => 'honorario_codigo', 'porcentaje' => null],
            ['tipo_atencion' => 'pni', 'categoria_afiliacion' => 'fundacional', 'modo' => 'honorario_codigo', 'porcentaje' => null],
            ['tipo_atencion' => 'pni', 'categoria_afiliacion' => 'otros', 'modo' => 'honorario_codigo', 'porcentaje' => null],
            ['tipo_atencion' => 'imagenes', 'categoria_afiliacion' => '*', 'modo' => 'porcentaje', 'porcentaje' => 30.0],
            ['tipo_atencion' => '*', 'categoria_afiliacion' => '*', 'modo' => 'porcentaje', 'porcentaje' => 30.0],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rules
     */
    public function saveRules(array $rules): void
    {
        $normalized = $this->normalizeRules($rules);
        if ($normalized === []) {
            $normalized = $this->defaultRules();
        }

        DB::table('app_settings')->updateOrInsert(
            ['name' => self::SETTING_NAME],
            [
                'category' => 'billing',
                'value' => json_encode($normalized, JSON_UNESCAPED_UNICODE),
                'type' => 'json',
                'autoload' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    /**
     * @return array<int, array{tipo_atencion:string,categoria_afiliacion:string,modo:string,porcentaje:float|null}>
     */
    private function storedRules(): array
    {
        try {
            $value = DB::table('app_settings')
                ->where('name', self::SETTING_NAME)
                ->value('value');
        } catch (\Throwable) {
            return [];
        }

        $decoded = json_decode((string) $value, true);
        if (!is_array($decoded)) {
            return [];
        }

        return $this->normalizeRules($decoded);
    }

    /**
     * @param array<int, array<string, mixed>> $rules
     * @return array<int, array{tipo_atencion:string,categoria_afiliacion:string,modo:string,porcentaje:float|null}>
     */
    private function normalizeRules(array $rules): array
    {
        $normalized = [];
        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $tipo = $this->normalizeKey((string) ($rule['tipo_atencion'] ?? ''));
            $categoria = $this->normalizeKey((string) ($rule['categoria_afiliacion'] ?? ''));
            $modo = $this->normalizeModo((string) ($rule['modo'] ?? ''));
            $porcentaje = $rule['porcentaje'] ?? null;

            if ($tipo === '' || $categoria === '' || $modo === '') {
                continue;
            }
            if ($modo === 'porcentaje' && !is_numeric($porcentaje)) {
                continue;
            }

            $normalized[] = [
                'tipo_atencion' => $tipo,
                'categoria_afiliacion' => $categoria,
                'modo' => $modo,
                'porcentaje' => $modo === 'porcentaje' ? (float) $porcentaje : null,
            ];
        }

        return $normalized;
    }

    private function normalizeModo(string $modo): string
    {
        $modo = $this->normalizeKey($modo);

        return in_array($modo, ['porcentaje', 'honorario_codigo'], true) ? $modo : '';
    }

    private function normalizeKey(string $value): string
    {
        $value = trim($value);
        if ($value === '*') {
            return '*';
        }

        $value = strtolower(strtr($value, [
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U',
            'Ñ' => 'N',
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ñ' => 'n',
        ]));
        $value = str_replace([' ', '-'], '_', $value);
        $value = preg_replace('/_+/', '_', $value) ?? $value;

        return trim($value, '_');
    }
}
