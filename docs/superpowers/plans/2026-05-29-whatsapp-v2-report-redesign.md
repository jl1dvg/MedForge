# WhatsApp V2 Report Redesign — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Corregir 3 bugs de datos en el reporte V2 y reorganizarlo en 3 secciones por audiencia (supervisor, director, marketing) incluyendo embudo por origen y métricas de leads perdidos para marketing.

**Architecture:** Todos los cambios viven en dos archivos: `KpiDashboardService.php` (lógica de datos) y `v2-dashboard.blade.php` (presentación). No se crean rutas ni controladores nuevos — el endpoint existente `/v2/whatsapp/dashboard` sirve la misma data con nuevas claves en el array de retorno.

**Tech Stack:** PHP 8.1+, Laravel 10, MySQL 8, Blade, Bootstrap 5

**Deploy:** El servidor IONOS no tiene npm. Para cambios solo PHP/Blade: `git pull` en servidor. Para cambios CSS/JS que requieran build: compilar local y subir con `rsync -av --delete ~/medforge/laravel-app/public/build/ ~/medforge/public/build/`.

---

## Mapa de archivos

| Archivo | Acción | Responsabilidad |
|---|---|---|
| `laravel-app/app/Modules/Whatsapp/Services/KpiDashboardService.php` | Modificar | Bug fixes + 2 métodos nuevos |
| `laravel-app/resources/views/whatsapp/v2-dashboard.blade.php` | Modificar | Restructura en 3 secciones |

---

## Task 1: Fix BUG-1 — Carga por agente sin filtro de fecha

**Problema:** `handoffsByAgent` filtra por `queued_at >= $from` → hoy muestra 1 handoff cuando hay 38 activos.
**Fix:** Consultar estado actual siempre. Mantener firma del método pero ignorar `$fromSql`/`$toSql` internamente.

**Files:**
- Modify: `laravel-app/app/Modules/Whatsapp/Services/KpiDashboardService.php:1637-1665`

- [ ] **Step 1: Localizar el método**

```bash
grep -n "private function handoffsByAgent" laravel-app/app/Modules/Whatsapp/Services/KpiDashboardService.php
```
Resultado esperado: línea ~1637.

- [ ] **Step 2: Reemplazar la consulta**

Cambiar el body del método `handoffsByAgent` (mantener firma igual para no romper `exportDashboardCsvRows`):

```php
private function handoffsByAgent(string $fromSql, string $toSql, ?int $roleId, ?int $agentId): array
{
    $filter = $this->handoffFilterSql('h', $roleId, $agentId, 'agent');
    $sql = 'SELECT
                h.assigned_agent_id AS user_id,
                ' . $this->agentNameSql('u', 'h.assigned_agent_id', 'Usuario #') . ' AS agent_name,
                COUNT(*) AS assigned_count,
                SUM(CASE WHEN h.status = "assigned" THEN 1 ELSE 0 END) AS active_count,
                SUM(CASE WHEN h.status = "resolved" THEN 1 ELSE 0 END) AS resolved_count
            FROM whatsapp_handoffs h
            LEFT JOIN users u ON u.id = h.assigned_agent_id
            WHERE h.assigned_agent_id IS NOT NULL
              AND h.status IN ("assigned", "queued")';
    $params = [];
    if ($filter['where'] !== '') {
        $sql .= ' AND ' . $filter['where'];
        $params = array_values($filter['params']);
    }
    $sql .= ' GROUP BY h.assigned_agent_id, agent_name ORDER BY assigned_count DESC, resolved_count DESC, agent_name ASC';

    return array_map(fn ($row) => [
        'user_id' => (int) ($row->user_id ?? 0),
        'agent_name' => (string) ($row->agent_name ?? ''),
        'assigned_count' => (int) ($row->assigned_count ?? 0),
        'active_count' => (int) ($row->active_count ?? 0),
        'resolved_count' => (int) ($row->resolved_count ?? 0),
    ], DB::select($sql, $params));
}
```

- [ ] **Step 3: Verificar en producción**

```bash
# En servidor IONOS via SSH
sshpass -p 'JorgeAMI2018' ssh u98115706@access793096920.webspace-data.io \
"mysql -h 74.208.195.146 -u jl1dvg -pJorgeAMI2018 medforge -e \"
SELECT assigned_agent_id, COUNT(*) as total
FROM whatsapp_handoffs
WHERE assigned_agent_id IS NOT NULL AND status IN ('assigned','queued')
GROUP BY assigned_agent_id ORDER BY total DESC LIMIT 5;\""
```
Resultado esperado: filas con totales reales (~38 handoffs distribuidos entre agentes).

- [ ] **Step 4: Commit**

