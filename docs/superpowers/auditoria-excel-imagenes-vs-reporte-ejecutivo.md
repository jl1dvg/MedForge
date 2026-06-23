# Auditoría de negocio: Excel del módulo Imágenes vs. Reporte Ejecutivo

**Alcance:** auditoría funcional, no técnica. Solo lectura — sin cambios de código.
**Excel auditado:** `GET /v2/imagenes/dashboard/export/excel` (botón "Exportar Excel" del dashboard operativo `/v2/imagenes/dashboard`). Genera 3 hojas: **Resumen KPI**, **Operación**, **Solicitudes**.
**Excel descartado del alcance:** existe un segundo export legado (`POST /examenes/reportes/excel`, hoja "Reporte", 11 columnas tipo kanban — paciente/examen/estado/turno). Es un export de listado operativo puntual, no comparable con el Reporte Ejecutivo; se menciona al final solo como nota.

---

## 1. Hoja "Resumen KPI" — matriz comparativa

| Bloque del Excel | Contenido | Sección equivalente en el Reporte Ejecutivo | Estado |
|---|---|---|---|
| Filtros aplicados | Tabla Filtro/Valor | Header del reporte (período, sede) | **Duplicado** |
| Hallazgos clave | 5 frases narrativas autogeneradas (cumplimiento, backlog, pendiente estimado, sin tarifa, SLA) | Recuadro de "Read" en Sección 02 + Recomendaciones en Sección 04 | **Duplicado parcialmente** — la frase de SLA ya no tiene equivalente (fue retirada del reporte a propósito) |
| Metodología | 10 líneas explicando cómo se calcula cada cruce (cohorte vs. operación, NAS, billing, tarifas) | No existe en el Reporte Ejecutivo (es audiencia no-ejecutiva) | **Mantener solo en Excel** — es documentación técnica de auditoría, no dato de negocio |
| Bloque 1 — Operación del período (9 KPIs: Agendas, Atendidos, Informadas, Facturados, Cumplimiento cita→realización, Cancelados, Pérdida operativa, Pendientes operativos, Pendiente de facturar) | KPIs operativos del período | Mapa Ejecutivo (Atendidos, Facturados, Pendiente facturar) + Flujo Conectado (conversión) | **Duplicado** en su mayoría; "Cancelados/Pérdida operativa/Pendientes operativos" no están en el reporte pero son variantes finas del mismo dato que ya aparece como "leak" en el flujo |
| Cumplimiento y oportunidad (SLA ≤48h, TAT prom/mediana/P90, Día pico) | Calidad de informe + tráfico | **Eliminado deliberadamente** del Reporte Ejecutivo (decisión ya tomada: TAT/SLA dejaron de ser narrativa principal) | **Solo en Excel** — útil para Radiología/operación, no para el ejecutivo |
| Economía y facturación (Producción facturada, Pendiente de facturar, Pendiente de pago, Pendiente estimado, Ticket promedio, Procedimientos facturados + fórmula de cada uno) | KPIs económicos con "qué significa" y "cómo se calcula" | Mapa Ejecutivo + Ledger + Sección 04 (Ticket promedio, Oportunidad pendiente) | **Duplicado en valores**; la columna "fórmula" es la única cosa que el reporte no tiene y no debería tener (es detalle de auditoría) |
| Top exámenes realizados | Tabla examen/casos | Sección 03 "Top exámenes" (por solicitud, no por realización — distinto matiz) | **Resumir/Migrar** — el reporte ya tiene un top exámenes; si "realizados" agrega valor real, sería una segunda serie en el mismo gráfico, no una tabla aparte |
| Backlog de facturación por categoría (Pública/Privada/Particular) | Facturados/pendiente/pendiente estimado por categoría | No existe desagregado por categoría en el reporte | **Podría migrarse** — es el único corte que el reporte no cubre (categoría de afiliación), pero es de interés más operativo que ejecutivo |
| Rendimiento económico | Repite 4 cifras ya mostradas arriba en el mismo Excel | Mapa Ejecutivo / Sección 04 | **Eliminar** — redundante incluso dentro del propio Excel |
| Desglose por empresa de seguro | Tabla empresa/estudios | Campo `porConvenio` del reporte — **existe en el payload pero ya no se renderiza** en ninguna sección visible | **Duplicado en datos, ausente en UI del reporte** — decisión pendiente: o se revive como gráfico en el reporte, o se retira definitivamente del payload |
| Bloque 2 — Solicitudes (Solicitudes, Agendadas al corte, Realizadas al corte, Realizadas posterior al corte, Cumplimiento al corte, Pérdida económica por no agendar, Sin agenda, Ausentes, Pendientes vigentes) | KPIs de la cohorte de solicitudes | Mapa Ejecutivo (Solicitados) + Flujo Conectado + Summary rows (Sin agenda, Agendadas no realizadas, Arrastre al corte) | **Duplicado en casi todo**; "Realizadas posterior al corte" es el único matiz fino sin equivalente directo, pero es detalle de auditoría de cohorte, no decisión ejecutiva |
| Top 10 doctores solicitantes | Tabla doctor/solicitudes | Sección 03 "Top doctores solicitantes" | **Duplicado exacto** |
| Top exámenes solicitados | Tabla examen/solicitudes | Sección 03 "Top exámenes" | **Duplicado exacto** |

