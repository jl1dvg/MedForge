<?php

namespace Models;

use PDO;

class BillingInsumosModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function insertar(int $billingId, array $insumo): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO billing_insumos
            (billing_id, insumo_id, codigo, nombre, cantidad, precio, iva)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $billingId,
            $insumo['id'],
            $insumo['codigo'],
            $insumo['nombre'],
            $insumo['cantidad'],
            $insumo['precio'],
            $insumo['iva']
        ]);
    }

    public function obtenerPorBillingId(int $billingId): array
    {
        $stmt = $this->db->prepare("SELECT bi.id,
                                           bi.insumo_id,
                                           bi.codigo,
                                           bi.nombre,
                                           bi.cantidad,
                                           bi.precio,
                                           bi.iva,
                                           CASE
                                               WHEN COALESCE(i.es_medicamento, 0) = 1 THEN 1
                                               ELSE COALESCE(
                                                   (
                                                       SELECT i2.es_medicamento
                                                       FROM insumos AS i2
                                                       WHERE i2.codigo_isspol = TRIM(bi.codigo)
                                                          OR i2.codigo_issfa = TRIM(bi.codigo)
                                                          OR i2.codigo_iess = TRIM(bi.codigo)
                                                          OR i2.codigo_msp = TRIM(bi.codigo)
                                                          OR i2.codigo_isspol = LPAD(TRIM(bi.codigo), 6, '0')
                                                          OR i2.codigo_issfa = LPAD(TRIM(bi.codigo), 6, '0')
                                                          OR i2.codigo_iess = LPAD(TRIM(bi.codigo), 6, '0')
                                                          OR i2.codigo_msp = LPAD(TRIM(bi.codigo), 6, '0')
                                                          OR i2.codigo_isspol = TRIM(LEADING '0' FROM TRIM(bi.codigo))
                                                          OR i2.codigo_issfa = TRIM(LEADING '0' FROM TRIM(bi.codigo))
                                                          OR i2.codigo_iess = TRIM(LEADING '0' FROM TRIM(bi.codigo))
                                                          OR i2.codigo_msp = TRIM(LEADING '0' FROM TRIM(bi.codigo))
                                                       ORDER BY COALESCE(i2.es_medicamento, 0) DESC, i2.id ASC
                                                       LIMIT 1
                                                   ),
                                                   i.es_medicamento,
                                                   0
                                               )
                                           END AS es_medicamento,
                                           COALESCE(
                                               (
                                                   SELECT i2.categoria
                                                   FROM insumos AS i2
                                                   WHERE i2.codigo_isspol = TRIM(bi.codigo)
                                                      OR i2.codigo_issfa = TRIM(bi.codigo)
                                                      OR i2.codigo_iess = TRIM(bi.codigo)
                                                      OR i2.codigo_msp = TRIM(bi.codigo)
                                                      OR i2.codigo_isspol = LPAD(TRIM(bi.codigo), 6, '0')
                                                      OR i2.codigo_issfa = LPAD(TRIM(bi.codigo), 6, '0')
                                                      OR i2.codigo_iess = LPAD(TRIM(bi.codigo), 6, '0')
                                                      OR i2.codigo_msp = LPAD(TRIM(bi.codigo), 6, '0')
                                                      OR i2.codigo_isspol = TRIM(LEADING '0' FROM TRIM(bi.codigo))
                                                      OR i2.codigo_issfa = TRIM(LEADING '0' FROM TRIM(bi.codigo))
                                                      OR i2.codigo_iess = TRIM(LEADING '0' FROM TRIM(bi.codigo))
                                                      OR i2.codigo_msp = TRIM(LEADING '0' FROM TRIM(bi.codigo))
                                                   ORDER BY COALESCE(i2.es_medicamento, 0) DESC, i2.id ASC
                                                   LIMIT 1
                                               ),
                                               i.categoria
                                           ) AS categoria
                                    FROM billing_insumos AS bi
                                    LEFT JOIN insumos AS i ON bi.insumo_id = i.id 
                                    WHERE billing_id = ?");
        $stmt->execute([$billingId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function borrarPorBillingId(int $billingId): void
    {
        $stmt = $this->db->prepare("DELETE FROM billing_insumos WHERE billing_id = ?");
        $stmt->execute([$billingId]);
    }

    public function obtenerPrecioPorAfiliacion(string $codigo, string $afiliacion, ?int $id = null): ?float
    {
        $afiliacionUpper = strtoupper($afiliacion);
        $iessVariants = [
            'IESS',
            'CONTRIBUYENTE VOLUNTARIO',
            'CONYUGE',
            'CONYUGE PENSIONISTA',
            'SEGURO CAMPESINO',
            'SEGURO CAMPESINO JUBILADO',
            'SEGURO GENERAL',
            'SEGURO GENERAL JUBILADO',
            'SEGURO GENERAL POR MONTEPIO',
            'SEGURO GENERAL TIEMPO PARCIAL',
            'HIJOS DEPENDIENTES',
        ];

        if (in_array($afiliacionUpper, $iessVariants, true)) {
            $campoPrecio = 'precio_iess';
        } else {
            $campoPrecio = match ($afiliacionUpper) {
                'ISSPOL' => 'precio_isspol',
                'ISSFA' => 'precio_issfa',
                'MSP' => 'precio_msp',
                default => 'precio_base',
            };
        }

        if ($id) {
            $stmt = $this->db->prepare("SELECT {$campoPrecio} FROM insumos WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $id]);
        } else {
            $stmt = $this->db->prepare("SELECT {$campoPrecio} FROM insumos WHERE codigo_isspol = :codigo OR codigo_issfa = :codigo OR codigo_iess = :codigo OR codigo_msp = :codigo LIMIT 1");
            $stmt->execute(['codigo' => $codigo]);
        }

        $precio = $stmt->fetchColumn();

        return $precio !== false ? (float)$precio : null;
    }
}
