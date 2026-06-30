<?php

namespace Tests\Unit;

use App\Modules\Shared\Support\MedforgeNavigation;
use Illuminate\Http\Request;
use Tests\TestCase;

class MedforgeNavigationTest extends TestCase
{
    public function test_agenda_v3_views_are_available_from_main_navigation(): void
    {
        $request = Request::create('/v2/agenda/v3?view=agenda');
        $request->attributes->set('_legacy_resolved_permissions', ['agenda.view', 'pacientes.flujo.view']);

        $navigation = MedforgeNavigation::build($request);
        $consulta = collect($navigation['sidebar'])
            ->firstWhere('label', 'Consulta');

        $this->assertIsArray($consulta);

        $links = collect($consulta['children'])
            ->where('type', 'item')
            ->mapWithKeys(fn (array $item): array => [$item['label'] => $item['href']])
            ->all();

        $this->assertSame('/v2/agenda/v3?view=agenda', $links['Agenda'] ?? null);
        $this->assertSame('/v2/agenda/v3?view=flowboard', $links['Flujo de Pacientes'] ?? null);
        $this->assertSame('/v2/agenda/v3?view=miagenda', $links['Mi agenda (médico)'] ?? null);
        $this->assertSame('/v2/agenda/v3?view=config', $links['Configuración base'] ?? null);
    }
}
