<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_341 extends CI_Migration
{
    public function up(): void
    {
        if (! option_exists('openai_api_key')) {
            add_option('openai_api_key', '');
        }

        if (! option_exists('openai_model')) {
            add_option('openai_model', 'gpt-4o-mini');
        }
    }
}
