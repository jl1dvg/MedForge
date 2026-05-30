# WhatsApp V2 Report Round 2 — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Corregir datos mal reflejados en el reporte V2 — SLA con horario laboral, "sin atender" real vs bot, backlog con antigüedad, labels en lenguaje llano, y semáforos con ícono.

**Architecture:** Nueva clase `BusinessHoursCalculator` aislada que el servicio consume. Toda lógica en `KpiDashboardService`, el blade solo renderiza. Deploy directo en IONOS vía `git pull`.

**Tech Stack:** PHP 8.1, Laravel 10, MySQL 8, Blade, Bootstrap 5. Servidor: `access793096920.webspace-data.io` user `u98115706` pass `JorgeAMI2018`.

---

## Mapa de archivos

| Archivo | Acción |
|---|---|
| `laravel-app/app/Modules/Whatsapp/Support/BusinessHoursCalculator.php` | Crear |
| `laravel-app/app/Modules/Whatsapp/Services/KpiDashboardService.php` | Modificar (5 métodos) |
| `laravel-app/resources/views/whatsapp/v2-dashboard.blade.php` | Modificar (5 bloques) |

---

## Task 1: Crear `BusinessHoursCalculator`

**Files:**
- Create: `laravel-app/app/Modules/Whatsapp/Support/BusinessHoursCalculator.php`

- [ ] **Step 1: Crear el archivo con implementación completa**

```php
<?php

declare(strict_types=1);

namespace App\Modules\Whatsapp\Support;

use Carbon\Carbon;

/**
 * Calcula segundos transcurridos dentro del horario laboral configurado.
 * Descuenta horas fuera de turno, días inhabilitados y festivos.
 */
class BusinessHoursCalculator
{
    /** @var array<string, array{enabled: bool, start: string, end: string}> */
    private array $schedule;
    private string $timezone;
    /** @var array<int, string> Fechas YYYY-MM-DD */
    private array $holidays;

    /**
     * @param array<string, mixed> $schedule  JSON decodificado de whatsapp_handoff_business_schedule
     * @param string               $timezone  Ej: "America/Guayaquil"
     * @param array<int, string>   $holidays  Fechas YYYY-MM-DD de whatsapp_handoff_business_holidays
     */
    public function __construct(array $schedule, string $timezone, array $holidays)
    {
        $this->schedule = $schedule;
        $this->timezone = $timezone;
        $this->holidays = array_values(array_filter($holidays));
    }

    /**
     * Segundos laborales entre dos timestamps.
     * Retorna 0 si $end <= $start o no hay horario configurado.
     */
    public function businessSecondsElapsed(Carbon $start, Carbon $end): int
    {
        if ($end->lessThanOrEqualTo($start) || empty($this->schedule)) {
            return 0;
        }

        $localStart = $start->copy()->setTimezone($this->timezone);
        $localEnd   = $end->copy()->setTimezone($this->timezone);

        $total    = 0;
        $current  = $localStart->copy()->startOfDay();
        $endDay   = $localEnd->copy()->startOfDay();

        while ($current->lessThanOrEqualTo($endDay)) {
            $dateStr = $current->format('Y-m-d');
            $dayName = strtolower($current->format('l')); // monday, tuesday...

            $dayConfig = $this->schedule[$dayName] ?? null;

            if ($dayConfig !== null
                && ($dayConfig['enabled'] ?? false)
                && !in_array($dateStr, $this->holidays, true)
            ) {
                [$sh, $sm] = explode(':', $dayConfig['start']);
                [$eh, $em] = explode(':', $dayConfig['end']);

                $workStart = $current->copy()->setTime((int) $sh, (int) $sm, 0);
                $workEnd   = $current->copy()->setTime((int) $eh, (int) $em, 0);

                $overlapStart = max($localStart->timestamp, $workStart->timestamp);
                $overlapEnd   = min($localEnd->timestamp,   $workEnd->timestamp);

                if ($overlapEnd > $overlapStart) {
                    $total += $overlapEnd - $overlapStart;
                }
            }

            $current->addDay();
        }

        return $total;
    }

    public function toMinutes(int $seconds, int $decimals = 1): float
    {
        return round($seconds / 60, $decimals);
    }
}
```

- [ ] **Step 2: Verificar sintaxis**

```bash
php -l laravel-app/app/Modules/Whatsapp/Support/BusinessHoursCalculator.php
```
Resultado esperado: `No syntax errors detected`

- [ ] **Step 3: Prueba rápida manual en tinker (opcional — confirmar lógica)**

```bash
# En servidor local
cd laravel-app && php artisan tinker --no-interaction
```
```php
use App\Modules\Whatsapp\Support\BusinessHoursCalculator;
$schedule = ["monday" => ["enabled" => true, "start" => "08:00", "end" => "18:00"],
             "tuesday"=> ["enabled" => true, "start" => "08:00", "end" => "18:00"]];
$calc = new BusinessHoursCalculator($schedule, "America/Guayaquil", []);
// Paciente escribe domingo 22:00, agente responde lunes 09:00
$start = \Carbon\Carbon::parse("2026-05-24 22:00:00", "America/Guayaquil")->utc();
$end   = \Carbon\Carbon::parse("2026-05-25 09:00:00", "America/Guayaquil")->utc();
echo $calc->businessSecondsElapsed($start, $end) / 60; // esperado: 60 min (08:00-09:00)
```

