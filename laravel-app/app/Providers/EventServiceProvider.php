<?php

namespace App\Providers;

use App\Events\Crm\ExamenSolicitado;
use App\Events\Crm\SolicitudCreada;
use App\Events\Crm\WhatsappLeadQualified;
use App\Listeners\CrmOpportunityListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        WhatsappLeadQualified::class => [
            [CrmOpportunityListener::class, 'handleWhatsappLeadQualified'],
        ],
        SolicitudCreada::class => [
            [CrmOpportunityListener::class, 'handleSolicitudCreada'],
        ],
        ExamenSolicitado::class => [
            [CrmOpportunityListener::class, 'handleExamenSolicitado'],
        ],
    ];
}
