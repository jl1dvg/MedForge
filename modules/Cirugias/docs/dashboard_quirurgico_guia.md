# Guia de uso - Dashboard Quirurgico

## Objetivo del dashboard
Este panel permite monitorear en un solo lugar la produccion quirurgica, la calidad de la ejecucion y el estado operativo de las solicitudes.

## Como usarlo en 3 pasos
1. Seleccione el rango de fechas (`Desde` y `Hasta`).
2. Aplique filtros de `Afiliacion` y/o `Categoria afiliacion` si desea segmentar.
3. Revise primero las tarjetas KPI y luego los graficos para detectar causas.

## Filtros disponibles
- **Desde / Hasta**: limita el periodo analizado.
- **Afiliacion**: filtra por convenio especifico (por ejemplo IESS, particular, etc.).
- **Categoria afiliacion**: agrupa para analisis rapido (`Publica`, `Privada`, etc.).

## Que significa cada tarjeta KPI
- **Cirugias en el periodo**: total de protocolos quirurgicos en el rango.
- **Protocolos revisados**: protocolos completos y validados.
- **Cirugias sin facturar**: cirugias con protocolo pero sin registro de facturacion.
- **Duracion promedio**: tiempo quirurgico promedio calculado entre `hora_inicio` y `hora_fin`.
- **Cumplimiento programacion**: `(realizadas / programadas) x 100`.
- **Tasa de suspendidas**: `(suspendidas / programadas) x 100`.
- **Tasa de reprogramacion**: `(reprogramadas / programadas) x 100`.
- **Reingreso mismo CIE-10**: porcentaje de reingreso por mismo diagnostico CIE-10.
- **Tiempo solicitud -> cirugia**: promedio de dias entre solicitud y cirugia confirmada.
- **Backlog sin resolucion**: solicitudes sin cirugia confirmada y no suspendidas.
- **Edad promedio backlog**: dias promedio de antiguedad del backlog.
- **Completadas con evidencia**: porcentaje de solicitudes completadas que tienen protocolo confirmado.
- **Concordancia de lateralidad**: porcentaje de coincidencia entre lateralidad solicitada y realizada.
- **Cirugias sin solicitud previa**: cirugias realizadas sin solicitud registrada previa.

## Que significa cada grafico
- **Cirugias por mes**: tendencia de volumen en el tiempo.
- **Estado de protocolos**: distribucion entre revisado, no revisado e incompleto.
- **Top procedimientos**: procedimientos con mayor volumen.
- **Top doctores solicitantes (realizadas)**:
  - considera solo solicitudes con cirugia confirmada;
  - toma doctor prioritariamente desde `procedimiento_proyectado.doctor`;
  - si no existe, usa `solicitud_procedimiento.doctor`.
- **Cirugias por convenio**: distribucion por afiliacion/convenio.

## Exportacion
- **Descargar PDF**: resumen ejecutivo de KPIs y filtros aplicados.
- **Descargar Excel**: detalle tabular de KPIs y filtros aplicados.

## Recomendaciones de interpretacion
- Revise **Cumplimiento**, **Suspendidas** y **Backlog** en conjunto, no por separado.
- Si sube **sin facturar**, priorice cierre administrativo.
- Si baja **concordancia de lateralidad**, audite calidad de registro clinico.
- Para comparativos de negocio, use `Categoria afiliacion` (Publica vs Privada).

## Nota de datos
Los resultados dependen de la calidad y completitud de carga en:
- solicitudes,
- procedimiento proyectado,
- protocolo quirurgico,
- metadatos de confirmacion de cirugia.