- [ ] **Step 4: Commit**

```bash
git add laravel-app/app/Modules/Whatsapp/Support/BusinessHoursCalculator.php
git commit -m "feat(whatsapp): BusinessHoursCalculator — SLA descontando horas fuera de horario laboral"
```

---

## Task 2: Integrar `BusinessHoursCalculator` en el servicio

**Files:**
- Modify: `laravel-app/app/Modules/Whatsapp/Services/KpiDashboardService.php`

- [ ] **Step 1: Agregar use statement al inicio del archivo**

Buscar el bloque de `use` statements (~línea 1-15) y agregar:

```php
use App\Modules\Whatsapp\Support\BusinessHoursCalculator;
```

- [ ] **Step 2: Agregar método factory `businessHoursCalculator()` antes de `median()`**

Localizar `private function median(` y agregar antes:

```php
private function businessHoursCalculator(): BusinessHoursCalculator
{
    $raw      = (string) $this->settingValue('whatsapp_handoff_business_schedule', '{}');
    $schedule = json_decode($raw, true);
    $schedule = is_array($schedule) ? $schedule : [];
    $timezone = (string) $this->settingValue('whatsapp_handoff_business_timezone', 'America/Guayaquil');
    $rawHols  = (string) $this->settingValue('whatsapp_handoff_business_holidays', '');
    $holidays = array_filter(array_map('trim', explode("\n", $rawHols)));
    return new BusinessHoursCalculator($schedule, $timezone, array_values($holidays));
}
```

- [ ] **Step 3: Verificar sintaxis**

```bash
php -l laravel-app/app/Modules/Whatsapp/Services/KpiDashboardService.php
```

- [ ] **Step 4: Commit**

```bash
git add laravel-app/app/Modules/Whatsapp/Services/KpiDashboardService.php
git commit -m "feat(whatsapp): inyectar BusinessHoursCalculator en KpiDashboardService"
```

---

## Task 3: `humanAttentionSummary` — SLA laboral + separar needs_human

**Files:**
- Modify: `laravel-app/app/Modules/Whatsapp/Services/KpiDashboardService.php:1161-1267`

- [ ] **Step 1: Localizar el loop foreach en `humanAttentionSummary`**

```bash
grep -n "foreach (\$rows as \$row)" laravel-app/app/Modules/Whatsapp/Services/KpiDashboardService.php | head -5
```

- [ ] **Step 2: Actualizar variables y loop**

Antes del `foreach` (alrededor de línea 1195), agregar las nuevas variables:

```php
$lostNeedsHuman  = 0;
$resolvedByBot   = 0;
$businessSeconds = [];
$bhCalc          = $this->businessHoursCalculator();
```

Dentro del loop, en el bloque `if ($firstReply !== null)` agregar cálculo de segundos laborales:

```php
if ($firstReply !== null) {
    $attended++;
    if ($waNumber !== '') {
        $peopleAttendedSet[$waNumber] = true;
    }
    if ($responseStart !== null && $firstReply->greaterThanOrEqualTo($responseStart)) {
        $clockSecs = $responseStart->diffInSeconds($firstReply);
        $responseSeconds[] = $clockSecs;
        // NUEVO: segundos laborales
        $bizSecs = $bhCalc->businessSecondsElapsed($responseStart, $firstReply);
        if ($bizSecs >= 0) {
            $businessSeconds[] = $bizSecs;
        }
    }
    if ($lastInbound !== null && $lastInbound->lessThanOrEqualTo($threshold24h)) {
        $resolved++;
    }
} else {
    $lost++;
    // NUEVO: separar needs_human
    $needsHuman = (bool) ($row->needs_human ?? false);
    if ($needsHuman) {
        $lostNeedsHuman++;
    } else {
        $resolvedByBot++;
    }
    // resto del bloque existente...
```

- [ ] **Step 3: Agregar nuevas claves al array de retorno**

En el `return [...]` de `humanAttentionSummary`, agregar al final:

```php
'conversations_lost_needs_human'              => $lostNeedsHuman,
'conversations_resolved_by_bot'               => $resolvedByBot,
'p75_business_first_human_response_minutes'   => ($p75b = $this->percentile($businessSeconds, 75)) !== null
    ? round($p75b / 60, 1) : null,
'median_business_first_human_response_minutes' => ($medb = $this->median($businessSeconds)) !== null
    ? round($medb / 60, 1) : null,
```

- [ ] **Step 4: Verificar sintaxis**

```bash
php -l laravel-app/app/Modules/Whatsapp/Services/KpiDashboardService.php
```

- [ ] **Step 5: Commit**

```bash
git add laravel-app/app/Modules/Whatsapp/Services/KpiDashboardService.php
git commit -m "feat(whatsapp): humanAttentionSummary — SLA laboral + needs_human separado"
```

---

