<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\AppSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica requests provenientes de la extensión Chrome CiveExtension.
 *
 * Tres mecanismos (en orden de prioridad):
 *  1. Header Origin: chrome-extension://<id> — Chrome lo setea automáticamente en
 *     todas las requests cross-origin del background service worker. Validamos el ID
 *     contra los IDs conocidos en app_settings (cive_extension_extension_id_remote
 *     y cive_extension_extension_id_local).
 *  2. Origen web confiable (SigCenter, cive.ddns.net, etc.) — cuando el service worker
 *     de Chrome está inactivo, el content script hace un fallback a fetch directo con
 *     el origen de la página (SigCenter). Aceptamos los mismos orígenes que ConsultasCors
 *     ya valida, ya que son orígenes médicos controlados.
 *  3. Header X-CiveExtension-Key — para llamadas server-to-server o pruebas.
 *     El valor debe coincidir con CIVE_EXTENSION_SECRET_KEY en .env
 *     (config key: services.cive_extension.secret_key).
 *
 * Nota: OPTIONS preflight es manejado por ConsultasCors antes de llegar aquí.
 * El doctor nunca necesita loguearse a MedForge — la extensión se autentica
 * por su propio ID de extensión Chrome o por estar en SigCenter.
 */
class CiveExtensionAuth
{
    private const CACHE_KEY = 'cive_extension_allowed_ids';
    private const CACHE_TTL = 300; // 5 minutos

    /**
     * Orígenes web confiables: los mismos que ConsultasCors permite.
     * Cuando el service worker de Chrome está inactivo, el content script hace fetch
     * directo con el origen de la página (SigCenter). Estos orígenes son sistemas
     * médicos controlados, no acceso público.
     *
     * @var array<int, string>
     */
    private array $trustedWebOrigins = [
        'http://cive.ddns.net',
        'https://cive.ddns.net',
        'http://cive.ddns.net:8085',
        'https://cive.ddns.net:8085',
        'http://192.168.1.13:8085',
        'http://localhost:8085',
        'http://127.0.0.1:8085',
        'https://asistentecive.consulmed.me',
        'https://cive.consulmed.me',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $origin = trim((string) $request->headers->get('Origin', ''));

        // 1. Requests provenientes del background service worker de la extensión Chrome
        if (str_starts_with(strtolower($origin), 'chrome-extension://')) {
            $extensionId = substr($origin, strlen('chrome-extension://'));
            if ($this->isAllowedExtensionId($extensionId)) {
                return $next($request);
            }

            return response()->json([
                'success' => false,
                'message' => 'Extensión no autorizada.',
            ], 403);
        }

        // 2. Fallback del content script cuando el service worker está inactivo:
        //    el fetch directo llega con el origen de SigCenter u otro origen médico confiable.
        if ($origin !== '' && in_array($origin, $this->trustedWebOrigins, true)) {
            return $next($request);
        }

        // 3. Sin Origin de extensión ni web confiable → verificar API key server-to-server
        $secretKey = trim((string) config('services.cive_extension.secret_key', ''));
        $providedKey = trim((string) $request->headers->get('X-CiveExtension-Key', ''));

        if ($secretKey !== '' && $providedKey !== '' && hash_equals($secretKey, $providedKey)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'No autorizado.',
        ], 401);
    }

    private function isAllowedExtensionId(string $id): bool
    {
        if (trim($id) === '') {
            return false;
        }

        /** @var array<int, string> $allowedIds */
        $allowedIds = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): array {
            $rows = AppSetting::query()
                ->whereIn('name', [
                    'cive_extension_extension_id_remote',
                    'cive_extension_extension_id_local',
                ])
                ->pluck('value')
                ->all();

            return array_values(
                array_filter(array_map('trim', $rows), static fn(string $v) => $v !== '')
            );
        });

        return in_array($id, $allowedIds, true);
    }
}
