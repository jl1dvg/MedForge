<?php

namespace controllers;

use models\DiagnosticoModel;
use PDO;

class DiagnosticoController
{
    private DiagnosticoModel $diagnosticoModel;

    public function __construct(PDO $pdo)
    {
        $this->diagnosticoModel = new DiagnosticoModel($pdo);
    }

    public function listar()
    {
        return $this->diagnosticoModel->listar();
    }

    public function obtenerPorId($dx_id)
    {
        return $this->diagnosticoModel->obtenerPorId($dx_id);
    }
}