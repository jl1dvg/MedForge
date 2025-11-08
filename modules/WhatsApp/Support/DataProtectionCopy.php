<?php

namespace Modules\WhatsApp\Support;

use function array_filter;
use function array_map;
use function is_array;
use function is_string;
use function trim;

class DataProtectionCopy
{
    /**
     * @return array<string, mixed>
     */
    public static function defaults(string $brand): array
    {
        $brand = trim($brand) !== '' ? $brand : 'MedForge';

        return [
            'intro_lines' => [
                'Por favor, antes de continuar, ayúdanos con unos datos.',
                'Para continuar con la conversación, debes aceptar nuestros Términos, Condiciones y Aviso de Privacidad: 👉 {{terms_url}}',
                '¿Continuamos?',
            ],
            'consent_prompt' => 'Confirmamos tu identidad y protegemos tus datos personales para brindarte un mejor servicio en ' . $brand . '. ¿Autorizas el uso de tu información para gestionar tus servicios médicos?',
            'consent_retry' => 'Necesitamos tu confirmación para continuar. Usa los botones enviados o responde "sí, autorizo" si estás de acuerdo.',
            'consent_declined' => 'Entendido. No utilizaremos tus datos hasta que lo autorices. Si deseas continuar responde "sí" o comunícate con nuestro equipo.',
            'identifier_request' => 'Escribe tu número de historia clínica 🪪',
            'identifier_retry' => 'No encontramos tu registro con el número de historia clínica proporcionado. Verifícalo y vuelve a intentarlo.',
            'confirmation_check' => '¿Está seguro de que la información ingresada es correcta? ✅',
            'confirmation_review' => 'Por favor, verifica si tu número de historia clínica {{history_number}} está correcto antes de continuar. ¡Gracias por tu atención! 😊',
            'confirmation_menu' => 'Cuando confirmes la información, responde con la opción que necesites o escribe "menu" para ver las alternativas disponibles.',
            'confirmation_recorded' => 'Tu autorización quedó registrada en nuestro sistema. Continuemos con la atención. ✅',
            'buttons' => [
                'accept' => 'Sí, autorizo',
                'decline' => 'No, gracias',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function resolve(string $brand, array $overrides = []): array
    {
        return self::merge(self::defaults($brand), $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function sanitize(array $overrides, string $brand): array
    {
        return self::merge(self::defaults($brand), $overrides);
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private static function merge(array $base, array $overrides): array
    {
        if (isset($overrides['intro_lines']) && is_array($overrides['intro_lines'])) {
            $intro = [];
            foreach ($overrides['intro_lines'] as $line) {
                if (!is_string($line)) {
                    continue;
                }
                $trimmed = trim($line);
                if ($trimmed !== '') {
                    $intro[] = $trimmed;
                }
            }
            if (!empty($intro)) {
                $base['intro_lines'] = $intro;
            }
        }

        foreach ([
            'consent_prompt',
            'consent_retry',
            'consent_declined',
            'identifier_request',
            'identifier_retry',
            'confirmation_check',
            'confirmation_review',
            'confirmation_menu',
            'confirmation_recorded',
        ] as $key) {
            if (isset($overrides[$key]) && is_string($overrides[$key])) {
                $value = trim($overrides[$key]);
                if ($value !== '') {
                    $base[$key] = $value;
                }
            }
        }

        if (isset($overrides['buttons']) && is_array($overrides['buttons'])) {
            $buttons = $base['buttons'];
            foreach (['accept', 'decline'] as $buttonKey) {
                if (!isset($overrides['buttons'][$buttonKey])) {
                    continue;
                }
                $label = $overrides['buttons'][$buttonKey];
                if (!is_string($label)) {
                    continue;
                }
                $trimmed = trim($label);
                if ($trimmed !== '') {
                    $buttons[$buttonKey] = $trimmed;
                }
            }
            $base['buttons'] = $buttons;
        }

        return $base;
    }
}
