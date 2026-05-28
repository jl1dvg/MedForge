<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ImagenesDashboardV3RoutesTest extends TestCase
{
    public function test_v3_dashboard_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('imagenes.dashboard.v3'));
        $this->assertTrue(Route::has('imagenes.dashboard.v3.data'));
        $this->assertTrue(Route::has('imagenes.dashboard.v3.detail'));
        $this->assertTrue(Route::has('imagenes.dashboard.v3.export'));

        $this->assertSame('v3/imagenes/dashboard', Route::getRoutes()->getByName('imagenes.dashboard.v3')?->uri());
        $this->assertSame('v3/imagenes/dashboard/data', Route::getRoutes()->getByName('imagenes.dashboard.v3.data')?->uri());
        $this->assertSame('v3/imagenes/dashboard/detail', Route::getRoutes()->getByName('imagenes.dashboard.v3.detail')?->uri());
        $this->assertSame('v3/imagenes/dashboard/export', Route::getRoutes()->getByName('imagenes.dashboard.v3.export')?->uri());
    }
}