```bash
git add laravel-app/app/Modules/Whatsapp/Services/KpiDashboardService.php
git commit -m "fix(whatsapp): carga por agente muestra estado actual, no filtro de fecha"
```

---

## Task 2: Fix BUG-2 — Barra de carga absoluta con cap configurable

**Problema:** La barra en el blade usa escala relativa al agente más cargado. Fix en el blade.

**Files:**
- Modify: `laravel-app/resources/views/whatsapp/v2-dashboard.blade.php:1882-1906`

- [ ] **Step 1: Localizar el bloque PHP en blade**

```bash
grep -n "loadPct\|maxAssigned\|Carga por agente" laravel-app/resources/views/whatsapp/v2-dashboard.blade.php
```

- [ ] **Step 2: Reemplazar lógica de carga**

Buscar el bloque `@php $agentRows = ... @endphp` y el `@forelse` de `handoffs_by_agent` y reemplazar:

```blade
@php
    $agentRows   = $breakdowns['handoffs_by_agent'] ?? [];
    $loadCap     = 10; // conversaciones = 100% de carga
@endphp
@forelse($agentRows as $row)
    @php
        $loadPct = (int) min(100, round(($row['assigned_count'] / $loadCap) * 100));
        $colorL  = $loadPct >= 85 ? 'red' : ($loadPct >= 60 ? 'yellow' : 'green');
    @endphp
    <tr>
        <td>{{ $row['agent_name'] }}</td>
        <td>{{ $row['assigned_count'] }}</td>
        <td>{{ $row['active_count'] }}</td>
        <td>{{ $row['resolved_count'] }}</td>
        <td>
            <div class="wa-prog-wrap">
                <div class="wa-prog-bg">
                    <div class="wa-prog-fill wa-prog-fill--{{ $colorL }}"
                         style="width:{{ $loadPct }}%"></div>
                </div>
                <span class="wa-prog-val wa-prog-val--{{ $colorL }}">{{ $row['assigned_count'] }}/{{ $loadCap }}</span>
            </div>
        </td>
    </tr>
@empty
    <tr><td colspan="5" class="text-center text-muted py-20">Sin agentes con carga activa.</td></tr>
@endforelse
```

- [ ] **Step 3: Commit**

```bash
git add laravel-app/resources/views/whatsapp/v2-dashboard.blade.php
git commit -m "fix(whatsapp): barra carga agente usa escala absoluta cap=10"
```

---

## Task 3: Fix BUG-3 — Reemplazar AVG con P75 en tiempos de respuesta

**Fix en `KpiDashboardService`:** agregar método `percentile75`, actualizar `humanAttentionByAgent` y `humanResponseByQueue`.

**Files:**
- Modify: `laravel-app/app/Modules/Whatsapp/Services/KpiDashboardService.php`

- [ ] **Step 1: Agregar método `percentile75` junto a `median`**

Ubicar `private function median(array $values)` (~línea 2891) y agregar debajo:

```php
/**
 * @param array<int, int|float> $values
 */
private function percentile75(array $values): ?float
{
    if ($values === []) {
        return null;
    }
    sort($values, SORT_NUMERIC);
    $index = (int) ceil(0.75 * count($values)) - 1;
    return (float) $values[max(0, $index)];
}
```

- [ ] **Step 2: Actualizar `humanAttentionByAgent`**

En el método `humanAttentionByAgent` (~línea 1670), el query usa `AVG(TIMESTAMPDIFF(...))`. Cambiar el `array_map` para calcular P75 en PHP sobre los segundos individuales:

```php
private function humanAttentionByAgent(string $fromSql, string $toSql, ?int $roleId, ?int $agentId): array
{
    $scope = $this->inboundScopeSubquery($fromSql, $toSql, $roleId, $agentId, 'human_agent');
    $reply = $this->firstHumanReplyByAgentSubquery($scope, $roleId, $agentId, 'human_agent');

    // Traer filas individuales (no AVG) para calcular P75 en PHP
    $sql = 'SELECT
                first_reply.assigned_agent_id AS user_id,
                ' . $this->agentNameSql('u', 'first_reply.assigned_agent_id', 'Usuario #') . ' AS agent_name,
                ' . ($this->isSqlite()
                    ? '(julianday(first_reply.first_human_reply_at) - julianday(first_reply.assigned_at)) * 86400'
                    : 'TIMESTAMPDIFF(SECOND, first_reply.assigned_at, first_reply.first_human_reply_at)') . ' AS response_seconds
            FROM (' . $reply['sql'] . ') first_reply
            LEFT JOIN users u ON u.id = first_reply.assigned_agent_id
            ORDER BY first_reply.assigned_agent_id';

    $rows = DB::select($sql, array_values($reply['params']));

    // Agrupar por agente y calcular P75
    $agents = [];
    foreach ($rows as $row) {
        $uid = (int) ($row->user_id ?? 0);
        if (!isset($agents[$uid])) {
            $agents[$uid] = [
                'user_id' => $uid,
                'agent_name' => (string) ($row->agent_name ?? ''),
                'seconds' => [],
            ];
        }
        $secs = (float) ($row->response_seconds ?? 0);
        if ($secs >= 0) {
            $agents[$uid]['seconds'][] = $secs;
        }
    }

    return array_values(array_map(function (array $agent): array {
        $p75 = $this->percentile75($agent['seconds']);
        return [
            'user_id' => $agent['user_id'],
            'agent_name' => $agent['agent_name'],
            'attended_conversations' => count($agent['seconds']),
            'p75_first_response_minutes' => $p75 !== null ? round($p75 / 60, 1) : null,
        ];
    }, $agents));
}
```

