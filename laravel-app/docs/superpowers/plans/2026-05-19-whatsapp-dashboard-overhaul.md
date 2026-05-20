# WhatsApp Dashboard Overhaul — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Corregir los datos del dashboard de WhatsApp, mejorar la UX para personal clínico no técnico, y agregar una guía de uso interactiva embebida en la vista.

**Architecture:** 4 fases independientes. Fase 1-2 son backend (datos correctos). Fase 3 es frontend (UI legible). Fase 4 es la guía embebida. Cada fase produce software funcionando y testeable por separado. Se puede hacer una fase a la vez o en paralelo.

**Tech Stack:** PHP 8.x · Laravel 10+ · Blade · MySQL · Vanilla JS · CSS inline (convención existente del proyecto)

---

## Mapa de archivos

| Archivo | Acción | Responsabilidad |
|--------|--------|-----------------|
| `database/migrations/2026_05_19_100000_add_platform_to_whatsapp_conversation_attributions.php` | Crear | Agregar columna `platform` a attribution table |
| `app/Modules/Whatsapp/Services/ConversationAttributionService.php` | Modificar | Derivar y guardar `platform` al ingestar referral |
| `app/Modules/Whatsapp/Services/KpiDashboardService.php` | Modificar | Fix filtro por agente + agregar platform a ads query |
| `resources/views/whatsapp/v2-dashboard.blade.php` | Modificar | Reestructurar UI en 3 zonas + renombrar labels |
| `resources/views/whatsapp/partials/dashboard-guide.blade.php` | Crear | Guía interactiva embebida (modal slide-through) |
| `tests/Feature/WhatsappKpiDashboardTest.php` | Modificar | Tests para fixes de datos y plataforma de ads |

---

## FASE 1 — Fix de datos: Platform en Ads

**Objetivo:** Que la tabla de ads muestre de qué red social llegó cada anuncio (Facebook, Instagram, etc.).

**Contexto:** La tabla `whatsapp_conversation_attributions` tiene `source_url` (text) pero no una columna explícita `platform`. El `source_url` que envía Meta contiene el dominio y permite distinguir: `facebook.com` → Facebook, `instagram.com` → Instagram, `wa.me` o vacío → WhatsApp directo.

---

### Tarea 1: Migración — columna `platform`

**Archivos:**
- Crear: `database/migrations/2026_05_19_100000_add_platform_to_whatsapp_conversation_attributions.php`

