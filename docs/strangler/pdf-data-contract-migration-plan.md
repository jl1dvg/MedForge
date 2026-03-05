# MedForge — Migración de lógica de datos para PDFs (Legacy ➜ Laravel)

## Objetivo
Centralizar en Laravel la **preparación de datos clínicos** para impresión de PDFs (no solo diseño), de forma que 012A/012B/Protocolo/Referencia/002 y demás documentos consuman la misma capa de datos normalizados.

---

## Principios
1. **Una sola verdad de datos** para todos los PDFs.
2. **Normalización robusta de profesionales** (incluyendo tokens SNS y variantes).
3. **Validación previa obligatoria** antes de renderizar PDF.
4. **Paridad controlada** con legacy (shadow mode) antes del cutover.
5. **Rollback por flag** por tipo de documento.

---

## Alcance inicial (Wave 1)
- 012A
- Protocolo
- Referencia
- 002

> 012B queda como referencia operativa (ya tiene partes más robustas de resolución de assets), pero también se incorporará a la capa unificada.

---

## Data Contract unificado (v1)
Cada documento debe recibir un payload normalizado con estos bloques:

### A) Paciente
- hc_number
- nombres/apellidos
- sexo
- fecha_nacimiento
- edad
- afiliacion

### B) Contexto clínico
- form_id
- fecha_consulta
- hora_consulta
- diagnosticos[] (code, description)
- procedimientos[]
- examenes[]

### C) Profesional responsable (crítico)
- doctor_user_id
- doctor_fname / doctor_mname / doctor_lname / doctor_lname2
- doctor_cedula
- doctor_signature_path (firma)
- doctor_firma (sello)
- doctor_signature_resolved_url
- doctor_seal_resolved_url

### D) Metadatos de auditoría
- source_module
- source_query_strategy
- resolution_warnings[]
- generated_at

---

## Reglas de normalización (v1)
1. Limpiar nombre del doctor removiendo marcador SNS en variantes:
   - `SNS` token suelto
   - `SNS-...`, `SNS_...`, `SNS...` prefijo
   - `(...SNS...)`
2. Resolver doctor por:
   - `nombre_norm`
   - `nombre_norm_rev`
   - variantes limpias
3. Si no hay firma en consulta, fallback a `users.signature_path` / `users.firma`.
4. Resolver rutas de firma/sello a URL absoluta utilizable por renderer.
5. Si falta `doctor_user_id` o firma/sello, registrar warning estructurado.

---

## Validaciones previas por documento

### 012A
- Requiere: paciente básico + doctor + (firma o sello).
- Diagnósticos no vacíos (o warning explícito de fallback).

### Protocolo
- Requiere: profesional principal + firma/sello válido.

### Referencia
- Requiere: identificación de profesional + firma/sello.

### 002
- Requiere: datos clínicos mínimos + identificación del firmante.

Si falla validación crítica:
- No render silencioso.
- Respuesta controlada + `error_id`.
- Log estructurado para corrección.

---

## Estrategia de implementación

## Fase 0 — Contrato y observabilidad (hoy)
- Definir contrato v1 y checklist de campos críticos.
- Definir estructura de logs de resolución (`pdf_data_resolution`).

## Fase 1 — Servicio Laravel de normalización
- Crear servicio unificado (módulo Shared) para:
  - resolver contexto clínico
  - resolver profesional
  - normalizar firmas/sellos
  - emitir warnings

## Fase 2 — Adaptadores por documento
- 012A adapter
- Protocolo adapter
- Referencia adapter
- 002 adapter

## Fase 3 — Shadow mode
- Generar payload Laravel en paralelo al legacy.
- Comparar campos críticos (no pixel-perfect):
  - doctor_user_id
  - nombres
  - firma/sello resueltos
  - diagnósticos

## Fase 4 — Cutover por flags
- `PDF_SOURCE_012A=laravel|legacy`
- `PDF_SOURCE_PROTOCOLO=laravel|legacy`
- `PDF_SOURCE_REFERENCIA=laravel|legacy`
- `PDF_SOURCE_002=laravel|legacy`

## Fase 5 — Retiro gradual legacy
- Mantener fallback 1–2 semanas.
- Retirar rutas legacy al estabilizar.

---

## Métricas de aceptación
1. 0 casos con firma/sello vacíos por error de match SNS.
2. ≥ 99% de documentos sin warning crítico.
3. Paridad funcional validada en muestra mínima de 30 casos por documento.
4. Tiempo medio de generación no peor que legacy (+10% máximo).

---

## Riesgos y mitigación
- Riesgo: múltiples fuentes de datos inconsistentes.
  - Mitigación: prioridad de fuentes + warnings estructurados.
- Riesgo: rutas de firma no accesibles por renderer.
  - Mitigación: resolver asset absoluto y fallback controlado.
- Riesgo: regresión por documento.
  - Mitigación: cutover por flag por documento (no big-bang).

---

## Próximo entregable inmediato
- Implementar servicio base de normalización en Laravel (Shared) con:
  - `normalizeDoctorName()`
  - `buildDoctorVariants()`
  - `resolveDoctorFromUsers()`
  - `resolveSignatureAssets()`
  - `validateCriticalFields()`
