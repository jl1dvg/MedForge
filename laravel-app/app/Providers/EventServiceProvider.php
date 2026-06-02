<?php

namespace App\Providers;

use App\Events\Crm\ExamenEstadoCambiado;
use App\Events\Crm\ExamenSolicitado;
use App\Events\Crm\SolicitudCreada;
use App\Events\Crm\SolicitudKanbanEstadoCambiado;
use App\Events\Crm\WhatsappLeadQualified;
use App\Listeners\CrmOpportunityListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(WhatsappLeadQualified::class, CrmOpportunityListener::class . '@handleWhatsappLeadQualified');
        Event::listen(SolicitudCreada::class, CrmOpportunityListener::class . '@handleSolicitudCreada');
        Event::listen(SolicitudKanbanEstadoCambiado::class, CrmOpportunityListener::class . '@handleSolicitudKanbanEstadoCambiado');
        Event::listen(ExamenSolicitado::class, CrmOpportunityListener::class . '@handleExamenSolicitado');
        Event::listen(ExamenEstadoCambiado::class, CrmOpportunityListener::class . '@handleExamenEstadoCambiado');
    }
}