## Task 4: `humanAttentionByAgent` y `humanResponseByQueue` — añadir P75 laboral

**Files:**
- Modify: `laravel-app/app/Modules/Whatsapp/Services/KpiDashboardService.php`

- [ ] **Step 1: Actualizar `humanAttentionByAgent`**

En el loop que agrupa por agente (donde se hace `$agents[$uid]['seconds'][] = $secs`), agregar segundos laborales en paralelo:

```php
// Antes del loop, agregar:
$bhCalc = $this->businessHoursCalculator();

// Inicializar agente con segundos laborales:
$agents[$uid] = [
    'user_id'    => $uid,
    'agent_name' => (string) ($row->agent_name ?? ''),
    'seconds'    => [],
    'biz_seconds' => [],
];

// En el loop, donde se asigna $secs:
$secs = (float) ($row->response_seconds ?? 0);
if ($secs >= 0) {
    $agents[$uid]['seconds'][] = $secs;
    // Para segundos laborales necesitamos start y end — pero el subquery solo devuelve
    // el diff en segundos, no los timestamps originales. Solución: agregar timestamps al subquery.
}
```

**NOTA:** `humanAttentionByAgent` calcula el diff en SQL sin devolver los timestamps. Para obtener el tiempo laboral se necesita devolver `assigned_at` y `first_human_reply_at` del subquery. Actualizar el SQL en `humanAttentionByAgent`:

```php
$sql = 'SELECT
            first_reply.assigned_agent_id AS user_id,
            ' . $this->agentNameSql('u', 'first_reply.assigned_agent_id', 'Usuario #') . ' AS agent_name,
            first_reply.assigned_at,
            first_reply.first_human_reply_at
        FROM (' . $reply['sql'] . ') first_reply
        LEFT JOIN users u ON u.id = first_reply.assigned_agent_id
        ORDER BY first_reply.assigned_agent_id';

$rows   = DB::select($sql, array_values($reply['params']));
$bhCalc = $this->businessHoursCalculator();
$agents = [];

foreach ($rows as $row) {
    $uid = (int) ($row->user_id ?? 0);
    if (!isset($agents[$uid])) {
        $agents[$uid] = [
            'user_id'     => $uid,
            'agent_name'  => (string) ($row->agent_name ?? ''),
            'seconds'     => [],
            'biz_seconds' => [],
        ];
    }

    $assignedAt  = isset($row->assigned_at) ? Carbon::parse((string) $row->assigned_at) : null;
    $repliedAt   = isset($row->first_human_reply_at) ? Carbon::parse((string) $row->first_human_reply_at) : null;

    if ($assignedAt !== null && $repliedAt !== null && $repliedAt->greaterThanOrEqualTo($assignedAt)) {
        $clock = $assignedAt->diffInSeconds($repliedAt);
        $biz   = $bhCalc->businessSecondsElapsed($assignedAt, $repliedAt);
        $agents[$uid]['seconds'][]     = $clock;
        $agents[$uid]['biz_seconds'][] = $biz;
    }
}

return array_values(array_map(function (array $agent): array {
    $p75    = $this->percentile($agent['seconds'], 75);
    $p75biz = $this->percentile($agent['biz_seconds'], 75);
    return [
        'user_id'                       => $agent['user_id'],
        'agent_name'                    => $agent['agent_name'],
        'attended_conversations'        => count($agent['seconds']),
        'p75_first_response_minutes'    => $p75    !== null ? round($p75    / 60, 1) : null,
        'p75_business_response_minutes' => $p75biz !== null ? round($p75biz / 60, 1) : null,
    ];
}, $agents));
```

- [ ] **Step 2: Actualizar `humanResponseByQueue`**

En el `array_map` que procesa los buckets (~línea donde están `$p75` y `$median`), agregar acumulación de `biz_seconds` durante el loop de filas y calcular P75 laboral:

```php
// En el loop foreach que llena los buckets, donde se agrega a response_seconds:
// agregar también biz_seconds al bucket:
$buckets[$queue]['biz_seconds']   = $buckets[$queue]['biz_seconds'] ?? [];

// Dentro del bloque if ($firstReply !== null):
if ($responseStart !== null && $firstReply->greaterThanOrEqualTo($responseStart)) {
    $buckets[$queue]['response_seconds'][] = $responseStart->diffInSeconds($firstReply);
    $buckets[$queue]['biz_seconds'][]      = $bhCalc->businessSecondsElapsed($responseStart, $firstReply);
}
```

Antes del loop, agregar `$bhCalc = $this->businessHoursCalculator();`

En el `array_map` que calcula P75:

```php
$rows = array_map(function (array $bucket): array {
    $seconds    = $bucket['response_seconds'];
    $bizSeconds = $bucket['biz_seconds'] ?? [];
    $p75        = $this->percentile($seconds, 75);
    $p75biz     = $this->percentile($bizSeconds, 75);
    $median     = $this->median($seconds);

    unset($bucket['response_seconds'], $bucket['biz_seconds']);
    $bucket['p75_first_response_minutes']    = $p75    !== null ? round($p75    / 60, 1) : null;
    $bucket['p75_business_response_minutes'] = $p75biz !== null ? round($p75biz / 60, 1) : null;
    $bucket['median_first_response_minutes'] = $median !== null ? round($median / 60, 1) : null;
    $bucket['response_rate'] = $bucket['total_handoffs'] > 0
        ? round(($bucket['attended_handoffs'] / $bucket['total_handoffs']) * 100, 1)
        : 0.0;
    return $bucket;
}, array_values($buckets));
```