- [ ] **Step 3: Actualizar `humanResponseByQueue`**

En `humanResponseByQueue` (~línea 1773), reemplazar el `array_map` que calcula avg:

```php
$rows = array_map(function (array $bucket): array {
    $seconds = $bucket['response_seconds'];
    $p75     = $this->percentile75($seconds);
    $median  = $this->median($seconds);

    unset($bucket['response_seconds']);
    $bucket['p75_first_response_minutes']    = $p75 !== null ? round($p75 / 60, 1) : null;
    $bucket['median_first_response_minutes'] = $median !== null ? round($median / 60, 1) : null;
    // Eliminar avg — ya no se usa
    $bucket['response_rate'] = $bucket['total_handoffs'] > 0
        ? round(($bucket['attended_handoffs'] / $bucket['total_handoffs']) * 100, 1)
        : 0.0;

    return $bucket;
}, array_values($buckets));
```

- [ ] **Step 4: Commit**

```bash
git add laravel-app/app/Modules/Whatsapp/Services/KpiDashboardService.php
git commit -m "fix(whatsapp): reemplazar AVG con P75 en tiempos de respuesta (agente y cola)"
```

---

## Task 4: Nuevo método — `conversationFunnelBySource`

Embudo por origen para la sección de marketing. Reutiliza la base analítica existente pero la agrupa por `source_category`.

**Files:**
- Modify: `laravel-app/app/Modules/Whatsapp/Services/KpiDashboardService.php`

- [ ] **Step 1: Agregar método después de `conversationSourcesBreakdown`**

```php
/**
 * Embudo de conversión agrupado por origen (ads, organic_direct, campaign_outbound).
 * Devuelve filas ordenadas: ads primero, luego orgánico, luego outbound, luego el resto.
 *
 * @param array{sql:string,params:array<int|string,mixed>} $base
 * @return array<int, array<string, mixed>>
 */
private function conversationFunnelBySource(array $base): array
{
    $rows = DB::select(
        'SELECT
            source_category,
            COUNT(*) AS total,
            SUM(is_identified) AS identified,
            SUM(has_handoff) AS handoffs,
            SUM(has_booking) AS booked
         FROM (' . $base['sql'] . ') analytics_base
         GROUP BY source_category
         ORDER BY total DESC',
        $base['params']
    );

    $order = ['ad' => 0, 'organic_direct' => 1, 'campaign_outbound' => 2];

    $result = array_map(function ($row): array {
        $total      = (int) ($row->total ?? 0);
        $identified = (int) ($row->identified ?? 0);
        $handoffs   = (int) ($row->handoffs ?? 0);
        $booked     = (int) ($row->booked ?? 0);
        $source     = (string) ($row->source_category ?? 'unknown');

        return [
            'source_category'      => $source,
            'source_label'         => $this->sourceCategoryLabel($source),
            'total'                => $total,
            'identified'           => $identified,
            'identification_rate'  => $total > 0 ? round(($identified / $total) * 100, 1) : 0.0,
            'handoffs'             => $handoffs,
            'handoff_rate'         => $total > 0 ? round(($handoffs / $total) * 100, 1) : 0.0,
            'booked'               => $booked,
            'booking_rate'         => $total > 0 ? round(($booked / $total) * 100, 1) : 0.0,
        ];
    }, $rows);

    usort($result, fn ($a, $b) => ($order[$a['source_category']] ?? 99) <=> ($order[$b['source_category']] ?? 99));

    return $result;
}
```

- [ ] **Step 2: Agregar al array `breakdowns` en `buildDashboard`**

En `buildDashboard` (~línea 130), agregar dentro de `'breakdowns' => [...]`:

```php
'funnel_by_source' => $this->conversationFunnelBySource($analytics['base']),
```

Verificar que `$analytics['base']` esté disponible — el método `conversationAnalytics` retorna `['base' => ..., 'summary' => ..., ...]`. Buscar cómo se usa `$analytics` en el blade:

```bash
grep -n "analytics\[.base.\]\|analyticsBase\|conversationAnalytics" laravel-app/app/Modules/Whatsapp/Services/KpiDashboardService.php | head -10
```

Si `$analytics` no expone `base`, usar directamente:

```php
'funnel_by_source' => $this->conversationFunnelBySource(
    $this->conversationAnalyticsBaseSubquery($fromSql, $toSql, $roleId, $agentId, 'funnel_source')
),
```

- [ ] **Step 3: Commit**

```bash
git add laravel-app/app/Modules/Whatsapp/Services/KpiDashboardService.php
git commit -m "feat(whatsapp): agregar embudo de conversión por origen de lead"
```

---

## Task 5: Nuevo método — `lostLeadsBySource`

Métricas de "leads perdidos por operación" para el bloque C de marketing.

**Files:**
- Modify: `laravel-app/app/Modules/Whatsapp/Services/KpiDashboardService.php`

- [ ] **Step 1: Agregar método**

```php
/**
 * Leads de ads que llegaron pero no recibieron atención humana.
 * Separa responsabilidad de marketing (trajeron el lead) de operaciones (no lo atendieron).
 *
 * @return array{
 *   ads_total: int,
 *   ads_lost_no_human: int,
 *   ads_lost_no_assignment: int,
 *   ads_abandoned_with_handoff: int
 * }
 */
private function lostLeadsBySource(string $fromSql, string $toSql, ?int $roleId, ?int $agentId): array
{
    $scope       = $this->inboundScopeSubquery($fromSql, $toSql, $roleId, $agentId, 'lost_leads');
    $reply       = $this->humanReplySubquery($roleId, $agentId, 'lost_leads');
    $handoffStart = $this->handoffStartSubquery($roleId, $agentId, 'lost_leads');

    $attributionJoin = Schema::hasTable('whatsapp_conversation_attributions')
        ? 'LEFT JOIN whatsapp_conversation_attributions attr ON attr.conversation_id = inbound.conversation_id'
        : 'LEFT JOIN (SELECT NULL AS conversation_id, NULL AS source_category) attr ON 1 = 0';

    $sql = 'SELECT
                inbound.conversation_id,
                inbound.last_inbound_at,
                COALESCE(attr.source_category, "unknown") AS source_category,
                human.first_human_reply_at,
                handoff.first_handoff_at,
                inbound.handoff_requested_at,
                (SELECT MIN(h2.assigned_at) FROM whatsapp_handoffs h2
                 WHERE h2.conversation_id = inbound.conversation_id
                   AND h2.assigned_agent_id IS NOT NULL) AS first_assignment_at
            FROM (' . $scope['sql'] . ') inbound
            ' . $attributionJoin . '
            LEFT JOIN (' . $reply['sql'] . ') human
                ON human.conversation_id = inbound.conversation_id
            LEFT JOIN (' . $handoffStart['sql'] . ') handoff
                ON handoff.conversation_id = inbound.conversation_id';

    $params = array_merge(
        array_values($scope['params']),
        array_values($reply['params']),
        array_values($handoffStart['params'])
    );

    $rows = DB::select($sql, $params);
    $threshold24h = Carbon::now()->subHours(24);

    $adsTotal              = 0;
    $adsLostNoHuman        = 0;
    $adsLostNoAssignment   = 0;
    $adsAbandonedHandoff   = 0;

    foreach ($rows as $row) {
        $isAd = in_array((string) ($row->source_category ?? ''), ['ad', 'ads'], true);
        if (!$isAd) {
            continue;
        }

        $adsTotal++;
        $hasHuman      = isset($row->first_human_reply_at);
        $hasHandoff    = isset($row->first_handoff_at) || isset($row->handoff_requested_at);
        $hasAssignment = isset($row->first_assignment_at);
        $lastInbound   = isset($row->last_inbound_at) ? Carbon::parse((string) $row->last_inbound_at) : null;
        $isOld         = $lastInbound !== null && $lastInbound->lessThanOrEqualTo($threshold24h);

        if (!$hasHuman) {
            $adsLostNoHuman++;
        }
        if ($hasHandoff && !$hasAssignment) {
            $adsLostNoAssignment++;
        }
        if ($hasHandoff && !$hasHuman && $isOld) {
            $adsAbandonedHandoff++;
        }
    }

    return [
        'ads_total'                => $adsTotal,
        'ads_lost_no_human'        => $adsLostNoHuman,
        'ads_lost_no_assignment'   => $adsLostNoAssignment,
        'ads_abandoned_with_handoff' => $adsAbandonedHandoff,
    ];
}
```

- [ ] **Step 2: Agregar a `buildDashboard`**

