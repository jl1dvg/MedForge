<?php
namespace Controllers;

use Helpers\OpenAIHelper;

class AIController
{
    private OpenAIHelper $ai;

    public function __construct()
    {
        $this->ai = new OpenAIHelper();
    }

    // POST /ai/enfermedad
    public function generarEnfermedad()
    {
        $examen = trim($_POST['examen_fisico'] ?? '');
        if ($examen === '') {
            return $this->json(['ok' => false, 'error' => 'examen_fisico es requerido'], 400);
        }

        try {
            $texto = $this->ai->generateEnfermedadProblemaActual($examen);
            return $this->json(['ok' => true, 'data' => $texto]);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // POST /ai/plan
    public function generarPlan()
    {
        $plan = trim($_POST['plan'] ?? '');
        $insurance = trim($_POST['insurance'] ?? '');
        if ($plan === '' || $insurance === '') {
            return $this->json(['ok' => false, 'error' => 'plan e insurance son requeridos'], 400);
        }

        try {
            $texto = $this->ai->generatePlanTratamiento($plan, $insurance);
            return $this->json(['ok' => true, 'data' => $texto]);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function json(array $payload, int $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}