- [ ] **Step 3: Verificar sintaxis**

```bash
php -l laravel-app/app/Modules/Whatsapp/Services/KpiDashboardService.php
```

- [ ] **Step 4: Commit**

```bash
git add laravel-app/app/Modules/Whatsapp/Services/KpiDashboardService.php
git commit -m "feat(whatsapp): P75 laboral en tiempos de agente y por cola"
```

---

## Task 5: `operationalInboxSummary` y `queueSummary` — desglose antigüedad

**Files:**
- Modify: `laravel-app/app/Modules/Whatsapp/Services/KpiDashboardService.php:1490-1633`

- [ ] **Step 1: Actualizar `operationalInboxSummary` — agregar SUM con antigüedad**

En el SQL existente (~línea 1530), agregar al SELECT:

```sql
SUM(CASE WHEN c.needs_human = 1 AND c.assigned_user_id IS NULL
          AND c.created_at >= ? THEN 1 ELSE 0 END) AS req_attention_today,
SUM(CASE WHEN c.needs_human = 1 AND c.assigned_user_id IS NULL
          AND c.created_at < ? AND c.created_at >= ? THEN 1 ELSE 0 END) AS req_attention_week,
SUM(CASE WHEN c.needs_human = 1 AND c.assigned_user_id IS NULL
          AND c.created_at < ? THEN 1 ELSE 0 END) AS req_attention_older,
SUM(CASE WHEN c.needs_human = 1 AND c.assigned_user_id IS NULL AND c.unread_count > 0
          AND c.created_at >= ? THEN 1 ELSE 0 END) AS priority_critical_today,
SUM(CASE WHEN c.needs_human = 1 AND c.assigned_user_id IS NULL AND c.unread_count > 0
          AND c.created_at < ? AND c.created_at >= ? THEN 1 ELSE 0 END) AS priority_critical_week,
SUM(CASE WHEN c.needs_human = 1 AND c.assigned_user_id IS NULL AND c.unread_count > 0
          AND c.created_at < ? THEN 1 ELSE 0 END) AS priority_critical_older,
```

Agregar al array `$params` (antes de los parámetros del filtro):

```php
$oneDayAgo  = Carbon::now()->subHours(24)->format('Y-m-d H:i:s');
$sevenDaysAgo = Carbon::now()->subDays(7)->format('Y-m-d H:i:s');
// Los params para los 8 nuevos CASE (en orden):
// req_attention_today: $oneDayAgo
// req_attention_week: $oneDayAgo, $sevenDaysAgo
// req_attention_older: $sevenDaysAgo
// priority_critical_today: $oneDayAgo
// priority_critical_week: $oneDayAgo, $sevenDaysAgo
// priority_critical_older: $sevenDaysAgo
$params = [$fourHoursAgo, $twentyFourHoursAgo,
           $oneDayAgo,
           $oneDayAgo, $sevenDaysAgo,
           $sevenDaysAgo,
           $oneDayAgo,
           $oneDayAgo, $sevenDaysAgo,
           $sevenDaysAgo];
```

Agregar al array `$defaults` y al bloque de asignación:

```php
// En $defaults:
'operational_status_requires_attention_today' => 0,
'operational_status_requires_attention_week'  => 0,
'operational_status_requires_attention_older' => 0,
'priority_critical_today'  => 0,
'priority_critical_week'   => 0,
'priority_critical_older'  => 0,

// En el bloque de asignación:
$defaults['operational_status_requires_attention_today'] = (int) ($row->req_attention_today ?? 0);
$defaults['operational_status_requires_attention_week']  = (int) ($row->req_attention_week  ?? 0);
$defaults['operational_status_requires_attention_older'] = (int) ($row->req_attention_older ?? 0);
$defaults['priority_critical_today']  = (int) ($row->priority_critical_today ?? 0);
$defaults['priority_critical_week']   = (int) ($row->priority_critical_week  ?? 0);
$defaults['priority_critical_older']  = (int) ($row->priority_critical_older ?? 0);
```

- [ ] **Step 2: Actualizar `queueSummary` — agregar today/backlog**

En el método `queueSummary`, el SQL actual devuelve solo `status` y `assigned_until`. Agregar `COALESCE(h.queued_at, h.created_at) AS entered_at` al SELECT:

```php
$sql = 'SELECT
            h.status,
            h.assigned_until,
            COALESCE(h.queued_at, h.created_at) AS entered_at
        FROM whatsapp_handoffs h
        WHERE h.status IN ("queued", "assigned", "expired")';
```

En el loop, calcular `today` vs `backlog`:

```php
$queued        = 0;
$assigned      = 0;
$overdue       = 0;
$queuedToday   = 0;
$queuedBacklog = 0;
$threshold24h  = Carbon::now()->subHours(24);

foreach ($rows as $row) {
    $status      = (string) ($row->status ?? '');
    $assignedUntil = $row->assigned_until ?? null;
    $enteredAt   = isset($row->entered_at) ? Carbon::parse((string) $row->entered_at) : null;
    $isToday     = $enteredAt !== null && $enteredAt->greaterThan($threshold24h);

    if ($status === 'queued') {
        $queued++;
        $isToday ? $queuedToday++ : $queuedBacklog++;
        continue;
    }
    if ($status !== 'assigned') { continue; }
    if ($assignedUntil === null || (string) $assignedUntil > $now) {
        $assigned++;
        $isToday ? $queuedToday++ : $queuedBacklog++;
        continue;
    }
    $overdue++;
    $isToday ? $queuedToday++ : $queuedBacklog++;
}

return [
    'live_queue_queued'          => $queued,
    'live_queue_assigned'        => $assigned,
    'live_queue_assigned_overdue'=> $overdue,
    'live_queue_total'           => $queued + $assigned + $overdue,
    'live_queue_today'           => $queuedToday,
    'live_queue_backlog'         => $queuedBacklog,
];
```

- [ ] **Step 3: Verificar sintaxis**

```bash
php -l laravel-app/app/Modules/Whatsapp/Services/KpiDashboardService.php
```

- [ ] **Step 4: Commit**

```bash
git add laravel-app/app/Modules/Whatsapp/Services/KpiDashboardService.php
git commit -m "feat(whatsapp): desglose de antigüedad en métricas operativas (hoy/semana/backlog)"
```

---

## Task 6: Blade — "Sin atender" en 2 tarjetas + fix label cobertura

**Files:**
- Modify: `laravel-app/resources/views/whatsapp/v2-dashboard.blade.php`

- [ ] **Step 1: Localizar las 4 tarjetas grandes de gerencia**

```bash
grep -n "coverageRate\|lostBig\|Cobertura\|Sin atender" laravel-app/resources/views/whatsapp/v2-dashboard.blade.php | head -10
```

- [ ] **Step 2: Reemplazar tarjeta "Sin atender" por 2 tarjetas + fix label Cobertura**

Buscar el bloque `@php` que define `$coverageRate`, `$coverageColor`, `$slaRateBig`, `$lostBig` y la fila de 4 tarjetas. Reemplazar con:

```blade
@php
    $coverageRate  = (float) ($summary['attention_rate'] ?? 0);
    $coverageColor = $coverageRate >= 80 ? 'success' : ($coverageRate >= 60 ? 'warning' : 'danger');
    $slaRateBig    = (float) ($summary['sla_response_rate'] ?? 0);
    $slaColorBig   = $slaRateBig >= 80 ? 'success' : ($slaRateBig >= 60 ? 'warning' : 'danger');
    $lostReal      = (int) ($summary['conversations_lost_needs_human'] ?? ($summary['conversations_lost'] ?? 0));
    $resolvedBot   = (int) ($summary['conversations_resolved_by_bot'] ?? 0);
    $lostIcon      = $lostReal > 0 ? '🔴' : '🟢';
    $p75biz        = $summary['p75_business_first_human_response_minutes'] ?? null;
    $p75clock      = $summary['median_first_human_response_minutes'] ?? null;
@endphp
<div class="row g-3 mb-2">
    <div class="col-6 col-md-3">
        <div class="text-center p-3 rounded border border-{{ $coverageColor }}">
            <div style="font-size:1.4rem">{{ $coverageRate >= 80 ? '🟢' : ($coverageRate >= 60 ? '🟡' : '🔴') }}</div>
            <div style="font-size:2rem;font-weight:700;color:var(--bs-{{ $coverageColor }})">{{ $coverageRate }}%</div>
            <div class="fw-semibold">Cobertura humana del canal</div>
            <div class="text-muted" style="font-size:12px">{{ $coverageRate }} de cada 100 personas que escribieron recibieron respuesta de un agente</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="text-center p-3 rounded border border-{{ $slaColorBig }}">
            <div style="font-size:1.4rem">{{ $slaRateBig >= 80 ? '🟢' : ($slaRateBig >= 60 ? '🟡' : '🔴') }}</div>
            <div style="font-size:2rem;font-weight:700;color:var(--bs-{{ $slaColorBig }})">{{ $slaRateBig }}%</div>
            <div class="fw-semibold">SLA</div>
            <div class="text-muted" style="font-size:12px">Solo {{ $slaRateBig }} de cada 100 respondidos dentro de los {{ $filters['sla_target_minutes'] ?? 15 }} min acordados</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="text-center p-3 rounded border {{ $lostReal > 0 ? 'border-danger' : 'border-secondary' }}">
            <div style="font-size:1.4rem">{{ $lostIcon }}</div>
            <div style="font-size:2rem;font-weight:700;{{ $lostReal > 0 ? 'color:var(--bs-danger)' : '' }}">{{ $lostReal }}</div>
            <div class="fw-semibold">Pidieron ayuda y no fueron atendidas</div>
            <div class="text-muted" style="font-size:12px">Conversaciones con solicitud de agente sin respuesta humana</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="text-center p-3 rounded border border-secondary">
            <div style="font-size:1.4rem">✅</div>
            <div style="font-size:2rem;font-weight:700">{{ $resolvedBot }}</div>
            <div class="fw-semibold">Resueltas sin agente</div>
            <div class="text-muted" style="font-size:12px">Bot, baja de lead o cierre natural del paciente — no requieren acción</div>
        </div>
    </div>
</div>
@if($p75biz !== null || $p75clock !== null)
<div class="alert alert-light border mb-2 py-2 px-3" style="font-size:13px">
    ⏱️ Tiempo de primera respuesta:
    <strong>{{ $p75biz !== null ? $p75biz . ' min laborales' : '—' }}</strong>
    <span class="text-muted ms-1">/ {{ $p75clock !== null ? $p75clock . ' min en reloj' : '—' }}</span>
    <span class="text-muted ms-2">(P75 · horario L-S 08:00-18:00)</span>
</div>
@endif
```

