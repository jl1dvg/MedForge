<?php

namespace App\Providers;

use App\Models\ConsultaExamene;
use App\Models\SolicitudProcedimiento;
use App\Models\WhatsappLead;
use App\Modules\Shared\Support\LegacyCurrentUser;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Relation::morphMap([
            'whatsapp_lead'           => WhatsappLead::class,
            'solicitud_procedimiento' => SolicitudProcedimiento::class,
            'consulta_examenes'       => ConsultaExamene::class,
        ]);

        View::composer('layouts.partials.header', function (\Illuminate\View\View $view) {
            if (!isset($view->getData()['currentUser'])) {
                $request = request();
                $view->with('currentUser', LegacyCurrentUser::resolve($request));
            }
        });
    }
}
