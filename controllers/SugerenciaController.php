<?php

namespace controllers;

use controllers\PalabraClaveController;
use controllers\DiagnosticoController;
use PDO;

class SugerenciaController
{
    private PalabraClaveController $palabraClaveController;
    private DiagnosticoController $diagnosticoController;

    public function __construct(PDO $pdo)
    {
        $this->palabraClaveController = new PalabraClaveController($pdo);
        $this->diagnosticoController = new DiagnosticoController($pdo);
    }

    public function sugerirDiagnosticos($texto)
    {
        $dx_ids = $this->palabraClaveController->buscarCoincidencias($texto);
        $sugerencias = [];

        foreach ($dx_ids as $dx_id) {
            $dx = $this->diagnosticoController->obtenerPorId($dx_id);
            if ($dx && $dx['active']) {
                $sugerencias[] = [
                    'dx_id' => $dx['dx_id'],
                    'dx_code' => $dx['dx_code'],
                    'descripcion' => $dx['short_desc']
                ];
            }
        }

        return $sugerencias;
    }
}