- [ ] **Step 3: Verificar sintaxis**

```bash
php -l laravel-app/resources/views/whatsapp/v2-dashboard.blade.php
```

- [ ] **Step 4: Commit**

```bash
git add laravel-app/resources/views/whatsapp/v2-dashboard.blade.php
git commit -m "feat(whatsapp): 2 tarjetas sin atender + label cobertura + tiempo laboral vs reloj"
```

---

## Task 7: Blade — Métricas operativas con pills de antigüedad y links

**Files:**
- Modify: `laravel-app/resources/views/whatsapp/v2-dashboard.blade.php`

- [ ] **Step 1: Localizar el panel "Bandejas operativas"**

```bash
grep -n "Bandejas operativas\|status_requires_attention\|priority_critical\|Requieren atención\|Prioridad crítica" laravel-app/resources/views/whatsapp/v2-dashboard.blade.php | head -15
```

- [ ] **Step 2: Reemplazar las tarjetas operativas**

Localizar donde se renderiza `$summary['operational_status_requires_attention']` y `$summary['priority_critical']`. Reemplazar cada tarjeta por versión con pills:

```blade
{{-- Requieren atención --}}
<div class="wa-now-card wa-now-card--{{ ((int)($summary['operational_status_requires_attention'] ?? 0)) > 0 ? 'alert' : 'ok' }}">
    <div class="wa-now-card__value">{{ $summary['operational_status_requires_attention'] ?? 0 }}</div>
    <div class="wa-now-card__label">Requieren atención</div>
    <div class="mt-1" style="font-size:11px;line-height:1.8">
        @if(($summary['operational_status_requires_attention_today'] ?? 0) > 0)
            <a href="/v2/whatsapp?needs_human=1&since=today" class="badge bg-danger text-decoration-none">🔴 {{ $summary['operational_status_requires_attention_today'] }} hoy</a>
        @endif
        @if(($summary['operational_status_requires_attention_week'] ?? 0) > 0)
            <a href="/v2/whatsapp?needs_human=1&since=week" class="badge bg-warning text-dark text-decoration-none ms-1">🟡 {{ $summary['operational_status_requires_attention_week'] }} esta semana</a>
        @endif
        @if(($summary['operational_status_requires_attention_older'] ?? 0) > 0)
            <span class="badge bg-secondary ms-1">⚪ {{ $summary['operational_status_requires_attention_older'] }} backlog</span>
        @endif
    </div>
</div>

{{-- Prioridad crítica --}}
<div class="wa-now-card wa-now-card--{{ ($summary['priority_critical'] ?? 0) > 0 ? 'alert' : 'ok' }}">
    <div class="wa-now-card__value">{{ $summary['priority_critical'] ?? 0 }}</div>
    <div class="wa-now-card__label">Prioridad crítica</div>
    <div class="mt-1" style="font-size:11px;line-height:1.8">
        @if(($summary['priority_critical_today'] ?? 0) > 0)
            <a href="/v2/whatsapp?priority=critical&since=today" class="badge bg-danger text-decoration-none">🔴 {{ $summary['priority_critical_today'] }} hoy</a>
        @endif
        @if(($summary['priority_critical_week'] ?? 0) > 0)
            <span class="badge bg-warning text-dark ms-1">🟡 {{ $summary['priority_critical_week'] }} esta semana</span>
        @endif
        @if(($summary['priority_critical_older'] ?? 0) > 0)
            <span class="badge bg-secondary ms-1">⚪ {{ $summary['priority_critical_older'] }} backlog antiguo</span>
        @endif
    </div>
</div>
```

- [ ] **Step 3: Verificar sintaxis**

```bash
php -l laravel-app/resources/views/whatsapp/v2-dashboard.blade.php
```

- [ ] **Step 4: Commit**

```bash
git add laravel-app/resources/views/whatsapp/v2-dashboard.blade.php
git commit -m "feat(whatsapp): pills hoy/semana/backlog en métricas operativas con links"
```

---

## Task 8: Blade — "En espera ahora" con desglose y link

