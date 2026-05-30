# WhatsApp Reporte V2 — Rediseño de datos y estructura

**Fecha:** 2026-05-29
**Audiencias:** Supervisor operativo · Director/gerencia · Marketing
**Principio guía:** Datos correctos primero, presentación intuitiva para usuarios con poco dominio digital.

---

## Contexto

El reporte V2 (`/v2/whatsapp/dashboard`) quedó como reporte histórico tras promover V3 como dashboard en vivo. Tiene 24 secciones y más de 50 métricas mezcladas sin distinción de audiencia. Revisión de datos en producción reveló 3 bugs críticos y oportunidades de mejora para marketing.

---

## Bugs confirmados en producción

### BUG-1 — Carga por agente: filtro de fecha incorrecto (CRÍTICO)
**Problema:** `handoffsByAgent` usa `WHERE queued_at >= $from AND queued_at < $to`. Al filtrar "hoy", muestra 1 handoff activo cuando en producción hay 38 conversaciones asignadas actualmente.

**Causa:** El workload real es estado actual (`status = 'assigned'`), no volumen del día.

**Fix:** La tabla "Carga por agente" siempre muestra estado actual independiente del filtro de fecha. El filtro de fecha aplica al resto del reporte, no a esta tabla.

### BUG-2 — Barra de carga relativa al máximo (engañosa)
**Problema:** La barra se llena al 100% para el agente con más conversaciones, aunque tenga solo 1. Todos los demás aparecen proporcionalmente.

**Fix:** Cambiar a escala absoluta con cap configurable (default 10 conversaciones). La barra representa carga real, no ranking relativo.

### BUG-3 — Tiempos de respuesta: promedio + mediana inconsistente con V3
**Problema:** V3 ya usa P75. V2 muestra avg + mediana juntos. Los outliers distorsionan el promedio (ej. faq_escalada: avg 14.4 min pero max 963 min). Etiquetas `✗ Alto` y `~ OK` no comunican nada a usuarios no técnicos.

**Fix:**
- Reemplazar `AVG` por P75 en `humanAttentionByAgent` y `humanResponseByQueue`
- Eliminar columna "Promedio" de la tabla "Primera respuesta por cola" (queda solo P75)
- Cambiar etiquetas: `✗ Alto` → tiempo en minutos con semáforo rojo · `~ OK` → tiempo con amarillo

---

## Arquitectura del reporte rediseñado

Un solo reporte con **3 secciones colapsables**, cada una con título de audiencia visible. Filtro de fecha en el header aplica a Secciones 2 y 3. Sección 1 siempre muestra estado actual.

---

## Sección 1 — Operación de hoy *(Supervisor)*

**Siempre estado actual**, sin filtro de fecha.

### Paneles

**Cola activa**
- Esperando asignación (queued)
- Asignadas a agente (assigned)
- Derivados urgentes sin respuesta >24h → 🔴 badge si > 0
- Solo pueden entrar por plantilla (fuera de ventana 24h)

**Carga por agente** (tabla)
- Columnas: Agente · Asignadas · Activas · Resueltas hoy · Barra de carga
- Barra: escala absoluta 0–cap (default cap = 10, configurable en settings)
- Semáforo: verde <60% · amarillo 60–85% · rojo >85%
- Consulta: `WHERE status IN ('assigned', 'queued') AND assigned_agent_id IS NOT NULL` (sin filtro de fecha)

**¿Quién atendió más rápido?** (tabla — antes "Atención humana por agente")
- Columnas: Agente · Atendidas hoy · Tiempo P75 · Semáforo
- Sin etiquetas "✗ Alto" — solo tiempo en minutos con color
- Consulta acotada a handoffs del día para relevancia operativa

---

## Sección 2 — Rendimiento del canal *(Director)*

Filtrada por rango de fecha seleccionado.

### Números grandes (siempre visibles, no colapsables)
- **Cobertura** → % personas atendidas / personas que escribieron · semáforo
- **SLA** → % respondidos dentro de meta · semáforo
- **Atendidas** → número absoluto
- **Perdidas** → número absoluto · badge "X solicitaron ayuda" si aplica

