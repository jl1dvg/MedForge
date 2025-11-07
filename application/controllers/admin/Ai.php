<?php

use app\services\ai\AiProviderRegistry;
use app\services\ai\Contracts\AiProviderInterface;
use RuntimeException;

defined('BASEPATH') or exit('No direct script access allowed');

class Ai extends AdminController
{
    private ?AiProviderInterface $provider = null;

    public function __construct()
    {
        parent::__construct();

        try {
            $this->provider = AiProviderRegistry::getProvider(get_option('ai_provider'));
        } catch (RuntimeException $exception) {
            $this->provider = null;
        }
    }

    public function text_enhancement($enhancementType)
    {
        if (! in_array($enhancementType, ['polite', 'formal', 'friendly'])) {
            show_404('Invalid enhancement type');
        }

        if (! $this->provider) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => 'AI provider is not configured.',
            ]);

            return;
        }

        try {
            $enhancedText = $this->provider->enhanceText((string) $this->input->post('text'), $enhancementType);

            echo json_encode([
                'success' => true,
                'message' => $enhancedText,
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
