# WhatsApp Reporte V2 — Round 2: Datos correctos y SLA laboral

**Fecha:** 2026-05-29
**Contexto:** Continúa el trabajo del Round 1 (spec: `2026-05-29-whatsapp-v2-report-redesign.md`). Corrige datos mal reflejados detectados en revisión visual con datos reales de producción.

---

## Problemas confirmados en producción

1. SLA mide tiempo de reloj — penaliza horas nocturnas donde nadie trabaja
2. "Sin atender" mezcla bot-resueltos (no problema) con no atendidos reales (problema)
3. "418 Requieren atención" es backlog histórico de semanas, no de hoy
4. "215 Prioridad crítica" sin contexto de antigüedad ni origen
5. "105 En espera ahora" sin desglose ni link al chat
6. Label "74% De cada 10 que pidieron agente" incorrecto — es cobertura general

---

## Arquitectura

**Enfoque A + C:** nueva clase `BusinessHoursCalculator` aislada que el servicio consume. Toda la lógica en el servicio, el blade solo renderiza.

**Archivo nuevo:**
- `laravel-app/app/Modules/Whatsapp/Support/BusinessHoursCalculator.php`

**Archivos modificados:**
- `laravel-app/app/Modules/Whatsapp/Services/KpiDashboardService.php`
- `laravel-app/resources/views/whatsapp/v2-dashboard.blade.php`

---

## Componente 1: `BusinessHoursCalculator`

**Ruta:** `laravel-app/app/Modules/Whatsapp/Support/BusinessHoursCalculator.php`

**Responsabilidad única:** dado un `Carbon $start` y `Carbon $end`, devuelve cuántos segundos transcurrieron dentro del horario laboral configurado.

**Fuente de configuración** (desde `app_settings`):
- `whatsapp_handoff_business_schedule` → JSON: `{"monday":{"enabled":true,"start":"08:00","end":"18:00"},...}`
- `whatsapp_handoff_business_timezone` → `"America/Guayaquil"`
- `whatsapp_handoff_business_holidays` → string con fechas `YYYY-MM-DD` separadas por `\n`

**API pública:**
```php
class BusinessHoursCalculator
{
    public function __construct(array $schedule, string $timezone, array $holidays) {}

    // Segundos laborales entre dos timestamps
    public function businessSecondsElapsed(Carbon $start, Carbon $end): int

    // Convierte segundos a minutos redondeados
    public function toMinutes(int $seconds): float
}
```

**Algoritmo de `businessSecondsElapsed`:**
1. Convertir `$start` y `$end` a la timezone configurada
2. Si `$end <= $start`, retornar 0
3. Iterar minuto a minuto (o por bloques de día para eficiencia) entre `$start` y `$end`
4. Para cada minuto: verificar si el día está habilitado, si no es holiday, y si la hora está dentro del rango `start`–`end` del día
5. Acumular los segundos que caen dentro del horario
6. Retornar total

**Implementación eficiente (por bloques de día):**
- Para cada día completo entre start y end: calcular la intersección del rango laboral del día con el rango total
- Sumar los segundos de esa intersección

**Instanciación en `KpiDashboardService`:**
```php
private function businessHoursCalculator(): BusinessHoursCalculator
{
    $schedule  = json_decode($this->settingValue('whatsapp_handoff_business_schedule', '{}'), true) ?? [];
    $timezone  = $this->settingValue('whatsapp_handoff_business_timezone', 'America/Guayaquil');
    $holidays  = array_filter(explode("\n", $this->settingValue('whatsapp_handoff_business_holidays', '')));
    return new BusinessHoursCalculator($schedule, $timezone, $holidays);
}
```

---

## Componente 2: SLA con tiempo laboral y tiempo reloj

**Métodos afectados en `KpiDashboardService`:**
- `humanAttentionSummary` — respuesta al paciente
- `humanAttentionByAgent` — respuesta por agente
- `humanResponseByQueue` — respuesta por cola

**Cambio:** en cada método, además de calcular `$responseSeconds` (tiempo reloj actual), calcular `$businessSeconds` usando `businessHoursCalculator()->businessSecondsElapsed($responseStart, $firstReply)`.

**Nuevas claves en el array de retorno (por agente y por cola):**
```php
'p75_first_response_minutes'          => ...,  // tiempo reloj (ya existe)
'p75_business_response_minutes'       => ...,  // tiempo laboral (nuevo)
'median_first_response_minutes'       => ...,  // tiempo reloj (ya existe)
'median_business_response_minutes'    => ...,  // tiempo laboral (nuevo)
```