### Detalle colapsable
- Primera respuesta por cola: Cola · Handoffs · P75 · Semáforo (eliminar columna Promedio)
- Recordatorios: solo Enviados · Entregados · Confirmaron (3 métricas, no 8)
- Cierres: Resueltos · Seguimiento · No interesados · Sin respuesta
- Derivaciones entre agentes

---

## Sección 3 — Captación y Marketing *(Marketing)*

Filtrada por rango de fecha. Organizada en 3 bloques.

### Bloque A — Lo que trajo marketing
- Total conversaciones nuevas
- Desde Ads · Orgánico · Iniciadas por equipo (3 tarjetas)
- Pacientes nuevos · Recurrentes · Reactivados
- Lead score promedio · Leads de alto valor

### Bloque B — Embudo por origen (NUEVO)
Tabla que muestra cada etapa del embudo separada por origen:

| Origen | Llegaron | Identificados | Handoff | Cita | Conversión |
|---|---|---|---|---|---|
| Ads | N | N (%) | N (%) | N (%) | % |
| Orgánico | N | N (%) | N (%) | N (%) | % |
| Equipo | N | N (%) | N (%) | N (%) | % |

**Fuente de datos:** `conversationAnalyticsBaseSubquery` cruzado con `source_category` de `whatsapp_conversation_attributions`. Porcentajes calculados sobre el total de cada fila.

**Propósito:** Marketing puede separar su conversión de la ejecución operativa. Si ads tiene 33% de handoff vs 80% del equipo, el problema es de atención no de calidad de lead.

### Bloque C — Leads perdidos por operación (NUEVO)
Frases directas, no tablas:
- "X personas de ads llegaron sin ser atendidas por un humano"
- "X personas solicitaron ayuda y no fueron asignadas a ningún agente"
- "X leads de ads tuvieron handoff pero siguen sin respuesta después de 24h"

**Fuente:** Cruzar `source_category = 'ads'` con `conversations_lost`, `conversations_abandoned_needs_human`, `conversations_abandoned_with_handoff`.

### Bloque D — Ads performance existente
Mantener la tabla de `adsPerformanceBreakdown` ya existente (origen por campaña/UTM si disponible).

---

## Fase 2 (fuera del scope actual)

- Input de gasto por campaña → CPL (costo por lead) · CPA (costo por cita agendada)
- Disponible como formulario dentro del Bloque D de marketing

---

## Cambios en KpiDashboardService

| Método | Cambio |
|---|---|
| `handoffsByAgent` | Eliminar filtro `queued_at >= $from`. Siempre consulta estado actual. |
| `humanAttentionByAgent` | `AVG` → P75 (percentile_cont o cálculo PHP sobre array ordenado) |
| `humanResponseByQueue` | Eliminar columna `avg_first_response_minutes`. Solo P75. |
| Nuevo: `conversationFunnelBySource` | Embudo agrupado por `source_category` — ads, organic, outbound |
| Nuevo: `lostLeadsBySource` | Cruzar leads de ads con `conversations_lost` y `conversations_abandoned_needs_human` |

---

## Cambios en blade `v2-dashboard.blade.php`

- Reorganizar en 3 secciones con `<details>/<summary>` o acordeón Bootstrap colapsable
- Etiquetas de audiencia visibles: "Para el supervisor", "Para gerencia", "Para marketing"
- Eliminar columna "Promedio" de tabla Primera respuesta por cola
- Reemplazar badges `✗ Alto` / `~ OK` por tiempo + semáforo de color
- Bloque B: nueva tabla embudo por origen
- Bloque C: 3 frases de "leads perdidos" con números reales

---

## Criterios de éxito

1. "Carga por agente" muestra los 38 handoffs activos reales, no 1
2. P75 de respuesta consistente con V3
3. Marketing puede leer en una tabla cuántos leads de ads convirtieron vs cuántos se perdieron por operación
4. Un usuario sin experiencia técnica entiende cada sección sin necesitar el tooltip `?`
