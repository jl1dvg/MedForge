<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class QueueHealthCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(
        private readonly string $key = 'worker_queue_health_check'
    ) {
    }

    public function handle(): void
    {
        Cache::put($this->key, [
            'app_env' => app()->environment(),
            'host' => gethostname(),
            'processed_at' => now()->toDateTimeString(),
        ], 300);
    }
}
