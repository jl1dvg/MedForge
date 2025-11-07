<?php

namespace app\services\ai\Providers;

use app\services\ai\Contracts\AiProviderInterface;
use RuntimeException;

defined('BASEPATH') or exit('No direct script access allowed');

class OpenAiProvider implements AiProviderInterface
{
    private const DEFAULT_MODEL = 'gpt-4o-mini';
    private const DEFAULT_MAX_TOKENS = 500;
    private const RESPONSES_ENDPOINT = 'https://api.openai.com/v1/responses';

    public function getName(): string
    {
        return _l('openai');
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public static function getModels(): array
    {
        return [
            ['id' => 'gpt-4o-mini', 'name' => 'GPT-4o mini'],
            ['id' => 'gpt-4o', 'name' => 'GPT-4o'],
            ['id' => 'gpt-4.1-mini', 'name' => 'GPT-4.1 mini'],
            ['id' => 'gpt-4.1', 'name' => 'GPT-4.1'],
        ];
    }

    public function chat($prompt): string
    {
        $payload = [
            'model'             => $this->determineModel(),
            'input'             => $prompt,
            'max_output_tokens' => $this->determineMaxTokens(),
        ];

        $systemPrompt = trim((string) get_option('ai_system_prompt'));
        if ($systemPrompt !== '') {
            $payload['system'] = $systemPrompt;
        }

        return $this->sendRequest($payload);
    }

    public function enhanceText(string $text, string $type): string
    {
        $instructions = $this->buildEnhancementInstruction($type);

        $prompt = <<<PROMPT
{$instructions}

"""
{$text}
"""
PROMPT;

        return $this->chat($prompt);
    }

    private function buildEnhancementInstruction(string $type): string
    {
        return match ($type) {
            'friendly' => 'Rewrite the provided message to sound more friendly and empathetic while keeping the key information. Respond only with the improved HTML content.',
            'formal'   => 'Rewrite the provided message using a formal and professional tone. Preserve all important details and respond with valid HTML only.',
            'polite'   => 'Rewrite the provided message to sound more polite and courteous without removing critical information. Return only the revised HTML.',
            default    => 'Rewrite the provided message while keeping its intent. Return only the revised HTML.',
        };
    }

    private function determineModel(): string
    {
        $configured = trim((string) get_option('openai_model'));

        if ($configured === '') {
            return self::DEFAULT_MODEL;
        }

        return $configured;
    }

    private function determineMaxTokens(): int
    {
        $configured = (int) get_option('openai_max_token');

        if ($configured <= 0) {
            return self::DEFAULT_MAX_TOKENS;
        }

        return $configured;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function sendRequest(array $payload): string
    {
        $apiKey = $this->resolveApiKey();

        $ch = curl_init(self::RESPONSES_ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
        ]);

        $rawResponse = curl_exec($ch);
        if ($rawResponse === false) {
            $error = curl_error($ch);
            curl_close($ch);

            throw new RuntimeException('AI request failed: ' . $error);
        }

        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($rawResponse, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Unexpected AI response format.');
        }

        if ($statusCode >= 400) {
            $message = $decoded['error']['message'] ?? 'Unknown error returned from AI provider.';

            throw new RuntimeException($message);
        }

        $text = $this->extractMessageText($decoded);
        if ($text === '') {
            throw new RuntimeException('AI provider returned an empty response.');
        }

        return $text;
    }

    private function extractMessageText(array $response): string
    {
        if (isset($response['output_text']) && $response['output_text'] !== '') {
            return (string) $response['output_text'];
        }

        if (isset($response['output']) && is_array($response['output'])) {
            $parts = [];
            foreach ($response['output'] as $item) {
                if (! isset($item['content']) || ! is_array($item['content'])) {
                    continue;
                }

                foreach ($item['content'] as $content) {
                    if (! is_array($content)) {
                        continue;
                    }

                    if (isset($content['type']) && in_array($content['type'], ['output_text', 'text'], true)) {
                        $parts[] = (string) ($content['text'] ?? '');
                    } elseif (isset($content['text'])) {
                        $parts[] = (string) $content['text'];
                    }
                }
            }

            $text = trim(implode('', $parts));
            if ($text !== '') {
                return $text;
            }
        }

        if (isset($response['choices'][0]['message']['content'])) {
            return (string) $response['choices'][0]['message']['content'];
        }

        return '';
    }

    private function resolveApiKey(): string
    {
        $fromSettings = trim((string) get_option('openai_api_key'));
        $fromEnv      = trim((string) ($_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY') ?? ''));

        $apiKey = $fromSettings !== '' ? $fromSettings : $fromEnv;

        if ($apiKey === '') {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        return $apiKey;
    }
}
