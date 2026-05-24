<?php

namespace App\Jobs;

use App\Modules\Whatsapp\Services\WebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class ProcessInboundMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        private readonly array $payload,
        private readonly string $waNumber,
    ) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->waNumber))->releaseAfter(5)->expireAfter(120)];
    }

    public function handle(WebhookService $service): void
    {
        $service->process($this->payload);
    }
}
