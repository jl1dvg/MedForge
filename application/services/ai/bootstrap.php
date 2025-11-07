<?php

use app\services\ai\AiProviderRegistry;
use app\services\ai\Providers\OpenAiProvider;

defined('BASEPATH') or exit('No direct script access allowed');

AiProviderRegistry::registerProvider('openai', new OpenAiProvider());

if (function_exists('hooks')) {
    hooks()->add_action('settings_ai', function () {
        $CI = get_instance();
        $CI->load->view('admin/settings/includes/ai_openai');
    });
}
