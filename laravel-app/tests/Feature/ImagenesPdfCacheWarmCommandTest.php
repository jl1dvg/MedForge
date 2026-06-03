<?php

namespace Tests\Feature;

use App\Jobs\GenerateImagenesPdfCacheJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ImagenesPdfCacheWarmCommandTest extends TestCase
{
    public function test_it_dispatches_warm_jobs_for_direct_case(): void
    {
        Queue::fake();

        $exitCode = Artisan::call('imagenes:pdf-cache-warm', [
            '--form-id' => '281556',
            '--hc-number' => '0908931660',
        ]);

        $this->assertSame(0, $exitCode);
        Queue::assertPushed(GenerateImagenesPdfCacheJob::class, 2);
    }

    public function test_it_dispatches_warm_jobs_for_pair_options(): void
    {
        Queue::fake();

        $exitCode = Artisan::call('imagenes:pdf-cache-warm', [
            '--pair' => ['281556:0908931660', '281557:0911111111'],
        ]);

        $this->assertSame(0, $exitCode);
        Queue::assertPushed(GenerateImagenesPdfCacheJob::class, 4);
    }

    public function test_it_fails_without_cases(): void
    {
        Queue::fake();

        $exitCode = Artisan::call('imagenes:pdf-cache-warm');

        $this->assertSame(1, $exitCode);
        Queue::assertNothingPushed();
    }
}
