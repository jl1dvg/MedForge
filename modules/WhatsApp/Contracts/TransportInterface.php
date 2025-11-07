<?php

namespace Modules\WhatsApp\Contracts;

interface TransportInterface
{
    /**
     * @param array<string, string> $config
     * @param array<string, mixed> $payload
     */
    public function send(array $config, array $payload): bool;
}
