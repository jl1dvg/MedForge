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
 * Dos mecanismos (en orden de prioridad):
 *  1. Header Origin: chrome-extension://<id> — Chrome lo setea automáticamente en
 *     todas las requests cross-origin del background service worker. Validamos el ID
 *     contra los IDs conocidos en app_settings (cive_extension_extension_id_remote
 *     y cive_extension_extension_id_local).
 *  2. Header X-CiveExtension-Key — para llamadas server-to-server o pruebas.
 *     El valor debe coincidir con CIVE_EXTENSION_SECRET_KEY en .env
 *     (config key: services.cive_extension.secret_key).
 *
 * Nota: OPTIONS preflight es manejado por ConsultasCors antes de llegar aquí.
 * El doctor nunca necesita loguearse a MedForge — la extensión se autentica
 * por su propio ID de extensión Chrome.
 */
class CiveExtensionAuth
{
    private const CACHE_KEY = 'cive_extension_allowed_ids';
    private const CACHE_TTL = 300; // 5 minutos

    public function handle(Request $request, Closure $next): Response
    {
        $origin = trim((string) $request->headers->get('Origin', ''));

        // Requests provenientes de la extensión Chrome
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

        // Sin Origin de extensión → verificar API key para llamadas server-to-server
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