- [ ] **Paso 1: Crear la migración**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('whatsapp_conversation_attributions', function (Blueprint $table): void {
            if (!Schema::hasColumn('whatsapp_conversation_attributions', 'platform')) {
                $table->string('platform', 32)->nullable()->after('source_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_conversation_attributions', function (Blueprint $table): void {
            if (Schema::hasColumn('whatsapp_conversation_attributions', 'platform')) {
                $table->dropColumn('platform');
            }
        });
    }
};
```

- [ ] **Paso 2: Correr la migración**

```bash
cd /Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app
php artisan migrate
```

Resultado esperado: `Migrating: 2026_05_19_100000... Done.`

- [ ] **Paso 3: Commit**

```bash
git add database/migrations/2026_05_19_100000_add_platform_to_whatsapp_conversation_attributions.php
git commit -m "feat(whatsapp): add platform column to conversation attributions"
```

---

### Tarea 2: Derivar platform en ConversationAttributionService

**Archivos:**
- Modificar: `app/Modules/Whatsapp/Services/ConversationAttributionService.php`

**Contexto:** El servicio ya tiene un método `referralPayload()` que extrae los datos del mensaje inbound. El `source_url` llega como `https://www.facebook.com/ads/...` o `https://www.instagram.com/...`. Necesitamos derivar el platform de esa URL y guardarlo en el `upsert`.

- [ ] **Paso 1: Escribir el test primero**

Abrir `tests/Feature/WhatsappKpiDashboardTest.php` y agregar al final de la clase (antes del `}`):

```php
/** @test */
public function it_derives_platform_from_source_url_when_saving_attribution(): void
{
    $cases = [
        ['https://www.facebook.com/ads/123', 'facebook'],
        ['https://l.facebook.com/l.php?u=...', 'facebook'],
        ['https://www.instagram.com/p/abc/', 'instagram'],
        ['https://wa.me/whatsapp', 'whatsapp'],
        ['', null],
        [null, null],
    ];

    foreach ($cases as [$url, $expected]) {
        $service = app(\App\Modules\Whatsapp\Services\ConversationAttributionService::class);
        $result = $this->callPrivate($service, 'derivePlatformFromUrl', [$url]);
        $this->assertSame($expected, $result, "Failed for URL: $url");
    }
}

private function callPrivate(object $object, string $method, array $args = []): mixed
{
    $ref = new \ReflectionMethod($object, $method);
    $ref->setAccessible(true);
    return $ref->invokeArgs($object, $args);
}
```

- [ ] **Paso 2: Correr el test para ver que falla**

```bash
php artisan test --filter=it_derives_platform_from_source_url_when_saving_attribution
```

Resultado esperado: `FAIL` con "Method derivePlatformFromUrl does not exist".

- [ ] **Paso 3: Implementar `derivePlatformFromUrl` en ConversationAttributionService**

Buscar el método `referralPayload()` en `app/Modules/Whatsapp/Services/ConversationAttributionService.php` (línea ~117). Agregar el método privado debajo:

```php
private function derivePlatformFromUrl(?string $url): ?string
{
    if ($url === null || $url === '') {
        return null;
    }

    $host = strtolower((string) parse_url($url, PHP_URL_HOST));

    if (str_contains($host, 'instagram.com')) {
        return 'instagram';
    }

    if (str_contains($host, 'facebook.com') || str_contains($host, 'fb.com')) {
        return 'facebook';
    }

    if (str_contains($host, 'wa.me') || str_contains($host, 'whatsapp.com')) {
        return 'whatsapp';
    }

    return null;
}
```

- [ ] **Paso 4: Usar `derivePlatformFromUrl` en el upsert de atribución**

Buscar en el mismo archivo donde se hace el `upsert` o `insert` de `whatsapp_conversation_attributions` (buscar `source_type` en el array del upsert). Agregar la línea de `platform`:

```php
// En el array del upsert, junto a source_type:
'platform' => $this->derivePlatformFromUrl($this->scalar($referralPayload['source_url'] ?? null)),
```

- [ ] **Paso 5: Correr el test**

```bash
php artisan test --filter=it_derives_platform_from_source_url_when_saving_attribution
```

Resultado esperado: `PASS`

- [ ] **Paso 6: Commit**

```bash
git add app/Modules/Whatsapp/Services/ConversationAttributionService.php tests/Feature/WhatsappKpiDashboardTest.php
git commit -m "feat(whatsapp): derive and store ad platform (facebook/instagram) from source_url"
```

---

### Tarea 3: Mostrar `platform` en `adsPerformanceBreakdown`

**Archivos:**
- Modificar: `app/Modules/Whatsapp/Services/KpiDashboardService.php` — método `adsPerformanceBreakdown` (línea 795)

- [ ] **Paso 1: Agregar `platform` al SELECT de la query**

Localizar `adsPerformanceBreakdown` en `KpiDashboardService.php` (línea 795). Reemplazar el SELECT:

```php
// ANTES — línea ~800:
$rows = DB::select(
    'SELECT
        NULLIF(referral_source_id, "") AS source_id,
        NULLIF(referral_headline, "") AS headline,
        NULLIF(referral_media_type, "") AS media_type,
        COUNT(*) AS conversations,
        SUM(is_identified) AS identified,
        SUM(has_booking) AS bookings,
        SUM(has_handoff) AS handoffs
     FROM (' . $base['sql'] . ') analytics_base
     WHERE source_category = "ad"
     GROUP BY referral_source_id, referral_headline, referral_media_type
     ORDER BY bookings DESC, conversations DESC, referral_source_id ASC
     LIMIT 10',
    $base['params']
);

// DESPUÉS:
$rows = DB::select(
    'SELECT
        NULLIF(referral_source_id, "") AS source_id,
        NULLIF(referral_headline, "") AS headline,
        NULLIF(referral_media_type, "") AS media_type,
        NULLIF(a.platform, "") AS platform,
        COUNT(*) AS conversations,
        SUM(is_identified) AS identified,
        SUM(has_booking) AS bookings,
        SUM(has_handoff) AS handoffs
     FROM (' . $base['sql'] . ') analytics_base
     LEFT JOIN whatsapp_conversation_attributions a ON a.conversation_id = analytics_base.conversation_id
     WHERE source_category = "ad"
     GROUP BY referral_source_id, referral_headline, referral_media_type, a.platform
     ORDER BY bookings DESC, conversations DESC, referral_source_id ASC
     LIMIT 50',
    $base['params']
);
```

- [ ] **Paso 2: Agregar `platform` al array de retorno (mismo método)**

```php
// ANTES:
return [
    'source_id' => ...,
    'headline' => ...,
    'media_type' => ...,
    'conversations' => ...,
    ...
];

// DESPUÉS — agregar 'platform':
return [
    'source_id' => $row->source_id !== null ? (string) $row->source_id : null,
    'headline' => $row->headline !== null ? (string) $row->headline : 'Sin headline',
    'media_type' => $row->media_type !== null ? (string) $row->media_type : 'n/d',
    'platform' => $row->platform !== null ? (string) $row->platform : null,
    'platform_label' => match ($row->platform ?? null) {
        'facebook'  => 'Facebook',
        'instagram' => 'Instagram',
        'whatsapp'  => 'WhatsApp',
        default     => 'Desconocido',
    },
    'conversations' => $conversations,
    'identified' => (int) ($row->identified ?? 0),
    'bookings' => $bookings,
    'handoffs' => (int) ($row->handoffs ?? 0),
    'booking_rate' => $conversations > 0 ? round(($bookings / $conversations) * 100, 1) : 0.0,
];
```

- [ ] **Paso 3: Correr los tests existentes para verificar no hay regresiones**

```bash
php artisan test --filter=WhatsappKpiDashboard
```

Resultado esperado: todos en PASS.

- [ ] **Paso 4: Commit**

```bash
git add app/Modules/Whatsapp/Services/KpiDashboardService.php
git commit -m "feat(whatsapp): add platform (facebook/instagram) to ads performance breakdown"
```

---

## FASE 2 — Fix de datos: Filtro de agente histórico

**Objetivo:** Cuando se selecciona un agente en el filtro, los KPIs de analytics deben reflejar todas las conversaciones que el agente atendió, no solo las que tiene asignadas hoy.

**Contexto:** `conversationScopeFilterSql` filtra por `c.assigned_user_id` (asignación actual). Cuando una conversación es transferida, desaparece del historial del agente original. El fix es: cuando se filtra por `agentId`, incluir conversaciones donde el agente haya tenido un handoff asignado, no solo la asignación actual.

---

### Tarea 4: Cambiar scope filter a historial de handoffs

**Archivos:**
- Modificar: `app/Modules/Whatsapp/Services/KpiDashboardService.php` — método `conversationScopeFilterSql` (línea 2214)

- [ ] **Paso 1: Escribir el test**

En `tests/Feature/WhatsappKpiDashboardTest.php`, agregar:

```php
/** @test */
public function agent_filter_includes_transferred_conversations(): void
{
    // Crear agente A y agente B
    $agentA = \App\Models\User::factory()->create(['name' => 'Agente A']);
    $agentB = \App\Models\User::factory()->create(['name' => 'Agente B']);

    // Crear conversación inicialmente asignada a A, luego transferida a B
    $conv = \App\Models\WhatsappConversation::factory()->create([
        'assigned_user_id' => $agentB->id, // actualmente asignada a B
        'created_at' => now()->subDays(2),
    ]);

    // Handoff asignado originalmente al agente A (historial)
    \App\Models\WhatsappHandoff::factory()->create([
        'conversation_id' => $conv->id,
        'assigned_agent_id' => $agentA->id,
        'status' => 'resolved',
        'queued_at' => now()->subDays(2),
    ]);

    $service = app(\App\Modules\Whatsapp\Services\KpiDashboardService::class);
    $dashboard = $service->buildDashboard(
        new \DateTimeImmutable(now()->subDays(7)->format('Y-m-d')),
        new \DateTimeImmutable(now()->format('Y-m-d')),
        null,
        $agentA->id
    );

    // El agente A debe aparecer en handoffs_by_agent aunque la conv ya no está asignada a él
    $agentAHandoffs = collect($dashboard['breakdowns']['handoffs_by_agent'])
        ->firstWhere('user_id', $agentA->id);

    $this->assertNotNull($agentAHandoffs, 'Agente A no aparece en handoffs_by_agent');
    $this->assertGreaterThanOrEqual(1, $agentAHandoffs['assigned_count']);
}
```

- [ ] **Paso 2: Correr el test para verificar falla**

```bash
php artisan test --filter=agent_filter_includes_transferred_conversations
```

Resultado esperado: `FAIL` (el agente A no aparece porque ya no tiene la conversación asignada).

- [ ] **Paso 3: Modificar `conversationScopeFilterSql`**

Localizar el método en línea ~2214 y reemplazarlo completo:

```php
private function conversationScopeFilterSql(string $conversationAlias, string $userAlias, ?int $roleId, ?int $agentId, string $scope): array
{
    $conditions = [];
    $params = [];

    if ($roleId !== null && $roleId > 0) {
        $conditions[] = $userAlias . '.role_id = ?';
        $params[$scope . '_role'] = $roleId;
    }

    if ($agentId !== null && $agentId > 0) {
        // Incluye tanto la asignación actual como conversaciones donde el agente
        // tuvo un handoff histórico (para no perder conversaciones transferidas).
        $conditions[] = '(' . $conversationAlias . '.assigned_user_id = ? OR EXISTS (
            SELECT 1 FROM whatsapp_handoffs wh_scope
            WHERE wh_scope.conversation_id = ' . $conversationAlias . '.id
              AND wh_scope.assigned_agent_id = ?
        ))';
        $params[$scope . '_agent_current'] = $agentId;
        $params[$scope . '_agent_historical'] = $agentId;
    }

    return ['where' => implode(' AND ', $conditions), 'params' => $params];
}
```

- [ ] **Paso 4: Correr el test**

```bash
php artisan test --filter=agent_filter_includes_transferred_conversations
```

Resultado esperado: `PASS`

- [ ] **Paso 5: Correr todos los tests de KPI para verificar no hay regresiones**

```bash
php artisan test --filter=WhatsappKpi
```

Resultado esperado: todos en PASS.

- [ ] **Paso 6: Commit**

```bash
git add app/Modules/Whatsapp/Services/KpiDashboardService.php tests/Feature/WhatsappKpiDashboardTest.php
git commit -m "fix(whatsapp): include historical handoff assignments in agent filter"
```

---

## FASE 3 — Rediseño UX del Dashboard

**Objetivo:** Reorganizar la vista en 3 zonas claras (Ahora / Rendimiento / Detalle), traducir el lenguaje técnico a español clínico, y agregar indicadores de alerta visuales.

**Contexto:** El archivo actual `v2-dashboard.blade.php` tiene 1238 líneas. No se va a reescribir desde cero — se reorganizan las secciones existentes y se cambian los labels. Los datos PHP y lógica quedan igual; solo cambia la presentación.

**Principios aplicados:**
- Los 3-4 KPIs más urgentes: grandes, arriba, con color de alerta si superan umbral
- Renombrar "SLA" → "Respondidos a tiempo", "handoff" → "derivado a agente", etc.
- Secciones de detalle (por agente, ads, breakdown) colapsadas por defecto

---

### Tarea 5: Zona "Ahora" — KPIs en tiempo real arriba del todo

**Archivos:**
- Modificar: `resources/views/whatsapp/v2-dashboard.blade.php`

- [ ] **Paso 1: Agregar CSS para la zona "Ahora"**

Localizar el bloque `<style>` en la vista (alrededor de línea 100). Agregar después del CSS existente:

```css
/* Zona Ahora */
.wa-now-zone {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 12px;
    margin-bottom: 24px;
}
.wa-now-card {
    background: #fff;
    border-radius: 10px;
    padding: 16px 20px;
    border-left: 4px solid #e2e8f0;
    box-shadow: 0 1px 4px rgba(0,0,0,.07);
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.wa-now-card--alert  { border-left-color: #ef4444; background: #fff5f5; }
.wa-now-card--warn   { border-left-color: #f59e0b; background: #fffbeb; }
.wa-now-card--ok     { border-left-color: #10b981; background: #f0fdf4; }
.wa-now-card__value  { font-size: 2rem; font-weight: 700; line-height: 1; }
.wa-now-card__label  { font-size: .82rem; color: #64748b; font-weight: 500; }
.wa-now-card__action { font-size: .75rem; color: #3b82f6; margin-top: 4px; text-decoration: none; }
.wa-now-card__action:hover { text-decoration: underline; }
.wa-section-toggle {
    background: none; border: none; cursor: pointer;
    font-size: .82rem; color: #64748b; padding: 4px 8px;
    border-radius: 4px;
}
.wa-section-toggle:hover { background: #f1f5f9; }
.wa-collapsible { transition: none; }
```

- [ ] **Paso 2: Localizar la sección donde inician los KPI cards (alrededor de línea 440) y ANTES de ella insertar la zona "Ahora"**

Buscar la línea que contiene `@foreach($kpiCards as $card)`. Insertar inmediatamente antes del contenedor que lo rodea:

```blade
{{-- ══ ZONA AHORA (tiempo real) ══ --}}
@php
    $queueTotal    = (int) ($summary['live_queue_total'] ?? 0);
    $sinRespuesta  = (int) ($summary['conversations_lost'] ?? 0);
    $cobertura     = (float) ($summary['attention_rate'] ?? 0);
    $respondidos   = (float) ($summary['sla_assignments_rate'] ?? 0);

    $queueClass    = $queueTotal > 10 ? 'alert' : ($queueTotal > 5 ? 'warn' : 'ok');
    $sinRespClass  = $sinRespuesta > 5 ? 'alert' : ($sinRespuesta > 2 ? 'warn' : 'ok');
    $coberturaClass= $cobertura < 70 ? 'alert' : ($cobertura < 85 ? 'warn' : 'ok');
    $slaClass      = $respondidos < 60 ? 'alert' : ($respondidos < 80 ? 'warn' : 'ok');
@endphp
<div class="wa-now-zone mb-20">
    <div class="wa-now-card wa-now-card--{{ $queueClass }}">
        <div class="wa-now-card__value">{{ $queueTotal }}</div>
        <div class="wa-now-card__label">En espera ahora</div>
        @if($queueTotal > 0)
            <a href="/v2/whatsapp" class="wa-now-card__action">Ver conversaciones →</a>
        @endif
    </div>
    <div class="wa-now-card wa-now-card--{{ $sinRespClass }}">
        <div class="wa-now-card__value">{{ $sinRespuesta }}</div>
        <div class="wa-now-card__label">Sin atender en el periodo</div>
    </div>
    <div class="wa-now-card wa-now-card--{{ $coberturaClass }}">
        <div class="wa-now-card__value">{{ $cobertura }}%</div>
        <div class="wa-now-card__label">De cada 10 que escriben, reciben respuesta</div>
    </div>
    <div class="wa-now-card wa-now-card--{{ $slaClass }}">
        <div class="wa-now-card__value">{{ $respondidos }}%</div>
        <div class="wa-now-card__label">Respondidos a tiempo ({{ $summary['sla_target_minutes'] ?? 15 }} min)</div>
    </div>
</div>
{{-- ══ FIN ZONA AHORA ══ --}}
```

- [ ] **Paso 3: Renombrar labels en los KPI cards**

En el array `$kpiCards` (alrededor de línea 448), reemplazar los labels técnicos:

```php
// CAMBIOS DE LABEL — buscar y reemplazar estos strings exactos:

// 'SLA asignación (objetivo: ...) → ya mostrado en zona "Ahora", marcar con nota
['label' => 'SLA asignación (objetivo: ' . $slaTargetMinutes . ' min)', ...]
// REEMPLAZAR label por:
['label' => 'Respondidos a tiempo (meta: ' . $slaTargetMinutes . ' min)', ...]

// 'Cola activa' →
['label' => 'Conversaciones en atención ahora', ...]

// 'Ventana 24h abierta' →
['label' => 'Pueden recibir mensaje libre (ventana activa)', ...]

// 'Requiere plantilla' →
['label' => 'Solo pueden reabrirse con plantilla', ...]

// 'Conversaciones sin respuesta humana' →
['label' => 'Sin atender por humano', ...]

// 'Conversaciones inactivas >24h sin respuesta humana' →
['label' => 'Posiblemente abandonadas (sin respuesta >24h)', ...]

// 'Sin respuesta humana con handoff >24h' →
['label' => 'Urgente: derivado y sin atender >24h', ...]

// 'Transferencias' →
['label' => 'Derivaciones entre agentes', ...]

// 'Pico simultáneo' →
['label' => 'Máximo de conversaciones abiertas al mismo tiempo', ...]
```

- [ ] **Paso 4: Colapsar secciones de detalle por defecto**

Localizar los paneles de "Por agente", "Por rol", "Ads" (alrededor de líneas 1084, 1218, 976). Envolver cada panel en:

```blade
{{-- Ejemplo para el panel de agentes --}}
<div class="col-12">
    <div class="wa-kpi-panel">
        <div class="wa-kpi-panel__head" style="cursor:pointer;"
             onclick="this.nextElementSibling.classList.toggle('d-none')">
            <div class="wa-kpi-title-row">
                <div class="wa-kpi-sideheading__title">Detalle por agente</div>
                <span class="wa-section-toggle">▼ ver / ocultar</span>
            </div>
        </div>
        <div class="wa-kpi-panel__body p-0 d-none wa-collapsible">
            {{-- contenido existente de la tabla --}}
        </div>
    </div>
</div>
```

Repetir el mismo patrón para: panel de handoffs por rol, panel de handoffs por agente, panel de ads.

- [ ] **Paso 5: En la tabla de Ads, agregar columna Platform**

Localizar el `<thead>` de la tabla de ads (alrededor de línea 999):

```blade
{{-- ANTES: --}}
<tr>
    <th>Anuncio</th>
    <th>Media</th>
    ...
</tr>

{{-- DESPUÉS: --}}
<tr>
    <th>Anuncio</th>
    <th>Red social</th>
    <th>Tipo de pieza</th>
    <th>Conversaciones</th>
    <th>Identificadas</th>
    <th>Citas</th>
    <th>Handoffs</th>
</tr>
```

Y en el `@forelse($analyticsAds as $row)`:

```blade
<tr>
    <td>
        <div class="fw-600">{{ $row['headline'] }}</div>
        <div class="text-muted small">ID: {{ $row['source_id'] ?? 'Sin ID' }}</div>
    </td>
    <td>
        @php
            $platformIcons = ['facebook' => '📘', 'instagram' => '📷', 'whatsapp' => '💬'];
            $icon = $platformIcons[$row['platform'] ?? ''] ?? '❓';
        @endphp
        {{ $icon }} {{ $row['platform_label'] ?? 'Desconocido' }}
    </td>
    <td>{{ $row['media_type'] }}</td>
    <td>{{ $row['conversations'] }}</td>
    <td>{{ $row['identified'] }}</td>
    <td>{{ $row['bookings'] }} <span class="text-muted">({{ $row['booking_rate'] }}%)</span></td>
    <td>{{ $row['handoffs'] }}</td>
</tr>
```

- [ ] **Paso 6: Probar visualmente**

```bash
php artisan serve
```

Abrir en browser: `/v2/whatsapp/dashboard`  
Verificar:
- Zona "Ahora" visible arriba con colores de alerta
- Secciones de detalle colapsadas
- Tabla de ads con columna "Red social"
- Labels en español clínico

- [ ] **Paso 7: Commit**

```bash
git add resources/views/whatsapp/v2-dashboard.blade.php
git commit -m "feat(whatsapp): redesign dashboard UX — 3 zones, clinical labels, collapsible detail, ads platform"
```

---

## FASE 4 — Guía de Uso Embebida

**Objetivo:** Crear una guía interactiva tipo "tour de la pantalla" que el personal clínico pueda abrir con un botón, slide por slide, explicando cada sección del dashboard en lenguaje simple. Se embebe en la vista como un modal.

**Decisión de diseño:** No Figma embebido (requiere account externa y tiene riesgo de privacidad). En su lugar: modal HTML propio con slides estilo presentación, fullscreen en móvil, con ilustraciones SVG inline. Mismo look-and-feel que el dashboard. Se puede actualizar sin dependencias externas.

---

### Tarea 6: Crear el partial de la guía

**Archivos:**
- Crear: `resources/views/whatsapp/partials/dashboard-guide.blade.php`

- [ ] **Paso 1: Crear el archivo del partial**

```blade
{{-- resources/views/whatsapp/partials/dashboard-guide.blade.php --}}
{{-- Guía interactiva del Dashboard WhatsApp — modal slide-through --}}

<button type="button"
        class="btn btn-sm btn-outline-secondary"
        id="wa-guide-open"
        style="position:fixed; bottom:24px; right:24px; z-index:1050; border-radius:50px; padding:8px 18px; box-shadow:0 2px 8px rgba(0,0,0,.15);">
    📖 ¿Cómo usar este panel?
</button>

<div id="wa-guide-overlay"
     style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:1060; align-items:center; justify-content:center;">
    <div id="wa-guide-modal"
         style="background:#fff; border-radius:16px; width:min(680px,95vw); max-height:90vh; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 8px 32px rgba(0,0,0,.22);">

        {{-- Header --}}
        <div style="padding:20px 24px 16px; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center;">
            <div>
                <div style="font-size:.75rem; color:#94a3b8; font-weight:600; text-transform:uppercase; letter-spacing:.06em;">Guía de uso</div>
                <div style="font-size:1.1rem; font-weight:700; color:#0f172a;">Panel de WhatsApp</div>
            </div>
            <button type="button" id="wa-guide-close"
                    style="background:none;border:none;cursor:pointer;font-size:1.4rem;color:#94a3b8;padding:4px;">✕</button>
        </div>

        {{-- Slides --}}
        <div id="wa-guide-slides" style="flex:1; overflow-y:auto; padding:28px 24px;">

            {{-- Slide 1: Bienvenida --}}
            <div class="wa-guide-slide" data-slide="1">
                <div style="text-align:center; padding:12px 0 20px;">
                    <div style="font-size:3rem;">📊</div>
                    <h2 style="font-size:1.3rem; font-weight:700; margin:12px 0 8px;">¿Qué es este panel?</h2>
                    <p style="color:#475569; line-height:1.6; max-width:480px; margin:0 auto;">
                        Este panel te muestra <strong>cómo está funcionando el canal de WhatsApp</strong> de la clínica: cuántos pacientes están escribiendo, cuántos fueron atendidos, cuántas citas se generaron y cómo está respondiendo el equipo.
                    </p>
                </div>
                <div style="background:#f8fafc; border-radius:10px; padding:16px 20px; margin-top:12px;">
                    <div style="font-weight:600; margin-bottom:8px; color:#0f172a;">El panel tiene 3 partes:</div>
                    <div style="display:flex; flex-direction:column; gap:10px;">
                        <div style="display:flex; gap:12px; align-items:flex-start;">
                            <span style="font-size:1.4rem;">🚦</span>
                            <div><strong>Lo que pasa ahora</strong> — números en tiempo real con colores de alerta</div>
                        </div>
                        <div style="display:flex; gap:12px; align-items:flex-start;">
                            <span style="font-size:1.4rem;">📈</span>
                            <div><strong>Rendimiento del periodo</strong> — análisis del rango de fechas que seleccionas</div>
                        </div>
                        <div style="display:flex; gap:12px; align-items:flex-start;">
                            <span style="font-size:1.4rem;">🔍</span>
                            <div><strong>Detalle</strong> — datos por agente, por anuncio, por sede (se abre con clic)</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Slide 2: Zona Ahora --}}
            <div class="wa-guide-slide" data-slide="2" style="display:none;">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:16px;">
                    <span style="font-size:2rem;">🚦</span>
                    <h2 style="font-size:1.2rem; font-weight:700; margin:0;">Lo que pasa ahora</h2>
                </div>
                <p style="color:#475569; line-height:1.6; margin-bottom:16px;">
                    Estos cuatro números se actualizan con el rango de fechas que tienes seleccionado. Los colores te avisan si hay algo que atender:
                </p>
                <div style="display:flex; flex-direction:column; gap:12px;">
                    <div style="border-left:4px solid #ef4444; background:#fff5f5; padding:12px 16px; border-radius:6px;">
                        <strong style="color:#dc2626;">Rojo</strong> — Situación crítica que necesita atención inmediata
                    </div>
                    <div style="border-left:4px solid #f59e0b; background:#fffbeb; padding:12px 16px; border-radius:6px;">
                        <strong style="color:#d97706;">Amarillo</strong> — Situación a monitorear, no urgente
                    </div>
                    <div style="border-left:4px solid #10b981; background:#f0fdf4; padding:12px 16px; border-radius:6px;">
                        <strong style="color:#059669;">Verde</strong> — Todo dentro de lo normal
                    </div>
                </div>
                <div style="background:#f8fafc; border-radius:10px; padding:16px 20px; margin-top:16px;">
                    <div style="font-weight:600; margin-bottom:10px;">¿Qué significa cada número?</div>
                    <div style="display:flex; flex-direction:column; gap:8px; font-size:.9rem; color:#475569;">
                        <div><strong style="color:#0f172a;">En espera ahora:</strong> Conversaciones que aún no fueron asignadas a ningún agente.</div>
                        <div><strong style="color:#0f172a;">Sin atender:</strong> Pacientes que escribieron y no recibieron respuesta de ninguna persona.</div>
                        <div><strong style="color:#0f172a;">De cada 10 que escriben:</strong> Porcentaje de pacientes que sí recibieron respuesta humana.</div>
                        <div><strong style="color:#0f172a;">Respondidos a tiempo:</strong> Cuántos pacientes recibieron respuesta dentro del tiempo objetivo.</div>
                    </div>
                </div>
            </div>

            {{-- Slide 3: Filtros --}}
            <div class="wa-guide-slide" data-slide="3" style="display:none;">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:16px;">
                    <span style="font-size:2rem;">🔎</span>
                    <h2 style="font-size:1.2rem; font-weight:700; margin:0;">Cómo usar los filtros</h2>
                </div>
                <div style="display:flex; flex-direction:column; gap:14px;">
                    <div style="border:1px solid #e2e8f0; border-radius:8px; padding:14px 16px;">
                        <div style="font-weight:600; margin-bottom:4px;">📅 Rango de fechas</div>
                        <div style="color:#475569; font-size:.9rem;">Filtra todos los datos al periodo que selecciones. Por defecto muestra los últimos 30 días.</div>
                    </div>
                    <div style="border:1px solid #e2e8f0; border-radius:8px; padding:14px 16px;">
                        <div style="font-weight:600; margin-bottom:4px;">👤 Agente</div>
                        <div style="color:#475569; font-size:.9rem;">Muestra solo los datos del agente seleccionado, incluyendo conversaciones que ya no tiene asignadas pero que atendió en el periodo.</div>
                    </div>
                    <div style="border:1px solid #e2e8f0; border-radius:8px; padding:14px 16px;">
                        <div style="font-weight:600; margin-bottom:4px;">⏱ Tiempo objetivo de respuesta</div>
                        <div style="color:#475569; font-size:.9rem;">Define en minutos cuánto tiempo máximo debería tardar el equipo en responder. El porcentaje "respondidos a tiempo" se calcula contra este valor. Por defecto: 15 minutos.</div>
                    </div>
                </div>
            </div>

            {{-- Slide 4: Ads --}}
            <div class="wa-guide-slide" data-slide="4" style="display:none;">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:16px;">
                    <span style="font-size:2rem;">📣</span>
                    <h2 style="font-size:1.2rem; font-weight:700; margin:0;">Tabla de anuncios</h2>
                </div>
                <p style="color:#475569; line-height:1.6; margin-bottom:16px;">
                    Esta tabla muestra qué anuncios de Facebook e Instagram están generando conversaciones en WhatsApp, y cuáles de esas conversaciones terminaron en cita.
                </p>
                <div style="display:flex; flex-direction:column; gap:10px; font-size:.9rem;">
                    <div style="display:flex; gap:10px;">
                        <span style="min-width:120px; font-weight:600; color:#0f172a;">Anuncio:</span>
                        <span style="color:#475569;">Nombre del anuncio tal como aparece en Meta Ads Manager.</span>
                    </div>
                    <div style="display:flex; gap:10px;">
                        <span style="min-width:120px; font-weight:600; color:#0f172a;">Red social:</span>
                        <span style="color:#475569;">Si el paciente llegó desde Facebook (📘) o Instagram (📷).</span>
                    </div>
                    <div style="display:flex; gap:10px;">
                        <span style="min-width:120px; font-weight:600; color:#0f172a;">Conversaciones:</span>
                        <span style="color:#475569;">Cuántas personas llegaron por ese anuncio.</span>
                    </div>
                    <div style="display:flex; gap:10px;">
                        <span style="min-width:120px; font-weight:600; color:#0f172a;">Identificadas:</span>
                        <span style="color:#475569;">Cuántas de ellas ya tenían historia clínica en el sistema (pacientes conocidos).</span>
                    </div>
                    <div style="display:flex; gap:10px;">
                        <span style="min-width:120px; font-weight:600; color:#0f172a;">Citas (%):</span>
                        <span style="color:#475569;">Cuántas terminaron agendando cita. El porcentaje muestra la efectividad del anuncio.</span>
                    </div>
                </div>
                <div style="background:#fffbeb; border:1px solid #fcd34d; border-radius:8px; padding:12px 16px; margin-top:16px; font-size:.85rem; color:#92400e;">
                    💡 <strong>Tip:</strong> Un anuncio con muchas conversaciones pero pocas citas puede indicar que el mensaje no es específico o que el flujo del bot necesita ajuste.
                </div>
            </div>

            {{-- Slide 5: Por agente --}}
            <div class="wa-guide-slide" data-slide="5" style="display:none;">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:16px;">
                    <span style="font-size:2rem;">👥</span>
                    <h2 style="font-size:1.2rem; font-weight:700; margin:0;">Estadísticas por agente</h2>
                </div>
                <p style="color:#475569; line-height:1.6; margin-bottom:16px;">
                    Las tablas de detalle (colapsadas al final del panel) muestran el rendimiento individual de cada agente.
                </p>
                <div style="display:flex; flex-direction:column; gap:10px; font-size:.9rem;">
                    <div style="border:1px solid #e2e8f0; border-radius:8px; padding:14px 16px;">
                        <div style="font-weight:600; margin-bottom:4px;">Conversaciones atendidas</div>
                        <div style="color:#475569;">Cuántas conversaciones recibieron al menos un mensaje del agente.</div>
                    </div>
                    <div style="border:1px solid #e2e8f0; border-radius:8px; padding:14px 16px;">
                        <div style="font-weight:600; margin-bottom:4px;">Tiempo promedio de primera respuesta</div>
                        <div style="color:#475569;">Cuántos minutos tardó el agente en responder desde que le fue asignada la conversación.</div>
                    </div>
                    <div style="border:1px solid #e2e8f0; border-radius:8px; padding:14px 16px;">
                        <div style="font-weight:600; margin-bottom:4px;">Derivaciones asignadas / resueltas</div>
                        <div style="color:#475569;">Cuántas conversaciones recibió el agente y cuántas marcó como resueltas.</div>
                    </div>
                </div>
                <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:12px 16px; margin-top:16px; font-size:.85rem; color:#1e40af;">
                    💡 <strong>Nota:</strong> Si filtras por agente en el selector de arriba, TODOS los KPIs del panel se filtran para ese agente.
                </div>
            </div>

        </div>{{-- /slides --}}

        {{-- Footer con navegación --}}
        <div style="padding:16px 24px; border-top:1px solid #f1f5f9; display:flex; align-items:center; justify-content:space-between;">
            <div style="display:flex; gap:8px;" id="wa-guide-dots">
                @for($i = 1; $i <= 5; $i++)
                    <button class="wa-guide-dot" data-target="{{ $i }}"
                            style="width:8px;height:8px;border-radius:50%;border:none;cursor:pointer;background:{{ $i === 1 ? '#3b82f6' : '#cbd5e1' }};padding:0;"></button>
                @endfor
            </div>
            <div style="display:flex; gap:8px;">
                <button type="button" id="wa-guide-prev"
                        class="btn btn-sm btn-light" style="display:none;">← Anterior</button>
                <button type="button" id="wa-guide-next"
                        class="btn btn-sm btn-primary">Siguiente →</button>
            </div>
        </div>

    </div>{{-- /modal --}}
</div>{{-- /overlay --}}

<script>
(function() {
    const TOTAL = 5;
    let current = 1;

    const overlay  = document.getElementById('wa-guide-overlay');
    const btnOpen  = document.getElementById('wa-guide-open');
    const btnClose = document.getElementById('wa-guide-close');
    const btnNext  = document.getElementById('wa-guide-next');
    const btnPrev  = document.getElementById('wa-guide-prev');

    function showSlide(n) {
        document.querySelectorAll('.wa-guide-slide').forEach(s => s.style.display = 'none');
        const slide = document.querySelector('[data-slide="' + n + '"]');
        if (slide) slide.style.display = 'block';

        document.querySelectorAll('.wa-guide-dot').forEach(d => {
            d.style.background = parseInt(d.dataset.target) === n ? '#3b82f6' : '#cbd5e1';
        });

        btnPrev.style.display = n > 1 ? 'inline-block' : 'none';
        btnNext.textContent   = n < TOTAL ? 'Siguiente →' : 'Cerrar';
        current = n;
    }

    btnOpen.addEventListener('click', function() {
        overlay.style.display = 'flex';
        showSlide(1);
    });

    btnClose.addEventListener('click', function() { overlay.style.display = 'none'; });
    overlay.addEventListener('click', function(e) { if (e.target === overlay) overlay.style.display = 'none'; });

    btnNext.addEventListener('click', function() {
        if (current < TOTAL) showSlide(current + 1);
        else overlay.style.display = 'none';
    });

    btnPrev.addEventListener('click', function() {
        if (current > 1) showSlide(current - 1);
    });

    document.querySelectorAll('.wa-guide-dot').forEach(d => {
        d.addEventListener('click', function() { showSlide(parseInt(this.dataset.target)); });
    });
})();
</script>
```

- [ ] **Paso 2: Incluir el partial en la vista del dashboard**

En `resources/views/whatsapp/v2-dashboard.blade.php`, localizar el final del archivo antes de `@endsection` (alrededor de línea 1230). Agregar:

```blade
{{-- Guía de uso embebida --}}
@include('whatsapp.partials.dashboard-guide')
```

- [ ] **Paso 3: Probar la guía**

```bash
php artisan serve
```

Abrir `/v2/whatsapp/dashboard`. Verificar:
- El botón "📖 ¿Cómo usar este panel?" aparece fijo en la esquina inferior derecha
- Al hacer clic abre el modal
- La navegación con "Siguiente / Anterior" y los puntos funciona
- El modal se cierra con ✕ o clic fuera
- En mobile (resize a 375px) el modal se ve bien (usa `min(680px, 95vw)`)
- Slide 4 menciona Facebook/Instagram (coherente con los datos de ads)

- [ ] **Paso 4: Commit**

```bash
git add resources/views/whatsapp/partials/dashboard-guide.blade.php
git add resources/views/whatsapp/v2-dashboard.blade.php
git commit -m "feat(whatsapp): add embedded interactive user guide to dashboard"
```

---

## Orden de ejecución recomendado

```
Fase 1 (Ads + platform)     ─── 1-2 horas ─── impacto inmediato visible en tabla de ads
Fase 2 (Agente histórico)   ─── 1-2 horas ─── fix de datos crítico para supervisión
Fase 3 (UX rediseño)        ─── 2-3 horas ─── cambio visual más grande, más riesgo visual
Fase 4 (Guía embebida)      ─── 1 hora   ─── solo frontend, cero riesgo de datos
```

Cada fase es independiente. Puedes entregar Fase 4 (guía) mientras trabajas Fase 1-2 si quieres mostrar avance rápido al equipo.

---

## Checklist de cobertura final

- [x] Platform de ads (FB/IG) derivada de source_url
- [x] Platform mostrada en tabla de ads con ícono
- [x] Filtro agente incluye historial de handoffs
- [x] Zona "Ahora" con colores de alerta
- [x] Labels en español clínico
- [x] Secciones de detalle colapsadas por defecto
- [x] Guía slide-through embebida en la vista
- [x] Guía explica: zona ahora, filtros, ads, por agente
- [x] Sin dependencias externas (sin Figma, sin CDNs nuevos)
- [x] Tests para cada fix de datos
