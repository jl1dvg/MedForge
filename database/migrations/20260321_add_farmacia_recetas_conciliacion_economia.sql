ALTER TABLE farmacia_recetas_conciliacion
    ADD COLUMN fecha_facturacion DATETIME DEFAULT NULL AFTER fecha_factura,
    ADD COLUMN cantidad_facturada DECIMAL(14,4) DEFAULT NULL AFTER producto_factura,
    ADD COLUMN precio_unitario_facturado DECIMAL(14,4) DEFAULT NULL AFTER cantidad_facturada,
    ADD COLUMN descuento_total_linea DECIMAL(14,4) DEFAULT NULL AFTER precio_unitario_facturado,
    ADD COLUMN descuento_bos_linea DECIMAL(14,4) DEFAULT NULL AFTER descuento_total_linea,
    ADD COLUMN monto_linea_neto DECIMAL(14,4) DEFAULT NULL AFTER descuento_bos_linea,
    ADD COLUMN monto_linea_unitario_neto DECIMAL(14,4) DEFAULT NULL AFTER monto_linea_neto;

DROP VIEW IF EXISTS vw_farmacia_recetas_conciliadas;

CREATE VIEW vw_farmacia_recetas_conciliadas AS
SELECT
    ri.id,
    ri.form_id,
    ri.id_ui AS receta_id_ui,
    ri.estado_receta,
    ri.producto,
    ri.vias,
    ri.unidad,
    ri.pauta,
    ri.dosis,
    ri.cantidad,
    ri.total_farmacia,
    ri.observaciones,
    ri.created_at,
    ri.updated_at AS receta_updated_at,
    frc.receta_id AS conciliacion_receta_id,
    frc.pedido_id,
    frc.cedula_paciente,
    frc.paciente,
    frc.fecha_receta,
    frc.producto_receta_id,
    frc.codigo_producto_receta,
    frc.producto_receta,
    frc.factura_id,
    frc.detalle_factura_id,
    frc.fecha_factura,
    frc.fecha_facturacion,
    frc.departamento_factura,
    frc.cedula_cliente_factura,
    frc.producto_factura_id,
    frc.codigo_producto_factura,
    frc.producto_factura,
    frc.cantidad_facturada,
    frc.precio_unitario_facturado,
    frc.descuento_total_linea,
    frc.descuento_bos_linea,
    frc.monto_linea_neto,
    frc.monto_linea_unitario_neto,
    frc.diff_dias,
    frc.tipo_match,
    frc.source_from,
    frc.source_to,
    frc.matched_at,
    frc.updated_at AS conciliacion_updated_at
FROM recetas_items ri
LEFT JOIN farmacia_recetas_conciliacion frc
    ON CONVERT(ri.id_ui USING utf8mb4) COLLATE utf8mb4_unicode_ci = frc.receta_id COLLATE utf8mb4_unicode_ci;
