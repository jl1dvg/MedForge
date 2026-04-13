<?php

namespace App\Http\Middleware;

use App\Modules\Whatsapp\Support\WhatsappFeature;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWhatsappFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $section = 'ui', string $legacyTarget = ''): Response
    {
        if (WhatsappFeature::sectionEnabled($section)) {
            return $next($request);
        }

        if ($section === 'ui' && WhatsappFeature::fallbackToLegacy() && $legacyTarget !== '') {
            return new RedirectResponse($legacyTarget);
        }

        return new JsonResponse([
            'ok' => false,
            'error' => 'WhatsApp Laravel feature disabled.',
            'section' => $section,
            'fallback' => WhatsappFeature::fallbackToLegacy() ? 'legacy' : 'disabled',
            'legacy_target' => $legacyTarget !== '' ? $legacyTarget : null,
            'compare_with_legacy' => WhatsappFeature::compareWithLegacy(),
        ], 503);
    }
}
