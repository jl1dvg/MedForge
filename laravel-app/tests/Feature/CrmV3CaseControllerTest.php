<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CrmV3CaseControllerTest extends TestCase
{
    public function test_v3_crm_routes_are_registered(): void
    {
        $routeNames = [
            'v3.crm.cases.show',
            'v3.crm.cases.notes.store',
            'v3.crm.cases.tasks.store',
            'v3.crm.cases.whatsapp.store',
            'v3.crm.cases.email.store',
            'v3.crm.cases.proposals.store',
        ];

        foreach ($routeNames as $routeName) {
            $this->assertNotNull(Route::getRoutes()->getByName($routeName), "Route [{$routeName}] is not registered.");
        }
    }
}
