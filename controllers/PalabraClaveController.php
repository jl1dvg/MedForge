<?php
namespace controllers;

use models\PalabraClaveModel;
use PDO;


class PalabraClaveController
{
    private PalabraClaveModel $palabraClaveModel;

    public function __construct(PDO $pdo)
    {
        $this->palabraClaveModel = new PalabraClaveModel($pdo);
    }

    public function listarPorDx($dx_id)
    {
        return $this->palabraClaveModel->listarPorDiagnostico($dx_id);
    }

    public function crear($dx_id, $palabra)
    {
        return $this->palabraClaveModel->crear($dx_id, $palabra);
    }

    public function eliminar($id)
    {
        return $this->palabraClaveModel->eliminar($id);
    }

    public function buscarCoincidencias($texto)
    {
        return $this->palabraClaveModel->buscarCoincidencias($texto);
    }
}