**En `humanAttentionSummary` (summary global):**
```php
'avg_first_human_response_minutes'         => ...,  // reloj (ya existe)
'p75_business_first_human_response_minutes' => ..., // laboral (nuevo)
```

**Blade — mostrar ambos:**
```
115 min totales  /  42 min laborales (P75)
Mediana 31 min · 75% respondidos antes de este tiempo laboral
```
El semáforo SLA usa el tiempo laboral para el color. El tiempo reloj aparece como dato secundario en gris.

---

## Componente 3: "Sin atender" — dos tarjetas

**Nuevas claves en `humanAttentionSummary`:**
```php
'conversations_lost_needs_human'   => $lostNeedsHuman,   // needs_human=1, sin respuesta
'conversations_resolved_by_bot'    => $resolvedByBot,    // needs_human=0, sin respuesta humana
```

**Lógica de separación** (dentro del loop existente en `humanAttentionSummary`):
```php
// Donde actualmente se hace $lost++:
if ($firstReply === null) {
    $lost++;
    // Nuevo:
    if ($row->needs_human ?? false) {
        $lostNeedsHuman++;
    } else {
        $resolvedByBot++;
    }
}
```

Requiere que el subquery `inboundScopeSubquery` devuelva el campo `needs_human` de `whatsapp_conversations`.

**Blade — dos tarjetas:**
- Tarjeta roja: `$summary['conversations_lost_needs_human']` — "Necesitaban ayuda y no fueron atendidas"
- Tarjeta gris/verde: `$summary['conversations_resolved_by_bot']` — "Resueltas sin humano" (bot, baja o cierre natural)

**Fix de label:**
```
"74% De cada 10 que pidieron agente, recibieron respuesta"
→ "74% Cobertura humana del canal"
subtexto: "Personas que recibieron respuesta humana"
```

---

## Componente 4: Métricas operativas con desglose de antigüedad

**Métodos afectados:** `operationalInboxSummary`, `queueSummary`

**Nueva estructura de retorno para métricas acumuladas:**
```php
// Para cada métrica que sea estado actual sin filtro de fecha:
'operational_status_requires_attention'        => 418,    // total (ya existe)
'operational_status_requires_attention_today'  => 12,     // < 24h
'operational_status_requires_attention_week'   => 87,     // 1-7 días
'operational_status_requires_attention_older'  => 319,    // > 7 días

'live_queue_total'    => 105,   // ya existe
'live_queue_today'    => 8,     // nuevo: < 24h
'live_queue_backlog'  => 97,    // nuevo: >= 24h
'live_queue_queued'   => 23,    // ya existe
'live_queue_assigned' => 82,    // ya existe
```

**Para "Prioridad crítica" (215):**
```php
'operational_critical'        => 215,   // total actual
'operational_critical_today'  => ...,   // entraron en estado crítico hoy
'operational_critical_week'   => ...,   // 1-7 días
'operational_critical_older'  => ...,   // > 7 días
```

**Blade — número grande + pills:**
```
418  Requieren atención
🔴 12 hoy  🟡 87 esta semana  ⚪ 319 backlog
```
Cada pill es `<a href="/v2/whatsapp?...">`.

**Separación visual de "Personas que escribieron" vs "En espera ahora":**
Actualmente aparecen juntas. Se separan en dos bloques distintos con un divisor visual y etiqueta explícita:
- Bloque izquierdo: "Del periodo seleccionado" → Personas que escribieron, Atendidas, etc.
- Bloque derecho: "Estado actual (ahora mismo)" → En espera, Carga, Cola

---

## Criterios de éxito

1. P75 con 10h de espera nocturna muestra `10h reloj / 2h laborales` — el semáforo usa 2h
2. "Sin atender" son dos tarjetas: 13 rojas (reales) + 62 verdes (bot) — no 75 juntas confundiendo
3. "418 Requieren atención" muestra desglose: 12 hoy / 87 semana / 319 backlog
4. "105 En espera" tiene link a `/v2/whatsapp` con desglose hoy/backlog
5. Label "Cobertura humana del canal" en lugar de frase confusa
6. Usuarios sin experiencia técnica entienden qué acción tomar con cada número