**Lectura de esta hoja:** de ~14 bloques, **9 son duplicados directos o casi directos** de algo que el Reporte Ejecutivo ya muestra (con mejor diseño visual). Los únicos 3 bloques sin equivalente real son: Metodología (audiencia técnica), TAT/SLA (decisión consciente de excluir), y el corte por categoría de afiliación (Pública/Privada/Particular).

---

## 2. Hoja "Operación" (24 columnas, una fila por procedimiento)

Es un **detalle transaccional**: HC, paciente, empresa de seguro, afiliación, categoría, sede, estado de agenda/realización/informe, facturación, fuente de billing, montos, billing ID, archivos NAS, código y detalle tarifario.

- **No tiene equivalente en el Reporte Ejecutivo, y no debería tenerlo.** El reporte ejecutivo es por diseño agregado; esta hoja es para que Billing/Agenda concilien caso por caso (ej. "¿por qué este paciente no se facturó?", "¿tiene archivos en NAS?").
- **Sigue siendo útil** tal como está — es la razón de negocio real para mantener el Excel: trazabilidad nominal que ningún dashboard agregado puede sustituir.
- Columnas candidatas a recorte si se quiere una versión mínima: `Form ID`, `Detalle tarifario`, `Código tarifario` (jerga interna de facturación, poco usada por agenda/coordinación) — pero son necesarias para Billing.

## 3. Hoja "Solicitudes" (23 columnas, una fila por solicitud)

Mismo patrón: detalle nominal por solicitud (paciente, doctor, examen, fechas de cada hito, banderas SI/NO de agendada/realizada/informada/facturada/cancelada/ausente/pendiente).

- Tampoco tiene ni debería tener equivalente 1:1 en el reporte ejecutivo — el reporte da el conteo agregado (Sin agenda: 142), esta hoja da el listado nominal (cuáles 142, de qué doctor, qué examen).
- **Sigue siendo útil** para que Agendamiento haga seguimiento operativo real (llamar a los pacientes sin agenda, por ejemplo).

---

## 3.bis Excel legado "Reporte" (kanban)

Hoja de una sola tabla (paciente/examen/estado/prioridad/turno), generada desde otra ruta (`/examenes/reportes/excel`), aparentemente ligada a una vista de listado/kanban distinta del dashboard. No se relaciona con el Reporte Ejecutivo en absoluto — es un export operativo del día a día (turnero), fuera del alcance de "qué reemplaza el reporte ejecutivo". Se menciona solo para que quede registrado que existe y que esta auditoría no lo tocó.

---

## 4. Por qué existen partes del Excel solo por costumbre tabular

