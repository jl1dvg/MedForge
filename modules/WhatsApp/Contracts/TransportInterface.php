<?php

namespace Modules\WhatsApp\Contracts;

interface TransportInterface
{
    /**
     * @param array<string, string> $config
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    public function send(array $config, array $payload);
}