En `buildDashboard`, agregar una clave nueva en el array de retorno (al mismo nivel que `breakdowns`):

```php
'marketing' => [
    'funnel_by_source' => $this->conversationFunnelBySource(
        $this->conversationAnalyticsBaseSubquery($fromSql, $toSql, $roleId, $agentId, 'mktg_funnel')
    ),
    'lost_by_source' => $this->lostLeadsBySource($fromSql, $toSql, $roleId, $agentId),
],
```

Y remover `'funnel_by_source'` de `breakdowns` si se agregó allí en Task 4 — consolidar todo en `'marketing'`.

- [ ] **Step 3: Commit**

```bash
git add laravel-app/app/Modules/Whatsapp/Services/KpiDashboardService.php
git commit -m "feat(whatsapp): leads perdidos por operación para sección marketing"
```

---

## Task 6: Actualizar blade — pasar nuevas variables al scope

El blade recibe el array de `buildDashboard`. Verificar que el controlador pasa `marketing` al blade y asignar variables locales.

**Files:**
- Modify: `laravel-app/resources/views/whatsapp/v2-dashboard.blade.php` (sección `@php` inicial)

- [ ] **Step 1: Localizar cómo se pasan datos al blade**

```bash
grep -n "buildDashboard\|KpiDashboardService\|v2-dashboard" laravel-app/app/Modules/Whatsapp/Controllers/WhatsappController.php 2>/dev/null || grep -rn "v2-dashboard" laravel-app/app/ | grep -v ".blade." | head -10
```

- [ ] **Step 2: Agregar extracción de variables en el blade**

Al inicio del blade (después de `@extends`), buscar el bloque `@php` que extrae `$summary`, `$breakdowns`, etc. Agregar:

```php
$marketingData  = $dashboard['marketing'] ?? [];
$funnelBySource = $marketingData['funnel_by_source'] ?? [];
$lostBySource   = $marketingData['lost_by_source'] ?? [];
```

- [ ] **Step 3: Commit**

```bash
git add laravel-app/resources/views/whatsapp/v2-dashboard.blade.php
git commit -m "feat(whatsapp): exponer datos de marketing al blade v2"
```

---

## Task 7: Restructurar blade — Sección 1 "Operación de hoy"

Envolver el bloque de operación en un acordeón colapsable con encabezado de audiencia.

**Files:**
- Modify: `laravel-app/resources/views/whatsapp/v2-dashboard.blade.php`

- [ ] **Step 1: Identificar los bloques a mover**

```bash
grep -n "Carga por agente\|Atención humana por agente\|Primera respuesta por cola\|Salud operativa\|Conversaciones fuera de 24h\|supervisor_band\|window_band" laravel-app/resources/views/whatsapp/v2-dashboard.blade.php
```

- [ ] **Step 2: Agregar wrapper de sección antes del bloque operativo**

Envolver los paneles "Salud operativa", "Conversaciones fuera de 24h", "Primera respuesta por cola", "Atención humana por agente" y "Carga por agente" con:

```blade
{{-- ╔══════════════════════════════════════════════════════╗ --}}
{{-- ║  SECCIÓN 1 — OPERACIÓN DE HOY  (para el supervisor) ║ --}}
{{-- ╚══════════════════════════════════════════════════════╝ --}}
<div class="card mb-3">
    <div class="card-header d-flex align-items-center" style="cursor:pointer;background:#f0fdf4;"
         onclick="this.nextElementSibling.classList.toggle('d-none')">
        <span style="font-size:18px;margin-right:8px">🟢</span>
        <strong>Operación de hoy</strong>
        <span class="text-muted ms-2" style="font-size:13px">Para el supervisor · Estado actual</span>
        <span class="ms-auto">▼</span>
    </div>
    <div class="card-body p-0">
        <div class="row g-3 p-3">
            {{-- paneles existentes aquí --}}
        </div>
    </div>
</div>
```

- [ ] **Step 3: Actualizar tabla "Atención humana por agente" para usar P75**

Buscar la columna `1ra respuesta` en la tabla y cambiar:

```blade
{{-- Antes --}}
<td class="wa-prog-val--{{ $color }}">{{ $mins !== null ? $mins . ' min' : '—' }}</td>

{{-- Después — usar p75_first_response_minutes --}}
@php
    $mins  = $row['p75_first_response_minutes'];
    $slaM  = (int)($filters['sla_target_minutes'] ?? 15);
    $color = $mins === null ? 'green' : ($mins > $slaM * 2 ? 'red' : ($mins > $slaM ? 'yellow' : 'green'));
@endphp
<td class="wa-prog-val--{{ $color }}">{{ $mins !== null ? $mins . ' min (P75)' : '—' }}</td>
```

Eliminar badge `✗ Alto` y `~ OK` — reemplazar con semáforo de color solamente.