**Files:**
- Modify: `laravel-app/resources/views/whatsapp/v2-dashboard.blade.php`

- [ ] **Step 1: Localizar tarjeta "En espera ahora"**

```bash
grep -n "live_queue_total\|En espera ahora\|Ver conversaciones" laravel-app/resources/views/whatsapp/v2-dashboard.blade.php | head -10
```

- [ ] **Step 2: Reemplazar con versión desglosada**

```blade
<div class="wa-now-card wa-now-card--{{ ($summary['live_queue_total'] ?? 0) > 0 ? 'warn' : 'ok' }}">
    <div class="wa-now-card__value">
        <a href="/v2/whatsapp?status=needs_human" style="color:inherit;text-decoration:none">
            {{ $summary['live_queue_total'] ?? 0 }}
        </a>
    </div>
    <div class="wa-now-card__label">En espera ahora</div>
    <div class="mt-1" style="font-size:11px;line-height:1.8">
        @if(($summary['live_queue_today'] ?? 0) > 0)
            <a href="/v2/whatsapp?status=needs_human&since=today" class="badge bg-success text-decoration-none">🟢 {{ $summary['live_queue_today'] }} hoy</a>
        @endif
        @if(($summary['live_queue_backlog'] ?? 0) > 0)
            <span class="badge bg-secondary ms-1">⚪ {{ $summary['live_queue_backlog'] }} backlog</span>
        @endif
        <div style="margin-top:3px">
            Cola {{ $summary['live_queue_queued'] ?? 0 }} · Asignadas {{ $summary['live_queue_assigned'] ?? 0 }}
        </div>
    </div>
    <a href="/v2/whatsapp" class="wa-now-card__action" style="font-size:11px">Ver conversaciones →</a>
</div>
```

- [ ] **Step 3: Separar visualmente "Del periodo" vs "Estado ahora"**

Buscar donde aparecen juntas las tarjetas `live_queue_total` y `people_inbound`. Agregar un divisor entre el bloque de métricas del periodo y el bloque de estado actual:

```blade
<div class="col-12">
    <div style="border-top:1px solid #e2e8f0;margin:8px 0;display:flex;align-items:center;gap:10px">
        <span style="font-size:11px;color:#94a3b8;white-space:nowrap;font-weight:600">DEL PERIODO SELECCIONADO</span>
        <div style="flex:1;border-top:1px solid #e2e8f0"></div>
        <span style="font-size:11px;color:#94a3b8;white-space:nowrap;font-weight:600">ESTADO ACTUAL (AHORA MISMO)</span>
    </div>
</div>
```

- [ ] **Step 4: Verificar sintaxis**

```bash
php -l laravel-app/resources/views/whatsapp/v2-dashboard.blade.php
```

- [ ] **Step 5: Commit**

```bash
git add laravel-app/resources/views/whatsapp/v2-dashboard.blade.php
git commit -m "feat(whatsapp): en espera ahora con desglose hoy/backlog, links y separador visual"
```

---

## Task 9: Blade — Tablas de agente y cola con tiempo laboral vs reloj

**Files:**
- Modify: `laravel-app/resources/views/whatsapp/v2-dashboard.blade.php`

- [ ] **Step 1: Actualizar tabla "¿Quién atendió más rápido?" (atención humana por agente)**

Localizar el `<th>1ra respuesta</th>` y la celda correspondiente. Reemplazar con dos columnas:

```blade
{{-- thead --}}
<th>Agente</th>
<th>Atendidas</th>
<th>P75 laboral</th>
<th>P75 reloj</th>
<th style="min-width:120px">Semáforo</th>
```

```blade
{{-- tbody --}}
@php
    $minsLab   = $row['p75_business_response_minutes'] ?? null;
    $minsClock = $row['p75_first_response_minutes'] ?? null;
    $slaM      = (int)($filters['sla_target_minutes'] ?? 15);
    $mins      = $minsLab ?? $minsClock;
    $color     = $mins === null ? 'green' : ($mins > $slaM * 2 ? 'red' : ($mins > $slaM ? 'yellow' : 'green'));
@endphp
<tr>
    <td>{{ $row['agent_name'] }}</td>
    <td>{{ $row['attended_conversations'] }}</td>
    <td class="wa-prog-val--{{ $color }}">{{ $minsLab !== null ? $minsLab . ' min' : '—' }}</td>
    <td class="text-muted" style="font-size:12px">{{ $minsClock !== null ? $minsClock . ' min' : '—' }}</td>
    <td>
        <div class="wa-prog-wrap">
            <div class="wa-prog-bg">
                <div class="wa-prog-fill wa-prog-fill--{{ $color }}"
                     style="width:{{ $mins !== null ? min(100, (int)round(($mins / ($slaM * 2)) * 100)) : 0 }}%"></div>
            </div>
        </div>
    </td>
</tr>
```

- [ ] **Step 2: Actualizar tabla "Primera respuesta por cola"**

Agregar columna `P75 laboral` junto a la columna `P75` existente (que pasa a llamarse `P75 reloj`):

