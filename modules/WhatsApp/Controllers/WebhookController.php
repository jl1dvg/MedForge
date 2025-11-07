<?php

namespace Modules\WhatsApp\Controllers;

use Core\BaseController;
use Modules\WhatsApp\Services\Messenger;
use PDO;
use function file_get_contents;
use function hash_equals;
use function is_array;
use function json_decode;
use function ltrim;
use function mb_strtolower;
use function preg_replace;
use function strlen;
use function str_contains;
use function strtr;
use function trim;

class WebhookController extends BaseController
{
    private Messenger $messenger;
    private string $verifyToken;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->messenger = new Messenger($pdo);
        $this->verifyToken = (string) ($_ENV['WHATSAPP_WEBHOOK_VERIFY_TOKEN']
            ?? $_ENV['WHATSAPP_VERIFY_TOKEN']
            ?? getenv('WHATSAPP_WEBHOOK_VERIFY_TOKEN')
            ?? getenv('WHATSAPP_VERIFY_TOKEN')
            ?? 'medforge-whatsapp');
    }

    public function handle(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if (strtoupper($method) === 'GET') {
            $this->handleVerification();

            return;
        }

        $this->handleIncoming();
    }

    private function handleVerification(): void
    {
        $mode = $_GET['hub_mode'] ?? $_GET['hub.mode'] ?? null;
        $token = $_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? null;
        $challenge = $_GET['hub_challenge'] ?? $_GET['hub.challenge'] ?? '';

        if ($mode === 'subscribe' && $token !== null && hash_equals($this->verifyToken, (string) $token)) {
            if (!headers_sent()) {
                http_response_code(200);
                header('Content-Type: text/plain; charset=UTF-8');
            }

            echo (string) $challenge;

            return;
        }

        if (!headers_sent()) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=UTF-8');
        }

        echo 'Verification token mismatch';
    }

    private function handleIncoming(): void
    {
        $raw = file_get_contents('php://input');
        $payload = json_decode($raw ?: '[]', true);

        if (!is_array($payload)) {
            $this->json(['ok' => false, 'error' => 'Invalid payload'], 400);

            return;
        }

        foreach ($this->extractMessages($payload) as $message) {
            $this->respondToMessage($message);
        }

        $this->json(['ok' => true]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractMessages(array $payload): array
    {
        $messages = [];

        foreach (($payload['entry'] ?? []) as $entry) {
            foreach (($entry['changes'] ?? []) as $change) {
                if (!isset($change['value']) || !is_array($change['value'])) {
                    continue;
                }

                $value = $change['value'];
                $metadata = is_array($value['metadata'] ?? null) ? $value['metadata'] : [];

                foreach (($value['messages'] ?? []) as $message) {
                    if (!is_array($message)) {
                        continue;
                    }

                    $message['metadata'] = $metadata;
                    $messages[] = $message;
                }
            }
        }

        return $messages;
    }

    /**
     * @param array<string, mixed> $message
     */
    private function respondToMessage(array $message): void
    {
        $sender = isset($message['from']) ? ('+' . ltrim((string) $message['from'], '+')) : null;
        if ($sender === null || $sender === '+') {
            return;
        }

        $text = $this->extractText($message);
        if ($text === null) {
            return;
        }

        $keyword = $this->normalize($text);

        if ($keyword === '') {
            return;
        }

        if ($this->matchesKeyword($keyword, self::menuKeywords(), true)) {
            $this->sendWelcomeMenu($sender);

            return;
        }

        if ($this->matchesKeyword($keyword, self::informationKeywords(), true)) {
            $this->sendInformation($sender);

            return;
        }

        if ($this->matchesKeyword($keyword, self::scheduleKeywords(), true)) {
            $this->sendSchedule($sender);

            return;
        }

        if ($this->matchesKeyword($keyword, self::locationKeywords(), true)) {
            $this->sendLocations($sender);

            return;
        }

        $this->sendFallback($sender);
    }

    /**
     * @param array<string, mixed> $message
     */
    private function extractText(array $message): ?string
    {
        $type = $message['type'] ?? '';

        if ($type === 'text' && isset($message['text']['body'])) {
            return (string) $message['text']['body'];
        }

        if ($type === 'interactive' && isset($message['interactive']) && is_array($message['interactive'])) {
            $interactive = $message['interactive'];
            $interactiveType = $interactive['type'] ?? '';

            if ($interactiveType === 'button_reply') {
                return (string) ($interactive['button_reply']['id'] ?? $interactive['button_reply']['title'] ?? '');
            }

            if ($interactiveType === 'list_reply') {
                return (string) ($interactive['list_reply']['id'] ?? $interactive['list_reply']['title'] ?? '');
            }
        }

        if ($type === 'button' && isset($message['button']['payload'])) {
            return (string) $message['button']['payload'];
        }

        return null;
    }

    private static function menuKeywords(): array
    {
        return ['menu', 'inicio', 'hola', 'buen dia', 'buenos dias', 'buenas tardes', 'buenas noches', 'start'];
    }

    private static function informationKeywords(): array
    {
        return ['1', 'opcion 1', 'informacion', 'informacion general', 'obtener informacion', 'informacion cive'];
    }

    private static function scheduleKeywords(): array
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

    private static function locationKeywords(): array
    {
        return ['3', 'opcion 3', 'ubicacion', 'ubicaciones', 'sedes', 'direccion', 'direcciones'];
    }

    /**
     * @param array<int, string> $keywords
     */
    private function matchesKeyword(string $text, array $keywords, bool $allowPartial = false): bool
    {
        foreach ($keywords as $keyword) {
            if ($text === $keyword) {
                return true;
            }

            if ($allowPartial && strlen($keyword) > 1 && str_contains($text, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function sendWelcomeMenu(string $recipient): void
    {
        $brand = $this->messenger->getBrandName();

        $messages = [
            "¡Hola! Soy Dr. Ojito, el asistente virtual de {$brand} 👁️", 
            "Te puedo ayudar con las siguientes solicitudes:\n1. Obtener información\n2. Horarios de atención\n3. Ubicaciones\n\nResponde con el número o escribe la opción que necesites."
        ];

        foreach ($messages as $message) {
            $this->messenger->sendTextMessage($recipient, $message);
        }
    }

    private function sendInformation(string $recipient): void
    {
        $messages = [
            'Obtener Información',
            "Selecciona la información que deseas conocer:\n• Procedimientos oftalmológicos disponibles.\n• Servicios complementarios como óptica y exámenes especializados.\n• Seguros y convenios con los que trabajamos.\n\nEscribe 'horarios' para conocer los horarios de atención o 'menu' para volver al inicio."
        ];

        foreach ($messages as $message) {
            $this->messenger->sendTextMessage($recipient, $message);
        }
    }

    private function sendSchedule(string $recipient): void
    {
        $message = "Horarios de atención 🕖\nVilla Club: Lunes a Viernes 09h00 - 18h00, Sábados 09h00 - 13h00.\nCeibos: Lunes a Viernes 09h00 - 18h00, Sábados 09h00 - 13h00.\n\nSi necesitas otra información responde 'menu'.";
        $this->messenger->sendTextMessage($recipient, $message);
    }

    private function sendLocations(string $recipient): void
    {
        $message = "Nuestras sedes 📍\nVilla Club: Km. 12.5 Av. León Febres Cordero, Villa Club Etapa Flora.\nCeibos: C.C. Ceibos Center, piso 2, consultorio 210.\n\nResponde 'horarios' para conocer los horarios o 'menu' para otras opciones.";
        $this->messenger->sendTextMessage($recipient, $message);
    }

    private function sendFallback(string $recipient): void
    {
        $message = "No logré identificar tu solicitud. Responde 'menu' para ver las opciones disponibles o 'horarios' para conocer nuestros horarios de atención.";
        $this->messenger->sendTextMessage($recipient, $message);
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = strtr($text, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);
        $text = preg_replace('/[^a-z0-9 ]+/u', '', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return $text;
    }
}
