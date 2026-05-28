<?php

namespace App\Providers;

use App\Models\WhatsappLead;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Relation::morphMap([
            'whatsapp_lead' => WhatsappLead::class,
        ]);
    }
}