```blade
{{-- thead --}}
<th>Cola</th>
<th>Handoffs</th>
<th>Atendidos</th>
<th>Pendientes</th>
<th>P75 laboral</th>
<th>P75 reloj</th>
<th style="min-width:120px">SLA</th>
```

```blade
{{-- tbody --}}
@php
    $p75biz   = $row['p75_business_response_minutes'];
    $p75clock = $row['p75_first_response_minutes'];
    $p75      = $p75biz ?? $p75clock;
    $pct      = $p75 !== null ? min(100, (int)round(($p75 / ($slaMeta * 2)) * 100)) : 0;
    $color    = $p75 === null ? 'green' : ($p75 > $slaMeta * 2 ? 'red' : ($p75 > $slaMeta ? 'yellow' : 'green'));
@endphp
<tr>
    <td>{{ $row['label'] }}</td>
    <td>{{ $row['total_handoffs'] }}</td>
    <td>{{ $row['attended_handoffs'] }} · {{ $row['response_rate'] }}%</td>
    <td>{{ $row['pending_handoffs'] }}</td>
    <td class="wa-prog-val--{{ $color }}">{{ $p75biz !== null ? $p75biz . ' min' : '—' }}</td>
    <td class="text-muted" style="font-size:12px">{{ $p75clock !== null ? $p75clock . ' min' : '—' }}</td>
    <td>
        <div class="wa-prog-wrap">
            <div class="wa-prog-bg">
                <div class="wa-prog-fill wa-prog-fill--{{ $color }}" style="width:{{ $pct }}%"></div>
            </div>
        </div>
    </td>
</tr>
```

Agregar nota al pie de la tabla:

```blade
<tfoot>
<tr><td colspan="7" class="text-muted py-2 px-3" style="font-size:11px">
    ⏱️ El tiempo laboral descuenta horas fuera del horario de atención (L-S 08:00-18:00, zona Guayaquil)
</td></tr>
</tfoot>
```

- [ ] **Step 3: Verificar sintaxis**

```bash
php -l laravel-app/resources/views/whatsapp/v2-dashboard.blade.php
```

- [ ] **Step 4: Commit**

```bash
git add laravel-app/resources/views/whatsapp/v2-dashboard.blade.php
git commit -m "feat(whatsapp): tablas con P75 laboral y P75 reloj columnas separadas"
```

---

## Task 10: Deploy y verificación en IONOS

- [ ] **Step 1: Push a main**

```bash
git push origin main
```

- [ ] **Step 2: Pull en IONOS**

```bash
sshpass -p 'JorgeAMI2018' ssh u98115706@access793096920.webspace-data.io \
"cd medforge && git pull origin main && echo 'OK'"
```

- [ ] **Step 3: Verificar PHP 8.1 sin errores**

```bash
sshpass -p 'JorgeAMI2018' ssh u98115706@access793096920.webspace-data.io \
"php8.1 -l /homepages/26/d793096920/htdocs/medforge/laravel-app/app/Modules/Whatsapp/Support/BusinessHoursCalculator.php && \
 php8.1 -l /homepages/26/d793096920/htdocs/medforge/laravel-app/app/Modules/Whatsapp/Services/KpiDashboardService.php && \
 php8.1 -l /homepages/26/d793096920/htdocs/medforge/laravel-app/resources/views/whatsapp/v2-dashboard.blade.php && \
 echo 'Todos OK'"
```
Resultado esperado: `Todos OK`

- [ ] **Step 4: Verificar en browser**

Abrir `https://cive.consulmed.me/v2/whatsapp/dashboard` con filtro "Hoy" y verificar:
1. Los 4 KPIs grandes muestran frases en lenguaje llano bajo cada número
2. "Requieren atención" tiene pills 🔴 hoy / 🟡 semana / ⚪ backlog
3. "En espera ahora" tiene desglose hoy/backlog y es clickeable
4. Tabla "Primera respuesta por cola" tiene columnas "P75 laboral" y "P75 reloj"
5. "Sin atender" muestra 2 tarjetas: roja (needs_human) y verde (bot)
6. Nota al pie: "⏱️ El tiempo laboral descuenta horas fuera del horario..."

---

## Self-Review

**Spec coverage:**
- ✅ BusinessHoursCalculator → Task 1-2
- ✅ SLA laboral en humanAttentionSummary → Task 3
- ✅ SLA laboral en agente y cola → Task 4
- ✅ Desglose antigüedad operacional → Task 5
- ✅ 2 tarjetas sin atender + label cobertura → Task 6
- ✅ Pills operativas con links → Task 7
- ✅ En espera con desglose + link → Task 8
- ✅ Tablas con P75 laboral vs reloj → Task 9
- ✅ Deploy IONOS → Task 10
- ✅ Frases llanas por KPI → Task 6

**Consistencia de tipos:**
- `p75_business_response_minutes` → float|null en servicio, mismo nombre en blade
- `conversations_lost_needs_human` / `conversations_resolved_by_bot` → int en servicio, usados en blade
- `live_queue_today` / `live_queue_backlog` → int, usados en blade
- `operational_status_requires_attention_today/week/older` → int, usados en blade