- [ ] **Step 4: Eliminar columna "Promedio" de tabla "Primera respuesta por cola"**

Buscar el `<th>Promedio</th>` y su `<td>` correspondiente en el `@forelse` y eliminarlos. Cambiar `colspan="7"` → `colspan="6"` en el `@empty`.

Actualizar la celda de Mediana para usar P75:
```blade
{{-- Antes --}}
<td>{{ $row['median_first_response_minutes'] !== null ? $row['median_first_response_minutes'] . ' min' : '—' }}</td>

{{-- Después --}}
<td>{{ $row['p75_first_response_minutes'] !== null ? $row['p75_first_response_minutes'] . ' min' : '—' }}</td>
```

Y actualizar el `<th>Mediana</th>` → `<th>P75</th>`.

- [ ] **Step 5: Commit**

```bash
git add laravel-app/resources/views/whatsapp/v2-dashboard.blade.php
git commit -m "feat(whatsapp): sección 1 operación de hoy con P75 y datos actuales"
```

---

## Task 8: Restructurar blade — Sección 2 "Rendimiento del canal"

**Files:**
- Modify: `laravel-app/resources/views/whatsapp/v2-dashboard.blade.php`

- [ ] **Step 1: Agregar 4 tarjetas de números grandes**

Antes del grid de KPIs existente, insertar:

```blade
{{-- ╔═════════════════════════════════════════════════════╗ --}}
{{-- ║  SECCIÓN 2 — RENDIMIENTO DEL CANAL  (para gerencia) ║ --}}
{{-- ╚═════════════════════════════════════════════════════╝ --}}
<div class="card mb-3">
    <div class="card-header d-flex align-items-center" style="cursor:pointer;background:#eff6ff;"
         onclick="this.nextElementSibling.classList.toggle('d-none')">
        <span style="font-size:18px;margin-right:8px">📊</span>
        <strong>Rendimiento del canal</strong>
        <span class="text-muted ms-2" style="font-size:13px">Para gerencia · {{ $filters['date_from'] ?? '' }} – {{ $filters['date_to'] ?? '' }}</span>
        <span class="ms-auto">▼</span>
    </div>
    <div class="card-body">
        {{-- 4 números grandes --}}
        @php
            $coverageRate = $summary['attention_rate'] ?? 0;
            $coverageColor = $coverageRate >= 80 ? 'success' : ($coverageRate >= 60 ? 'warning' : 'danger');
            $slaRate = $summary['sla_response_rate'] ?? 0;
            $slaColor = $slaRate >= 80 ? 'success' : ($slaRate >= 60 ? 'warning' : 'danger');
        @endphp
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="text-center p-3 rounded border border-{{ $coverageColor }}">
                    <div style="font-size:2rem;font-weight:700;color:var(--bs-{{ $coverageColor }})">{{ $coverageRate }}%</div>
                    <div class="fw-semibold">Cobertura</div>
                    <div class="text-muted small">Personas atendidas</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-center p-3 rounded border border-{{ $slaColor }}">
                    <div style="font-size:2rem;font-weight:700;color:var(--bs-{{ $slaColor }})">{{ $slaRate }}%</div>
                    <div class="fw-semibold">SLA</div>
                    <div class="text-muted small">Respondidos a tiempo</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-center p-3 rounded border">
                    <div style="font-size:2rem;font-weight:700">{{ $summary['conversations_attended_human'] ?? 0 }}</div>
                    <div class="fw-semibold">Atendidas</div>
                    <div class="text-muted small">Con respuesta humana</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                @php $lost = $summary['conversations_lost'] ?? 0; @endphp
                <div class="text-center p-3 rounded border {{ $lost > 0 ? 'border-danger' : '' }}">
                    <div style="font-size:2rem;font-weight:700;{{ $lost > 0 ? 'color:var(--bs-danger)' : '' }}">{{ $lost }}</div>
                    <div class="fw-semibold">Sin atender</div>
                    @if(($summary['conversations_lost_with_handoff'] ?? 0) > 0)
                        <div class="text-muted small">{{ $summary['conversations_lost_with_handoff'] }} solicitaron ayuda</div>
                    @endif
                </div>
            </div>
        </div>
        {{-- detalle colapsable existente: grids de KPI, recordatorios, cierres --}}
    </div>
</div>
```

- [ ] **Step 2: Mover paneles existentes dentro del card**

Mover los paneles de KPIs (grid de tarjetas), Recordatorios y Primera respuesta por cola dentro del `card-body` de la Sección 2.

- [ ] **Step 3: Simplificar Recordatorios a 3 métricas**

En el panel de recordatorios, conservar solo:

```blade
@php
    $reminderCards = [
        ['label' => 'Enviados', 'value' => number_format((int)($reminderSummary['sent'] ?? 0))],
        ['label' => 'Entregados', 'value' => number_format((int)($reminderSummary['delivered'] ?? 0)), 'sub' => ($reminderSummary['delivery_rate'] ?? 0) . '% de entrega'],
        ['label' => 'Confirmaron', 'value' => number_format((int)($reminderSummary['confirmed'] ?? 0)), 'sub' => ($reminderSummary['confirmation_rate'] ?? 0) . '% de respuestas'],
    ];
@endphp
```

Eliminar las otras 5 tarjetas de recordatorios (generados, pendientes, fallidos, respondidos, pidieron agente) — pueden ir en un detalle colapsable si se necesitan.

- [ ] **Step 4: Commit**

```bash
git add laravel-app/resources/views/whatsapp/v2-dashboard.blade.php
git commit -m "feat(whatsapp): sección 2 rendimiento del canal con 4 KPIs grandes"
```

---

## Task 9: Restructurar blade — Sección 3 "Captación y Marketing"

**Files:**
- Modify: `laravel-app/resources/views/whatsapp/v2-dashboard.blade.php`

- [ ] **Step 1: Crear wrapper de sección**

```blade
{{-- ╔══════════════════════════════════════════════════════╗ --}}
{{-- ║  SECCIÓN 3 — CAPTACIÓN Y MARKETING  (para marketing) ║ --}}
{{-- ╚══════════════════════════════════════════════════════╝ --}}
<div class="card mb-3">
    <div class="card-header d-flex align-items-center" style="cursor:pointer;background:#fdf4ff;"
         onclick="this.nextElementSibling.classList.toggle('d-none')">
        <span style="font-size:18px;margin-right:8px">📣</span>
        <strong>Captación y Marketing</strong>
        <span class="text-muted ms-2" style="font-size:13px">Para marketing · Ads, conversiones y leads</span>
        <span class="ms-auto">▼</span>
    </div>
    <div class="card-body">
        {{-- Bloque A, B, C aquí --}}
    </div>
</div>
```

- [ ] **Step 2: Bloque A — Lo que trajo marketing**

Mover las tarjetas existentes de `$commercialCards` dentro de esta sección. Simplificar a:

```blade
@php
    $mktgCards = [
        ['label' => 'Conversaciones nuevas', 'value' => $analyticsSummary['total_conversations'] ?? 0, 'sub' => 'Base del periodo'],
        ['label' => 'Desde Ads', 'value' => $analyticsSummary['conversations_from_ads'] ?? 0, 'sub' => 'Orgánico: ' . ($analyticsSummary['conversations_organic'] ?? 0)],
        ['label' => 'Iniciadas por equipo', 'value' => $analyticsSummary['conversations_outbound_started'] ?? 0, 'sub' => 'Seguimientos manuales'],
        ['label' => 'Pacientes nuevos', 'value' => $analyticsSummary['new_patients'] ?? 0, 'sub' => 'Recurrentes: ' . ($analyticsSummary['returning_patients'] ?? 0)],
        ['label' => 'Lead score promedio', 'value' => $analyticsSummary['avg_lead_score'] ?? 0, 'sub' => 'Alto valor: ' . ($analyticsSummary['high_value_leads'] ?? 0)],
    ];
@endphp
<h6 class="text-muted mb-2">Lo que trajo marketing</h6>
<div class="wa-kpi-grid mb-4">
    @foreach($mktgCards as $card)
        <div class="wa-kpi-card">
            <div class="wa-kpi-label">{{ $card['label'] }}</div>
            <div class="wa-kpi-value">{{ $card['value'] }}</div>
            <div class="wa-kpi-sub">{{ $card['sub'] ?? '' }}</div>
        </div>
    @endforeach
</div>
```

- [ ] **Step 3: Bloque B — Embudo por origen**

```blade
<h6 class="text-muted mb-2">¿Qué pasó con cada lead según su origen?</h6>
<div class="table-responsive mb-4">
    <table class="table table-striped wa-kpi-table mb-0">
        <thead>
        <tr>
            <th>Origen</th>
            <th>Llegaron</th>
            <th>Identificados</th>
            <th>Solicitaron atención</th>
            <th>Cita agendada</th>
            <th>Conversión final</th>
        </tr>
        </thead>
        <tbody>
        @forelse($funnelBySource as $row)
            <tr>
                <td><strong>{{ $row['source_label'] }}</strong></td>
                <td>{{ $row['total'] }}</td>
                <td>{{ $row['identified'] }} <span class="text-muted">({{ $row['identification_rate'] }}%)</span></td>
                <td>{{ $row['handoffs'] }} <span class="text-muted">({{ $row['handoff_rate'] }}%)</span></td>
                <td>{{ $row['booked'] }} <span class="text-muted">({{ $row['booking_rate'] }}%)</span></td>
                <td>
                    @php
                        $rate = $row['booking_rate'];
                        $rateColor = $rate >= 20 ? 'success' : ($rate >= 10 ? 'warning' : 'danger');
                    @endphp
                    <span class="badge bg-{{ $rateColor }}">{{ $rate }}%</span>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted py-3">Sin datos analíticos para el periodo.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
```

