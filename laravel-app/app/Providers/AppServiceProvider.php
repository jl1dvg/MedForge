<?php

namespace App\Providers;

use App\Models\WhatsappLead;
use App\Providers\EventServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->register(EventServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'whatsapp_lead' => WhatsappLead::class,
            // 'solicitud' and 'examen' are added when those Laravel models exist
        ]);
    }
}