Indicios claros de que ciertos bloques de "Resumen KPI" existen porque el usuario está acostumbrado a Excel, no porque aporten un dato nuevo:

- **"Rendimiento económico"** repite 4 cifras que ya están arriba en el mismo Excel, en el mismo formato tabla — no agrega nada, solo "se ve como reporte" al tener su propia sección con encabezado.
- **Bloque 1 y Bloque 2** repiten en formato tabla KPI/Valor/Detalle lo que el Mapa Ejecutivo ya muestra como tarjetas visuales — la única diferencia es el formato (tabla vs. card), no el dato.
- **Metodología** es una necesidad real pero nace de la falta de confianza en el cálculo, típico de cuando un número "no se explica solo" — el Reporte Ejecutivo soluciona esto con mejor storytelling (hints, subtextos) en vez de un bloque de texto técnico.
- **Top exámenes / Top doctores en dos lugares (cohorte y operación)** es duplicación motivada por "más tablas = más completo", cuando en la práctica son el mismo top con una sutil diferencia de universo (solicitado vs. realizado) que casi nadie usa para decidir nada distinto.

---

## 5. Propuesta: versión mínima de Excel para usuarios operativos

Mantener el Excel, pero reducido a lo que el Reporte Ejecutivo **no puede** dar (datos nominales/transaccionales):

1. **Hoja "Operación"** — igual que hoy, sin recortes (es la hoja de trabajo real de Billing).
2. **Hoja "Solicitudes"** — igual que hoy, sin recortes (es la hoja de trabajo real de Agendamiento).
3. **Hoja "Resumen KPI" reducida a 3 bloques:**
   - Filtros aplicados (contexto mínimo de qué se exportó).
   - Metodología (porque Billing/Auditoría sí la necesita para confiar en los números nominales de las otras hojas).
   - Backlog de facturación por categoría (Pública/Privada/Particular) — el único corte agregado que hoy no vive en ningún lado del Reporte Ejecutivo.

Todo lo demás de "Resumen KPI" (Hallazgos clave, Bloque 1, Bloque 2, Cumplimiento y oportunidad, Rendimiento económico, Top exámenes/doctores, desglose por seguro) se elimina del Excel porque ya se ve mejor, con contexto y storytelling, en el Reporte Ejecutivo.

## 6. Qué debe quedar exclusivamente en el Reporte Ejecutivo (dejar de exportarse a Excel)

- Hallazgos clave (texto narrativo) — el reporte ya cuenta esa historia con mejor diseño.
- Bloque 1 y Bloque 2 de KPIs (operación y solicitudes) — totalmente cubiertos por Mapa Ejecutivo + Flujo Conectado.
- TAT/SLA — decisión ya tomada de sacarlos de la narrativa ejecutiva; si Radiología los necesita, debería ser un export aparte específico de calidad de informes, no parte del Excel "de dashboard".
- Rendimiento económico — redundante hasta dentro del propio Excel.
- Top exámenes / Top doctores (ambas variantes) — cubiertos por Sección 03.
- Desglose por empresa de seguro — aquí hay una decisión pendiente real: o se reactiva como visualización en el reporte (ya existe el campo `porConvenio` en el payload, simplemente no se está pintando), o se elimina del payload por completo. Tal como está hoy (en el payload pero sin render) es el peor escenario: vive en dos lugares y no se ve bien en ninguno.

---

## Conclusión

El Excel actual de Imágenes está, en su hoja de KPIs, **mayormente duplicado** respecto al Reporte Ejecutivo — alrededor del 65% de "Resumen KPI" es el mismo dato con otro formato. El valor real y no sustituible del Excel está en las hojas **Operación** y **Solicitudes** (detalle nominal, transaccional, para conciliación caso por caso), que ningún reporte agregado debe ni puede reemplazar. La recomendación es mantener esas dos hojas intactas, recortar "Resumen KPI" a Filtros + Metodología + Backlog por categoría, y resolver la ambigüedad pendiente del desglose por convenio (`porConvenio`) que hoy existe a medias en ambos lados.
