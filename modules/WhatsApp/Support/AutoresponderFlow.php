<?php

namespace Modules\WhatsApp\Support;

use function trim;

class AutoresponderFlow
{
    /**
     * @return array<int, string>
     */
    public static function menuKeywords(): array
    {
        return ['menu', 'inicio', 'hola', 'buen dia', 'buenos dias', 'buenas tardes', 'buenas noches', 'start'];
    }

    /**
     * @return array<int, string>
     */
    public static function informationKeywords(): array
    {
        return ['1', 'opcion 1', 'informacion', 'informacion general', 'obtener informacion', 'informacion cive'];
    }

    /**
     * @return array<int, string>
     */
    public static function scheduleKeywords(): array
    {
        return [
            '2',
            'opcion 2',
            'horarios',
            'horario',
            'horario atencion',
            'horarios atencion',
            'horarios de atencion',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function locationKeywords(): array
    {
        return ['3', 'opcion 3', 'ubicacion', 'ubicaciones', 'sedes', 'direccion', 'direcciones'];
    }

    /**
     * @return array<int, string>
     */
    public static function welcomeMessages(string $brand): array
    {
        $brand = trim($brand) !== '' ? $brand : 'MedForge';

        return [
            "¡Hola! Soy Dr. Ojito, el asistente virtual de {$brand} 👁️",
            "Te puedo ayudar con las siguientes solicitudes:\n1. Obtener información\n2. Horarios de atención\n3. Ubicaciones\nResponde con el número o escribe la opción que necesites.",
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function informationMessages(): array
    {
        return [
            'Obtener Información',
            "Selecciona la información que deseas conocer:\n• Procedimientos oftalmológicos disponibles.\n• Servicios complementarios como óptica y exámenes especializados.\n• Seguros y convenios con los que trabajamos.\n\nEscribe 'horarios' para conocer los horarios de atención o 'menu' para volver al inicio.",
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function scheduleMessages(): array
    {
        return [
            "Horarios de atención 🕖\nVilla Club: Lunes a Viernes 09h00 - 18h00, Sábados 09h00 - 13h00.\nCeibos: Lunes a Viernes 09h00 - 18h00, Sábados 09h00 - 13h00.\n\nSi necesitas otra información responde 'menu'.",
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function locationMessages(): array
    {
        return [
            "Nuestras sedes 📍\nVilla Club: Km. 12.5 Av. León Febres Cordero, Villa Club Etapa Flora.\nCeibos: C.C. Ceibos Center, piso 2, consultorio 210.\n\nResponde 'horarios' para conocer los horarios o 'menu' para otras opciones.",
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function fallbackMessages(): array
    {
        return [
            "No logré identificar tu solicitud. Responde 'menu' para ver las opciones disponibles o 'horarios' para conocer nuestros horarios de atención.",
        ];
    }

    public static function overview(string $brand): array
    {
        $brand = trim($brand) !== '' ? $brand : 'MedForge';

        return [
            'entry' => [
                'title' => 'Mensaje de bienvenida',
                'description' => 'Primer contacto que recibe toda persona que escribe al canal.',
                'keywords' => self::menuKeywords(),
                'messages' => self::welcomeMessages($brand),
            ],
            'options' => [
                [
                    'id' => 'information',
                    'title' => 'Opción 1 · Obtener información',
                    'keywords' => self::informationKeywords(),
                    'messages' => self::informationMessages(),
                    'followup' => "Sugiere escribir 'horarios' para continuar o 'menu' para volver al inicio.",
                ],
                [
                    'id' => 'schedule',
                    'title' => 'Opción 2 · Horarios de atención',
                    'keywords' => self::scheduleKeywords(),
                    'messages' => self::scheduleMessages(),
                    'followup' => "Indica que el usuario puede responder 'menu' para otras opciones.",
                ],
                [
                    'id' => 'locations',
                    'title' => 'Opción 3 · Ubicaciones',
                    'keywords' => self::locationKeywords(),
                    'messages' => self::locationMessages(),
                    'followup' => "Recomienda escribir 'horarios' o 'menu' según la necesidad.",
                ],
            ],
            'fallback' => [
                'title' => 'Sin coincidencia',
                'description' => 'Mensaje que se envía cuando ninguna palabra clave coincide.',
                'messages' => self::fallbackMessages(),
            ],
            'meta' => [
                'brand' => $brand,
                'keywordLegend' => self::keywordLegend(),
            ],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private static function keywordLegend(): array
    {
        return [
            'Bienvenida' => self::menuKeywords(),
            'Opción 1' => self::informationKeywords(),
            'Opción 2' => self::scheduleKeywords(),
            'Opción 3' => self::locationKeywords(),
        ];
    }
}
