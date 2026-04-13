<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConsultasCors
{
    /**
     * @var array<int, string>
     */
    private array $allowedOrigins = [
        'http://cive.ddns.net',
        'https://cive.ddns.net',
        'http://sigcenter.ddns.net:18093',
        'https://sigcenter.ddns.net:18093',
        'http://192.168.1.13:8085',
        'http://localhost:8085',
        'http://127.0.0.1:8085',
        'https://asistentecive.consulmed.me',
        'https://cive.consulmed.me',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $origin = trim((string) $request->headers->get('Origin', ''));

        if ($origin !== '' && !$this->isAllowedOrigin($origin)) {
            return response()->json([
                'success' => false,
                'message' => 'Origen no permitido',
            ], 403);
        }

        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            $response = response('', 204);
            return $this->withCorsHeaders($response, $origin);
        }

        $response = $next($request);
        return $this->withCorsHeaders($response, $origin);
    }

    private function isAllowedOrigin(string $origin): bool
    {
        return in_array($origin, $this->allowedOrigins, true);
    }

    private function withCorsHeaders(Response $response, string $origin): Response
    {
        if ($origin !== '') {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Vary', 'Origin');
        }

        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Request-Id, X-Requested-With');

        return $response;
    }
}

