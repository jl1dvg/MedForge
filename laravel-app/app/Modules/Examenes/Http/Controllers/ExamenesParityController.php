<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Controllers;

use App\Modules\Examenes\Services\ExamenesParityService;
use App\Modules\Examenes\Services\LegacyExamenesBridge;
use App\Modules\Shared\Support\LegacySessionAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ExamenesParityController
{
    private LegacyExamenesBridge $bridge;
    private ExamenesParityService $native;

    public function __construct()
    {
        $this->bridge = new LegacyExamenesBridge();
        $this->native = new ExamenesParityService(DB::connection()->getPdo());
    }

    public function kanbanData(Request $request): Response
    {
        return $this->relayNativeJson(
            $request,
            'kanbanData',
            fn(): array => $this->native->kanbanData($request->all())
        );
    }

    public function enviarCoberturaMail(Request $request): Response
    {
        return $this->relayJson($request, 'enviarCoberturaMail');
    }

    public function reportePdf(Request $request): Response
    {
        return $this->relayRaw($request, 'reportePdf');
    }

    public function reporteExcel(Request $request): Response
    {
        return $this->relayRaw($request, 'reporteExcel');
    }

    public function actualizarEstado(Request $request): Response
    {
        return $this->relayNativeJson(
            $request,
            'actualizarEstado',
            fn(): array => $this->native->actualizarEstado($request->all(), LegacySessionAuth::userId($request))
        );
    }

    public function enviarRecordatorios(Request $request): Response
    {
        return $this->relayJson($request, 'enviarRecordatorios');
    }

    public function derivacionPreseleccion(Request $request): Response
    {
        return $this->relayNativeJson(
            $request,
            'derivacionPreseleccion',
            fn(): array => $this->native->derivacionPreseleccion($request->all())
        );
    }

    public function guardarDerivacionPreseleccion(Request $request): Response
    {
        return $this->relayNativeJson(
            $request,
            'guardarDerivacionPreseleccion',
            fn(): array => $this->native->guardarDerivacionPreseleccion($request->all())
        );
    }

    public function apiEstadoGet(Request $request): Response
    {
        $hcNumber = $request->query('hcNumber', $request->query('hc_number'));

        return $this->relayNativeJson(
            $request,
            'apiEstadoGet',
            fn(): array => $this->native->apiEstadoGet(is_scalar($hcNumber) ? (string) $hcNumber : null)
        );
    }

    public function apiEstadoPost(Request $request): Response
    {
        return $this->relayNativeJson(
            $request,
            'apiEstadoPost',
            fn(): array => $this->native->apiEstadoPost($request->all(), LegacySessionAuth::userId($request))
        );
    }

    public function turneroData(Request $request): Response
    {
        return $this->relayNativeJson(
            $request,
            'turneroData',
            fn(): array => $this->native->turneroData($request->query())
        );
    }

    public function derivacionDetalle(Request $request): Response
    {
        $hcNumber = $request->query('hc_number');
        $formId = $request->query('form_id');
        $examenId = $request->query('examen_id');

        return $this->relayNativeJson(
            $request,
            'derivacionDetalle',
            fn(): array => $this->native->derivacionDetalle(
                is_scalar($hcNumber) ? (string) $hcNumber : null,
                is_scalar($formId) ? (string) $formId : null,
                is_scalar($examenId) ? (int) $examenId : null,
                LegacySessionAuth::userId($request)
            )
        );
    }

    public function turneroLlamar(Request $request): Response
    {
        return $this->relayNativeJson(
            $request,
            'turneroLlamar',
            fn(): array => $this->native->turneroLlamar($request->all(), LegacySessionAuth::userId($request))
        );
    }

    public function crmResumen(Request $request, int $id): Response
    {
        return $this->relayNativeJson(
            $request,
            'crmResumen',
            fn(): array => $this->native->crmResumen($id),
            [$id]
        );
    }

    public function crmGuardarDetalles(Request $request, int $id): Response
    {
        return $this->relayNativeJson(
            $request,
            'crmGuardarDetalles',
            fn(): array => $this->native->crmGuardarDetalles($id, $request->all(), LegacySessionAuth::userId($request)),
            [$id]
        );
    }

    public function crmBootstrap(Request $request, int $id): Response
    {
        return $this->relayNativeJson(
            $request,
            'crmBootstrap',
            fn(): array => $this->native->crmBootstrap(
                $id,
                $request->all(),
                LegacySessionAuth::userId($request),
                $this->sessionPermissions($request)
            ),
            [$id]
        );
    }

    public function crmChecklistState(Request $request, int $id): Response
    {
        return $this->relayNativeJson(
            $request,
            'crmChecklistState',
            fn(): array => $this->native->crmChecklistState($id, $this->sessionPermissions($request)),
            [$id]
        );
    }

    public function crmActualizarChecklist(Request $request, int $id): Response
    {
        return $this->relayNativeJson(
            $request,
            'crmActualizarChecklist',
            fn(): array => $this->native->crmActualizarChecklist(
                $id,
                $request->all(),
                LegacySessionAuth::userId($request),
                $this->sessionPermissions($request)
            ),
            [$id]
        );
    }

    public function crmAgregarNota(Request $request, int $id): Response
    {
        return $this->relayNativeJson(
            $request,
            'crmAgregarNota',
            fn(): array => $this->native->crmAgregarNota($id, $request->all(), LegacySessionAuth::userId($request)),
            [$id]
        );
    }

    public function crmRegistrarBloqueo(Request $request, int $id): Response
    {
        return $this->relayNativeJson(
            $request,
            'crmRegistrarBloqueo',
            fn(): array => $this->native->crmRegistrarBloqueo($id, $request->all(), LegacySessionAuth::userId($request)),
            [$id]
        );
    }

    public function crmGuardarTarea(Request $request, int $id): Response
    {
        return $this->relayNativeJson(
            $request,
            'crmGuardarTarea',
            fn(): array => $this->native->crmGuardarTarea($id, $request->all(), LegacySessionAuth::userId($request)),
            [$id]
        );
    }

    public function crmActualizarTarea(Request $request, int $id): Response
    {
        return $this->relayNativeJson(
            $request,
            'crmActualizarTarea',
            fn(): array => $this->native->crmActualizarTarea($id, $request->all()),
            [$id]
        );
    }

    public function crmSubirAdjunto(Request $request, int $id): Response
    {
        return $this->relayNativeJson(
            $request,
            'crmSubirAdjunto',
            fn(): array => $this->native->crmSubirAdjunto(
                $id,
                $request->file('archivo'),
                is_scalar($request->input('descripcion')) ? (string) $request->input('descripcion') : null,
                LegacySessionAuth::userId($request)
            ),
            [$id]
        );
    }

    public function prefactura(Request $request): Response|RedirectResponse
    {
        return $this->relayRaw($request, 'prefactura');
    }

    /**
     * @param array<int,mixed> $args
     */
    private function relayJson(Request $request, string $legacyMethod, array $args = []): Response
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json([
                'success' => false,
                'error' => 'Sesión expirada',
            ], 401);
        }

        return $this->dispatch($request, $legacyMethod, $args, true);
    }

    /**
     * @param array<int,mixed> $args
     */
    private function relayRaw(Request $request, string $legacyMethod, array $args = []): Response|RedirectResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return redirect('/auth/login?auth_required=1');
        }

        return $this->dispatch($request, $legacyMethod, $args, false);
    }

    /**
     * @param callable():array{status:int,payload:array<string,mixed>} $nativeResolver
     */
    private function relayNativeJson(
        Request $request,
        string $legacyMethod,
        callable $nativeResolver,
        array $legacyArgs = []
    ): Response
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json([
                'success' => false,
                'error' => 'Sesión expirada',
            ], 401);
        }

        try {
            $nativeResult = $nativeResolver();
            $status = (int) ($nativeResult['status'] ?? 200);
            if ($status < 100 || $status > 599) {
                $status = 200;
            }

            $payload = is_array($nativeResult['payload'] ?? null) ? $nativeResult['payload'] : [];

            return response()->json($payload, $status);
        } catch (Throwable $e) {
            Log::warning('examenes.v2.native.fallback', [
                'method' => $legacyMethod,
                'error' => $e->getMessage(),
            ]);

            return $this->dispatch($request, $legacyMethod, $legacyArgs, true);
        }
    }

    /**
     * @return array<int,string>
     */
    private function sessionPermissions(Request $request): array
    {
        $session = LegacySessionAuth::readSession($request);
        $raw = $session['permisos'] ?? [];

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            } else {
                $split = array_map('trim', explode(',', $raw));
                $raw = array_values(array_filter($split, static fn(string $item): bool => $item !== ''));
            }
        }

        if (!is_array($raw)) {
            return [];
        }

        $permissions = [];
        array_walk_recursive($raw, static function (mixed $value) use (&$permissions): void {
            if (!is_string($value)) {
                return;
            }

            $permission = trim($value);
            if ($permission === '') {
                return;
            }

            $permissions[] = $permission;
        });

        return array_values(array_unique($permissions));
    }

    /**
     * @param array<int,mixed> $args
     */
    private function dispatch(Request $request, string $legacyMethod, array $args, bool $expectJson): Response
    {
        try {
            $captured = $this->bridge->dispatch($request, $legacyMethod, $args);
        } catch (Throwable $e) {
            Log::error('examenes.v2.parity.error', [
                'method' => $legacyMethod,
                'error' => $e->getMessage(),
            ]);

            if ($expectJson) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se pudo procesar la solicitud en v2.',
                ], 500);
            }

            return response('No se pudo procesar la solicitud en v2.', 500);
        }

        $status = (int) ($captured['status'] ?? 200);
        if ($status < 100 || $status > 599) {
            $status = 200;
        }

        $headers = is_array($captured['headers'] ?? null) ? $captured['headers'] : [];
        if ($expectJson && !isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/json; charset=UTF-8';
        }

        return response((string) ($captured['body'] ?? ''), $status, $headers);
    }
}
