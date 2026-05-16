<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class MarkLegacyAliasUsage
{
    public function handle(Request $request, Closure $next, string $aliasType = 'legacy'): Response
    {
        $response = $next($request);
        $canonicalPath = $this->canonicalPath($request);

        Log::warning('solicitudes.legacy_alias_used', [
            'alias_type' => $aliasType,
            'method' => $request->getMethod(),
            'path' => $request->path(),
            'canonical_path' => $canonicalPath,
            'user_id' => optional($request->user())->getAuthIdentifier(),
            'request_id' => $request->headers->get('X-Request-Id'),
        ]);

        $response->headers->set('X-Legacy-Alias', '1');
        $response->headers->set('X-Legacy-Alias-Type', $aliasType);
        if ($canonicalPath !== null) {
            $response->headers->set('X-Canonical-Path', $canonicalPath);
        }
        $response->headers->set('Deprecation', 'true');

        return $response;
    }

    private function canonicalPath(Request $request): ?string
    {
        $path = '/' . ltrim($request->path(), '/');
        if (str_starts_with($path, '/api/solicitudes/')) {
            return '/v2/solicitudes/' . substr($path, strlen('/api/solicitudes/'));
        }

        return match ($path) {
            '/solicitudes/api/estado' => '/v2/solicitudes/api/estado',
            default => null,
        };
    }
}
