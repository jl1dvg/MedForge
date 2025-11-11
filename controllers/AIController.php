<?php
namespace Controllers;

use Core\BaseController;
use Helpers\OpenAIHelper;
use Modules\AI\Services\AIConfigService;
use PDO;
use RuntimeException;
use Throwable;

class AIController extends BaseController
{
    private AIConfigService $configService;
    private ?OpenAIHelper $ai = null;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->configService = new AIConfigService($pdo);
    }

    // POST /ai/enfermedad
    public function generarEnfermedad(): void
    {
        $this->requireAuth();
        $this->requirePermission(['ai.consultas.enfermedad', 'ai.manage', 'administrativo']);

        if (!$this->configService->isFeatureEnabled(AIConfigService::FEATURE_CONSULTAS_ENFERMEDAD)) {
            $this->json([
                'ok' => false,
                'error' => 'La asistencia de IA para enfermedad actual está deshabilitada en la configuración.',
            ], 403);

            return;
        }

        $examen = trim($_POST['examen_fisico'] ?? '');
        if ($examen === '') {
            $this->json(['ok' => false, 'error' => 'examen_fisico es requerido'], 400);

            return;
        }

        try {
            $texto = $this->client()->generateEnfermedadProblemaActual($examen);
            $this->json(['ok' => true, 'data' => $texto]);
        } catch (RuntimeException $exception) {
            $this->json(['ok' => false, 'error' => $exception->getMessage()], 503);
        } catch (Throwable $exception) {
            $this->json(['ok' => false, 'error' => $exception->getMessage()], 500);
        }
    }

    // POST /ai/plan
    public function generarPlan(): void
    {
        $this->requireAuth();
        $this->requirePermission(['ai.consultas.plan', 'ai.manage', 'administrativo']);

        if (!$this->configService->isFeatureEnabled(AIConfigService::FEATURE_CONSULTAS_PLAN)) {
            $this->json([
                'ok' => false,
                'error' => 'La generación asistida de planes está deshabilitada en la configuración de IA.',
            ], 403);

            return;
        }

        $plan = trim($_POST['plan'] ?? '');
        $insurance = trim($_POST['insurance'] ?? '');
        if ($plan === '' || $insurance === '') {
            $this->json(['ok' => false, 'error' => 'plan e insurance son requeridos'], 400);

            return;
        }

        $procedimiento = $_POST['procedimiento'] ?? null;
        $ojo = $_POST['ojo'] ?? null;

        try {
            $texto = $this->client()->generatePlanTratamiento($plan, $insurance, $procedimiento, $ojo);
            $this->json(['ok' => true, 'data' => $texto]);
        } catch (RuntimeException $exception) {
            $this->json(['ok' => false, 'error' => $exception->getMessage()], 503);
        } catch (Throwable $exception) {
            $this->json(['ok' => false, 'error' => $exception->getMessage()], 500);
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
