<?php

namespace Models;

use PDO;

class PalabraClaveModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function listarPorDiagnostico($dx_id)
    {
        $stmt = $this->db->prepare("SELECT * FROM palabras_clave WHERE dx_id = ?");
        $stmt->execute([$dx_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crear($dx_id, $palabra)
    {
        $stmt = $this->db->prepare("INSERT INTO palabras_clave (dx_id, palabra) VALUES (?, ?)");
        $stmt->execute([$dx_id, strtolower($palabra)]);
        return $this->db->lastInsertId();
    }

    public function eliminar($id)
    {
        $stmt = $this->db->prepare("DELETE FROM palabras_clave WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function buscarCoincidencias($texto)
    {
        $stmt = $this->db->query("SELECT p.dx_id, p.palabra FROM palabras_clave p");
        $todas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $texto = strtolower($texto);
        $resultados = [];

        foreach ($todas as $item) {
            if (strpos($texto, $item['palabra']) !== false) {
                $resultados[] = $item['dx_id'];
            }
        }

        return array_unique($resultados);
    }
}