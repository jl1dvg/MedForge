# Propuesta de dashboard de Billing (análisis de viabilidad)

## 1) Datos disponibles hoy en el módulo Billing

**Fuentes principales**

- `billing_main` + detalle de ítems (procedimientos/insumos/derechos/anestesia) vía `BillingViewService`, que arma el detalle desde el legacy controller y calcula subtotales/total con IVA.【F:modules/Billing/Services/BillingViewService.php†L48-L171】
- `NoFacturadosService`: identifica procedimientos/protocolos sin factura en `billing_main`, clasifica tipo (imagen/consulta/quirúrgico) y expone filtros por fecha/afiliación/tipo.【F:modules/Billing/Services/NoFacturadosService.php†L14-L213】
- `BillingRuleService`: aplica reglas de precio por código/afiliación/edad y genera trazas en memoria (`reglas_aplicadas`).【F:modules/Billing/Services/BillingRuleService.php†L14-L121】
- `SriService`: envía facturas al SRI y persiste estado/errores/último envío/intentos en `BillingSriDocumentModel`.【F:modules/Billing/Services/SriService.php†L22-L150】
- `BillingController::crearDesdeNoFacturado`: crea facturas desde no facturados, copiando ítems y actualizando fecha de creación con la fecha de cirugía (si existe).【F:modules/Billing/Controllers/BillingController.php†L83-L189】

---

## 2) KPIs viables hoy (por bloque)

### 1) KPIs de dinero (lo que la gerencia mira primero)

**Viable hoy**
- # facturas por día/semana/mes (desde `billing_main` y `created_at`/`fecha_ordenada` en listados).【F:modules/Billing/Services/BillingViewService.php†L17-L45】
- Monto total facturado (suma de subtotales + IVA en detalle).【F:modules/Billing/Services/BillingViewService.php†L120-L171】
- Ticket promedio (total / # facturas) y ticket por tipo (imagen/consulta/quirúrgico).【F:modules/Billing/Services/NoFacturadosService.php†L23-L95】
- Monto por afiliación (usa afiliación del paciente en `BillingViewService`).【F:modules/Billing/Services/BillingViewService.php†L21-L41】

**Charts sugeridos**
- Línea: monto facturado por día.
- Barras apiladas: monto por afiliación.
- Pareto: top procedimientos por ingresos (requiere sumar ítems de procedimientos).

### 2) KPIs de fuga y control (leakage)

**Viable hoy**
- # no facturados (backlog) por tipo y afiliación (NoFacturadosService).【F:modules/Billing/Services/NoFacturadosService.php†L14-L213】
- % fuga = no facturados / (facturados + no facturados).【F:modules/Billing/Services/NoFacturadosService.php†L14-L213】
- Aging de fuga: días desde `fecha` (procedimiento/protocolo) hasta hoy (existe campo `fecha`).【F:modules/Billing/Services/NoFacturadosService.php†L23-L95】
- Recuperación: facturas creadas desde no facturados (`crearDesdeNoFacturado`).【F:modules/Billing/Controllers/BillingController.php†L83-L189】

**Charts sugeridos**
- Gauge: % fuga actual.
- Barras: no facturados por afiliación.
- Tabla top: form_id más antiguos sin facturar.

### 3) KPIs de tiempos (velocidad atención → dinero)

**Viable hoy**
- Lead time a facturar: `protocolo_data.fecha_inicio` → `billing_main.created_at` (en creación desde no facturado se setea fecha).【F:modules/Billing/Controllers/BillingController.php†L108-L136】

**Parcial**
- P50/P90 y tiempos por doctor/afiliación: requiere consolidar por `form_id` con fechas en BD.

### 4) KPIs por aseguradora (IESS/ISSFA/ISSPOL)

**Viable hoy**
- Reportes por pagador ya existen (informes IESS/ISSFA/ISSPOL). Se pueden reutilizar para comparativos de producción y mix de procedimientos.【F:modules/Billing/Controllers/InformesController.php†L316-L410】

### 5) KPIs de reglas (BillingRuleService)

**Viable hoy (runtime)**
- Regla aplicada por item y trazas en memoria (`reglas_aplicadas`).【F:modules/Billing/Services/BillingRuleService.php†L14-L121】

**No disponible aún (persistencia)**
- No hay tabla `billing_rule_trace` para medir hit-rate histórico; se recomienda persistir.

### 6) KPIs de SRI (SriService)

**Viable hoy**
- Estado de envío/autorización, errores y # intentos por factura vía `BillingSriDocumentModel` (mapeado en `BillingViewService`).【F:modules/Billing/Services/BillingViewService.php†L21-L45】【F:modules/Billing/Services/SriService.php†L22-L150】

**Charts sugeridos**
- Donut: autorizado/rechazado/pendiente.
- Pareto: top errores SRI.

### 7) Productividad del equipo

**Parcial**
- Si `billing_main` guarda `created_by`/`updated_by`, se pueden hacer rankings y retrabajo; no aparece en los servicios actuales.

---

## 3) Dashboard recomendado “Solo Billing” (layout)

**Bloque A — Dinero**
- Monto total, # facturas, ticket promedio
- Monto por afiliación
- Top procedimientos por ingresos

**Bloque B — Control (fugas)**
- No facturados + aging promedio
- Fuga por afiliación
- Top 20 no facturados más antiguos

**Bloque C — Eficiencia**
- Lead time a facturar (P50/P90)
- Lead time por afiliación
- Rescate: detectado → facturado

**Bloque D — Automatización y cumplimiento**
- Rule hit-rate (cuando exista trazabilidad persistente)
- SRI: aceptación vs rechazo + top errores

---

## 4) Ideas para mejorar datos (habilitar KPIs premium)

1. **Persistir trazas de reglas** (`billing_rule_trace`)
   - Permite hit-rate histórico, errores evitados y benchmarking por afiliación.

2. **Registrar `created_by`/`updated_by` en billing_main**
   - Habilita productividad por usuario y retrabajo.

3. **Valor estimado en no facturados**
   - Campo `valor_estimado` en `NoFacturadosService` ya existe pero se usa en 0; llenarlo con tarifario permitiría “revenue recovery”.【F:modules/Billing/Services/NoFacturadosService.php†L14-L95】

4. **Evento de rescate**
   - Registrar `no_facturado_detectado_at` y `facturado_at` para medir tiempo de rescate.

---

## 5) Mensaje para gerencia

> “El módulo de facturación de MedForge permite a la gerencia visualizar productividad, eficiencia, calidad y rentabilidad en tiempo real.”