- [ ] **Step 4: Bloque C — Leads perdidos por operación**

```blade
@if(($lostBySource['ads_total'] ?? 0) > 0)
<div class="alert alert-light border mb-4">
    <h6 class="mb-3">🛡️ Leads de ads — separando marketing de operaciones</h6>
    <ul class="mb-0" style="line-height:2">
        <li>
            <strong>{{ $lostBySource['ads_total'] }}</strong> personas llegaron desde ads en el periodo
        </li>
        @if(($lostBySource['ads_lost_no_human'] ?? 0) > 0)
        <li class="text-danger">
            <strong>{{ $lostBySource['ads_lost_no_human'] }}</strong> no recibieron respuesta humana
            <span class="text-muted">(responsabilidad operativa, no de marketing)</span>
        </li>
        @endif
        @if(($lostBySource['ads_lost_no_assignment'] ?? 0) > 0)
        <li class="text-warning">
            <strong>{{ $lostBySource['ads_lost_no_assignment'] }}</strong> solicitaron ayuda pero no fueron asignados a ningún agente
        </li>
        @endif
        @if(($lostBySource['ads_abandoned_with_handoff'] ?? 0) > 0)
        <li class="text-danger">
            <strong>{{ $lostBySource['ads_abandoned_with_handoff'] }}</strong> tuvieron handoff pero llevan más de 24h sin respuesta
        </li>
        @endif
    </ul>
</div>
@endif
```

- [ ] **Step 5: Mover secciones analíticas existentes dentro de esta sección**

Mover dentro del card de marketing: "Vista ejecutiva del canal", "Mix ejecutivo", "Captación y conversión", "Outcome de conversaciones", tabla de Ads.

Eliminar duplicados — si `$commercialCards` ya está en Bloque A, no repetir en "Captación y conversión".

- [ ] **Step 6: Commit**

```bash
git add laravel-app/resources/views/whatsapp/v2-dashboard.blade.php
git commit -m "feat(whatsapp): sección 3 marketing con embudo por origen y leads perdidos"
```

---

## Task 10: Deploy y verificación visual en IONOS

- [ ] **Step 1: Push a main**

```bash
git push origin main
```

- [ ] **Step 2: Pull en IONOS**

```bash
sshpass -p 'JorgeAMI2018' ssh u98115706@access793096920.webspace-data.io \
"cd medforge && git pull origin main && echo 'OK'"
```

- [ ] **Step 3: Verificar en browser**

Abrir `/v2/whatsapp/dashboard` con filtro "Hoy" y verificar:
1. Sección 1 muestra los ~38 handoffs activos reales distribuidos entre agentes
2. Sección 1 tabla P75 muestra minutos (no "✗ Alto")
3. Sección 2 muestra 4 números grandes (Cobertura, SLA, Atendidas, Sin atender)
4. Sección 3 Bloque B muestra tabla embudo con filas por origen
5. Sección 3 Bloque C muestra alerta de leads de ads perdidos si hay datos

- [ ] **Step 4: Si hay cambios CSS/JS que requieran npm**

```bash
# En local
cd ~/PhpstormProjects/MedForge/laravel-app && npm run build
rsync -av --delete ~/PhpstormProjects/MedForge/laravel-app/public/build/ \
  u98115706@access793096920.webspace-data.io:/homepages/26/d793096920/htdocs/medforge/public/build/
```

---

## Self-Review

**Spec coverage:**
- ✅ BUG-1 carga agente → Task 1
- ✅ BUG-2 barra relativa → Task 2
- ✅ BUG-3 P75 vs avg → Task 3
- ✅ Embudo por origen → Task 4
- ✅ Leads perdidos marketing → Task 5
- ✅ 3 secciones blade → Tasks 7, 8, 9
- ✅ Deploy IONOS → Task 10
- ✅ Recordatorios simplificados a 3 → Task 8 Step 3
- ⚠️ Cap configurable en settings (Fase 2) → hardcodeado en `$loadCap = 10` por ahora, suficiente para el objetivo

**Consistencia de tipos:**
- `p75_first_response_minutes` → float|null en service, usado igual en blade
- `funnelBySource` / `lostBySource` → extraídos de `$dashboard['marketing']` en blade
- `handoffsByAgent` → sigue retornando mismas claves, compatible con `exportDashboardCsvRows`
