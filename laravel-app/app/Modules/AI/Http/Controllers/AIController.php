<?php

declare(strict_types=1);

namespace App\Modules\AI\Http\Controllers;

use App\Modules\AI\Services\AIConfigService;
use Helpers\OpenAIHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class AIController
{
    private AIConfigService $configService;
    private ?OpenAIHelper $ai = null;

    public function __construct()
    {
        $this->configService = new AIConfigService();
    }

    // POST /ai/enfermedad
    public function generarEnfermedad(Request $request): JsonResponse
    {
        if (!$this->configService->isFeatureEnabled(AIConfigService::FEATURE_CONSULTAS_ENFERMEDAD)) {
            return response()->json([
                'ok' => false,
                'error' => 'La asistencia de IA para enfermedad actual está deshabilitada en la configuración.',
            ], 403);
        }

        $examen = trim((string) $request->input('examen_fisico', ''));
        if ($examen === '') {
            return response()->json(['ok' => false, 'error' => 'examen_fisico es requerido'], 400);
        }

        try {
            $texto = $this->client()->generateEnfermedadProblemaActual($examen);

            return response()->json(['ok' => true, 'data' => $texto]);
        } catch (RuntimeException $exception) {
            return response()->json(['ok' => false, 'error' => $exception->getMessage()], 503);
        } catch (Throwable $exception) {
            return response()->json(['ok' => false, 'error' => $exception->getMessage()], 500);
        }
    }

    // POST /ai/plan
    public function generarPlan(Request $request): JsonResponse
    {
        if (!$this->configService->isFeatureEnabled(AIConfigService::FEATURE_CONSULTAS_PLAN)) {
            return response()->json([
                'ok' => false,
                'error' => 'La generación asistida de planes está deshabilitada en la configuración de IA.',
            ], 403);
        }

        $plan = trim((string) $request->input('plan', ''));
        $insurance = trim((string) $request->input('insurance', ''));
        if ($plan === '' || $insurance === '') {
            return response()->json(['ok' => false, 'error' => 'plan e insurance son requeridos'], 400);
        }

        $procedimiento = $request->input('procedimiento');
        $ojo = $request->input('ojo');

        try {
            $texto = $this->client()->generatePlanTratamiento($plan, $insurance, $procedimiento, $ojo);

            return response()->json(['ok' => true, 'data' => $texto]);
        } catch (RuntimeException $exception) {
            return response()->json(['ok' => false, 'error' => $exception->getMessage()], 503);
        } catch (Throwable $exception) {
            return response()->json(['ok' => false, 'error' => $exception->getMessage()], 500);
        }
    }

    private function client(): OpenAIHelper
    {
        if ($this->ai instanceof OpenAIHelper) {
            return $this->ai;
        }

        $provider = $this->configService->getActiveProvider();
        if ($provider !== AIConfigService::PROVIDER_OPENAI) {
            throw new RuntimeException('No hay un proveedor de IA configurado o habilitado.');
        }

        $config = $this->configService->getOpenAIConfig();

        if ($config['api_key'] === '' || $config['endpoint'] === '') {
            throw new RuntimeException('Configura la API Key y el endpoint de OpenAI antes de usar la IA.');
        }

        $this->ai = new OpenAIHelper([
            'api_key' => $config['api_key'],
            'endpoint' => $config['endpoint'],
            'model' => $config['model'],
            'max_output_tokens' => $config['max_output_tokens'],
            'headers' => $config['headers'],
        ]);

        return $this->ai;
    }
}
