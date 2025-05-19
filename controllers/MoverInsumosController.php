<?php

namespace Controllers;

class MoverInsumosController
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    public function ejecutar()
    {
        // Obtiene todos los protocolos que tienen insumos
        $stmt = $this->pdo->query("SELECT id, insumos FROM protocolo_data WHERE insumos IS NOT NULL");
        $protocolos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($protocolos as $protocolo) {
            $id = $protocolo['id'];
            $json = json_decode($protocolo['insumos'], true);

            if (!isset($json['equipos'], $json['quirurgicos'])) continue;

            $equipos_actualizados = [];
            $mover = [];

            foreach ($json['equipos'] as $item) {
                if ($item['nombre'] === "PACK DE PHACO") {
                    $mover[] = $item;
                } else {
                    $equipos_actualizados[] = $item;
                }
            }

            // Solo actualizar si encontramos el insumo específico
            if (!empty($mover)) {
                $json['equipos'] = $equipos_actualizados;
                $json['quirurgicos'] = array_merge($json['quirurgicos'], $mover);

                $nuevoJson = json_encode($json, JSON_UNESCAPED_UNICODE);

                $update = $this->pdo->prepare("UPDATE protocolo_data SET insumos = ? WHERE id = ?");
                $update->execute([$nuevoJson, $id]);

                echo "Movido insumo en protocolo ID $id\n";
            }
        }

        // Ahora aplicamos la misma lógica a la tabla insumos_pack
        $stmt2 = $this->pdo->query("SELECT procedimiento_id, insumos FROM insumos_pack WHERE insumos IS NOT NULL");
        $registros = $stmt2->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($registros as $registro) {
            $id = $registro['procedimiento_id'];
            $json = json_decode($registro['insumos'], true);

            if (!isset($json['equipos'], $json['quirurgicos'])) continue;

            $equipos_actualizados = [];
            $mover = [];

            foreach ($json['equipos'] as $item) {
                if ($item['nombre'] === "PACK DE PHACO") {
                    $mover[] = $item;
                } else {
                    $equipos_actualizados[] = $item;
                }
            }

            if (!empty($mover)) {
                $json['equipos'] = $equipos_actualizados;
                $json['quirurgicos'] = array_merge($json['quirurgicos'], $mover);

                $nuevoJson = json_encode($json, JSON_UNESCAPED_UNICODE);

                $update = $this->pdo->prepare("UPDATE insumos_pack SET insumos = ? WHERE procedimiento_id = ?");
                $update->execute([$nuevoJson, $id]);

                echo "Movido insumo en insumos_pack ID $id\n";
            }
        }

        echo "Proceso finalizado.\n";
    }
}