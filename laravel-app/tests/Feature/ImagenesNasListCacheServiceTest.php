<?php

namespace Tests\Feature;

use App\Modules\Examenes\Services\ImagenesNasListCacheService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ImagenesNasListCacheServiceTest extends TestCase
{
    public function test_it_reuses_cached_nas_list_for_same_form_and_hc(): void
    {
        Cache::store('array')->flush();

        $service = new ImagenesNasListCacheService(Cache::store('array'), 300);
        $calls = 0;

        $first = $service->remember('0908931660', '281556', function () use (&$calls): array {
            $calls++;

            return ['success' => true, 'files' => [['name' => 'first.pdf']]];
        });

        $second = $service->remember('0908931660', '281556', function () use (&$calls): array {
            $calls++;

            return ['success' => true, 'files' => [['name' => 'second.pdf']]];
        });

        $this->assertSame(1, $calls);
        $this->assertSame('first.pdf', $first['files'][0]['name']);
        $this->assertSame('first.pdf', $second['files'][0]['name']);
        $this->assertTrue($second['cached']);
    }
}
