<?php

declare(strict_types=1);

namespace App\Modules\Consultas\Http\Controllers;

use App\Modules\Consultas\Services\ConsultasParityService;
use App\Modules\Shared\Support\LegacyCurrentUser;
use App\Modules\Shared\Support\LegacySessionAuth;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ConsultasUiController
{
    private ConsultasParityService $service;

    public function __construct()
    {
        $pdo = DB::connection()->getPdo();
        $this->service = new ConsultasParityService($pdo);
    }

    public function edit(Request $request): View|RedirectResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return redirect('/auth/login?auth_required=1');
        }

        $formId = trim((string) $request->query('form_id', ''));
        $hcNumber = trim((string) $request->query('hc_number', ''));
        $editorData = [];
        $loadError = null;

        if ($formId !== '' && $hcNumber !== '') {
            try {
                $result = $this->service->editorData($formId, $hcNumber);
                $editorData = is_array($result['data'] ?? null) ? $result['data'] : [];
            } catch (RuntimeException $e) {
                $loadError = $e->getMessage();
            } catch (\Throwable) {
                $loadError = 'No se pudo cargar el contexto clínico de la consulta.';
            }
        } else {
            $loadError = 'Faltan form_id y hc_number para abrir la consulta desde Agenda.';
        }

        return view('consultas.v2-editor', [
            'pageTitle' => 'Consulta',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'editorData' => $editorData,
            'loadError' => $loadError,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return redirect('/auth/login?auth_required=1');
        }

        $payload = $this->buildPayload($request);
        $query = http_build_query([
            'form_id' => $payload['form_id'] ?? '',
            'hc_number' => $payload['hcNumber'] ?? '',
        ], '', '&', PHP_QUERY_RFC3986);
        $target = '/v2/consultas' . ($query !== '' ? ('?' . $query) : '');

        $result = $this->service->guardar($payload);
        $ok = (bool) ($result['success'] ?? false);
        $message = trim((string) ($result['message'] ?? ''));

        if (!$ok) {
            return redirect($target)
                ->withInput()
                ->with('consultas_error', $message !== '' ? $message : 'No se pudo guardar la consulta.');
        }

        return redirect($target)
            ->with('consultas_status', $message !== '' ? $message : 'Consulta guardada correctamente.');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(Request $request): array
    {
        return [
            'form_id' => trim((string) $request->input('form_id', '')),
            'hcNumber' => trim((string) $request->input('hc_number', '')),
            'fechaActual' => trim((string) $request->input('fechaActual', date('Y-m-d'))),
            'fechaNacimiento' => trim((string) $request->input('fechaNacimiento', '')),
            'sexo' => trim((string) $request->input('sexo', '')),
            'celular' => trim((string) $request->input('celular', '')),
            'ciudad' => trim((string) $request->input('ciudad', '')),
            'doctor' => trim((string) $request->input('doctor', '')),
            'motivoConsulta' => trim((string) $request->input('motivoConsulta', '')),
            'enfermedadActual' => trim((string) $request->input('enfermedadActual', '')),
            'examenFisico' => trim((string) $request->input('examenFisico', '')),
            'plan' => trim((string) $request->input('plan', '')),
            'estadoEnfermedad' => trim((string) $request->input('estadoEnfermedad', '')),
            'antecedente_alergico' => trim((string) $request->input('antecedente_alergico', '')),
            'signos_alarma' => trim((string) $request->input('signos_alarma', '')),
            'recomen_no_farmaco' => trim((string) $request->input('recomen_no_farmaco', '')),
            'vigenciaReceta' => trim((string) $request->input('vigenciaReceta', '')),
            'diagnosticos' => $this->normalizeDiagnosticos($request->input('diagnosticos', [])),
            'examenes' => $this->normalizeExamenes($request->input('examenes', [])),
            'recetas' => $this->normalizeRecetas($request->input('recetas', [])),
            'pio' => $this->normalizePio($request->input('pio', [])),
        ];
    }

    /**
     * @param mixed $rows
     * @return array<int, array<string, string>>
     */
    private function normalizeDiagnosticos(mixed $rows): array
    {
        $items = is_array($rows) ? $rows : [];
        $normalized = [];

        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }

            $payload = [
                'idDiagnostico' => trim((string) ($row['idDiagnostico'] ?? '')),
                'ojo' => trim((string) ($row['ojo'] ?? '')),
                'evidencia' => isset($row['evidencia']) ? '1' : '0',
                'selector' => trim((string) ($row['selector'] ?? '')),
            ];

            if ($payload['idDiagnostico'] === '' && $payload['ojo'] === '' && $payload['selector'] === '') {
                continue;
            }

            $normalized[] = $payload;
        }

        return $normalized;
    }

    /**
     * @param mixed $rows
     * @return array<int, array<string, string>>
     */
    private function normalizeExamenes(mixed $rows): array
    {
        $items = is_array($rows) ? $rows : [];
        $normalized = [];

        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }

            $payload = [
                'codigo' => trim((string) ($row['codigo'] ?? '')),
                'nombre' => trim((string) ($row['nombre'] ?? '')),
                'lateralidad' => trim((string) ($row['lateralidad'] ?? '')),
            ];

            if ($payload['codigo'] === '' && $payload['nombre'] === '' && $payload['lateralidad'] === '') {
                continue;
            }

            $normalized[] = $payload;
        }

        return $normalized;
    }

    /**
     * @param mixed $rows
     * @return array<int, array<string, string>>
     */
    private function normalizeRecetas(mixed $rows): array
    {
        $items = is_array($rows) ? $rows : [];
        $normalized = [];

        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }

            $payload = [
                'idRecetas' => trim((string) ($row['idRecetas'] ?? '')),
                'estadoRecetaid' => trim((string) ($row['estadoRecetaid'] ?? '')),
                'producto' => trim((string) ($row['producto'] ?? '')),
                'vias' => trim((string) ($row['vias'] ?? '')),
                'dosis' => trim((string) ($row['dosis'] ?? '')),
                'unidad' => trim((string) ($row['unidad'] ?? '')),
                'pauta' => trim((string) ($row['pauta'] ?? '')),
                'cantidad' => trim((string) ($row['cantidad'] ?? '')),
                'total_farmacia' => trim((string) ($row['total_farmacia'] ?? '')),
                'observaciones' => trim((string) ($row['observaciones'] ?? '')),
            ];

            if ($payload['producto'] === '' && $payload['vias'] === '' && $payload['dosis'] === '' && $payload['cantidad'] === '') {
                continue;
            }

            $normalized[] = $payload;
        }

        return $normalized;
    }

    /**
     * @param mixed $rows
     * @return array<int, array<string, string>>
     */
    private function normalizePio(mixed $rows): array
    {
        $items = is_array($rows) ? $rows : [];
        $normalized = [];

        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }

            $payload = [
                'id' => trim((string) ($row['id'] ?? '')),
                'tonometro' => trim((string) ($row['tonometro'] ?? '')),
                'od' => trim((string) ($row['od'] ?? '')),
                'oi' => trim((string) ($row['oi'] ?? '')),
                'po_patologico' => isset($row['po_patologico']) ? '1' : '0',
                'po_hora' => trim((string) ($row['po_hora'] ?? '')),
                'hora_fin' => trim((string) ($row['hora_fin'] ?? '')),
                'po_observacion' => trim((string) ($row['po_observacion'] ?? '')),
            ];

            if (
                $payload['tonometro'] === ''
                && $payload['od'] === ''
                && $payload['oi'] === ''
                && $payload['po_hora'] === ''
                && $payload['hora_fin'] === ''
                && $payload['po_observacion'] === ''
            ) {
                continue;
            }

            $normalized[] = $payload;
        }

        return $normalized;
    }
}
