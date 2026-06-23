<?php

namespace App\Modules\Pacientes\Services;

class TipoAfiliacionResolver
{
    public function classify(?string $afiliacion): string
    {
        $value = $this->normalize((string) $afiliacion);

        if ($value === '') {
            return 'otros';
        }

        if (str_contains($value, 'fundacion') || str_contains($value, 'fundaciones')) {
            return 'fundacional';
        }

        if (str_contains($value, 'particular')) {
            return 'particular';
        }

        foreach (['msp', 'iess', 'issfa', 'isspol', 'seguro general', 'jubilado', 'campesino'] as $needle) {
            if (str_contains($value, $needle)) {
                return 'publico';
            }
        }

        foreach (['ecuas', 'bmi', 'humana', 'confiamed', 'mediken', 'salud', 'consulmed'] as $needle) {
            if (str_contains($value, $needle)) {
                return 'privado';
            }
        }

        return 'otros';
    }

    public function metadata(string $tipo): array
    {
        return match ($tipo) {
            'publico' => ['id' => 'publico', 'label' => 'Publico', 'tone' => 'publico', 'color' => 'blue'],
            'privado' => ['id' => 'privado', 'label' => 'Privado', 'tone' => 'privado', 'color' => 'purple'],
            'particular' => ['id' => 'particular', 'label' => 'Particular', 'tone' => 'particular', 'color' => 'gray'],
            'fundacional' => ['id' => 'fundacional', 'label' => 'Fundacional', 'tone' => 'fundacional', 'color' => 'green'],
            default => ['id' => 'otros', 'label' => 'Otros', 'tone' => 'otros', 'color' => 'amber'],
        };
    }

    private function normalize(string $value): string
    {
        $value = strtolower(trim($value));
        $value = strtr($value, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ñ' => 'n',
        ]);
        $value = preg_replace('/\s+/', ' ', $value) ?: '';

        return $value;
    }
}
