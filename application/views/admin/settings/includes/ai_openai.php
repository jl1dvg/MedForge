<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<div class="row ai-provider-section" data-ai-provider="openai">
    <div class="col-md-6">
        <?= render_input('settings[openai_api_key]', 'openai_api_key', get_option('openai_api_key'), 'password', ['autocomplete' => 'off']); ?>
    </div>
    <div class="col-md-6">
        <?= render_select('settings[openai_model]', app\services\ai\Providers\OpenAiProvider::getModels(), ['id', 'name'], 'openai_model', get_option('openai_model') ?: 'gpt-4o-mini'); ?>
    </div>
</div>
<div class="row ai-provider-section" data-ai-provider="openai">
    <div class="col-md-6">
        <?= render_input('settings[openai_max_token]', 'openai_max_token', get_option('openai_max_token'), 'number', ['min' => 1, 'max' => 4000]); ?>
    </div>
</div>
