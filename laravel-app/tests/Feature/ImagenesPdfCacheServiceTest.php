<?php

namespace Tests\Feature;

use App\Modules\Reporting\Services\ImagenesPdfCacheService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ImagenesPdfCacheServiceTest extends TestCase
{
    public function test_it_reuses_cached_pdf_until_form_cache_is_invalidated(): void
    {
        Cache::store('array')->flush();

        $service = new ImagenesPdfCacheService(Cache::store('array'));
        $calls = 0;

        $first = $service->remember('012b', ['form_id' => '281556', 'hc_number' => '0908931660'], '281556', function () use (&$calls): array {
            $calls++;

            return [
                'content' => 'PDF-V1',
                'filename' => 'informe.pdf',
            ];
        });

        $second = $service->remember('012b', ['form_id' => '281556', 'hc_number' => '0908931660'], '281556', function () use (&$calls): array {
            $calls++;

            return [
                'content' => 'PDF-V2',
                'filename' => 'informe.pdf',
            ];
        });

        $this->assertSame(1, $calls);
        $this->assertSame('PDF-V1', $first['content']);
        $this->assertSame('PDF-V1', $second['content']);
        $this->assertTrue($second['cached']);

        $service->forgetForm('281556');

        $third = $service->remember('012b', ['form_id' => '281556', 'hc_number' => '0908931660'], '281556', function () use (&$calls): array {
            $calls++;

            return [
                'content' => 'PDF-V3',
                'filename' => 'informe.pdf',
            ];
        });

        $this->assertSame(2, $calls);
        $this->assertSame('PDF-V3', $third['content']);
        $this->assertFalse($third['cached']);
    }
}
