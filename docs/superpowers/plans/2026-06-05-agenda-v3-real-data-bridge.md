# Agenda V3 — Real Data Bridge — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Conectar el frontend Agenda V3 (PR #364) con data real de `procedimiento_proyectado`/SigCenter, eliminando los médicos ficticios del seeder y haciendo que `fetchPPCitas()` retorne citas reales.

**Architecture:** Tres cambios quirúrgicos en el branch `claude/zen-hamilton-IyzzU`: (1) el seeder deja de insertar médicos falsos, (2) `syncMedicosFromPP()` hace full-replace correcto con TTL reducido, (3) el filtro de sede en `fetchPPCitas()` se vuelve case-insensitive. Se agrega un endpoint `POST /sync` para forzar re-sync sin reiniciar servidor.

**Tech Stack:** PHP 8.3, Laravel 10, MySQL — sin dependencias nuevas. PHP CLI: `/usr/bin/php8.3-cli`.

---

## Archivos a modificar

| Archivo | Cambio |
|---------|--------|
| `laravel-app/database/seeders/AgendaV3Seeder.php` | Truncar `agenda_medicos` al inicio; eliminar el bloque `upsert` de médicos |
| `laravel-app/app/Modules/Agenda/Http/Controllers/AgendaV3Controller.php` | Fix `syncMedicosFromPP()`: deactivar ausentes, TTL 30 min; fix `fetchPPCitas()`: sede case-insensitive; nuevo método `forceSync()`  |
| `laravel-app/routes/v2/agenda.php` | Registrar `POST /api/agenda/v3/sync` |

---

## Task 1: Checkout del branch de trabajo

**Files:**
- No modifica archivos — solo cambia el branch activo

- [ ] **Step 1: Hacer checkout del branch del PR**

```bash
git fetch origin claude/zen-hamilton-IyzzU
git checkout claude/zen-hamilton-IyzzU
```

Expected: `Switched to branch 'claude/zen-hamilton-IyzzU'`

---

## Task 2: Limpiar el seeder de médicos falsos

**Files:**
- Modify: `laravel-app/database/seeders/AgendaV3Seeder.php`

El seeder actualmente inserta 7 médicos ficticios (m_ramirez, m_vargas, etc.). Hay que eliminarlo y agregar un truncate al inicio para limpiar datos viejos de corridas anteriores.

- [ ] **Step 1: Reemplazar el método `run()` del seeder**

Abrir `laravel-app/database/seeders/AgendaV3Seeder.php` y reemplazar el método `run()` completo por:

```php
public function run(): void
{
    // Limpiar médicos falsos de corridas anteriores
    DB::table('agenda_medicos')->truncate();

    DB::table('agenda_sedes')->upsert([
        ['id' => 'ceibos',    'label' => 'Ceibos',     'abrev' => 'CB', 'apertura' => '08:00:00', 'cierre' => '18:00:00', 'activo' => 1],
        ['id' => 'villaclub', 'label' => 'Villa Club', 'abrev' => 'VC', 'apertura' => '08:00:00', 'cierre' => '17:00:00', 'activo' => 1],
    ], ['id'], ['label', 'abrev', 'apertura', 'cierre', 'activo']);

    // agenda_medicos se puebla automáticamente desde procedimiento_proyectado
    // vía syncMedicosFromPP() en el primer hit a GET /api/agenda/v3/config

    DB::table('agenda_salas')->upsert([
        ['id' => 's_cons1', 'sede_id' => 'ceibos',    'label' => 'Consultorio 1',      'tipo' => 'consultorio',  'area' => 'consulta',   'cap' => 1, 'activo' => 1],
        ['id' => 's_cons2', 'sede_id' => 'ceibos',    'label' => 'Consultorio 2',      'tipo' => 'consultorio',  'area' => 'consulta',   'cap' => 1, 'activo' => 1],
        ['id' => 's_cons3', 'sede_id' => 'ceibos',    'label' => 'Consultorio 3',      'tipo' => 'consultorio',  'area' => 'consulta',   'cap' => 1, 'activo' => 1],
        ['id' => 's_opto',  'sede_id' => 'ceibos',    'label' => 'Box optometría',     'tipo' => 'box',          'area' => 'consulta',   'cap' => 1, 'activo' => 1],
        ['id' => 's_qx1',   'sede_id' => 'ceibos',    'label' => 'Quirófano 1',        'tipo' => 'quirofano',    'area' => 'quirurgico', 'cap' => 1, 'activo' => 1],
        ['id' => 's_qx2',   'sede_id' => 'ceibos',    'label' => 'Quirófano 2',        'tipo' => 'quirofano',    'area' => 'quirurgico', 'cap' => 1, 'activo' => 1],
        ['id' => 's_proc',  'sede_id' => 'ceibos',    'label' => 'Sala procedimientos','tipo' => 'procedimiento','area' => 'quirurgico', 'cap' => 1, 'activo' => 1],
        ['id' => 's_laser', 'sede_id' => 'ceibos',    'label' => 'Sala láser',         'tipo' => 'laser',        'area' => 'quirurgico', 'cap' => 1, 'activo' => 1],
        ['id' => 's_img1',  'sede_id' => 'ceibos',    'label' => 'Imágenes A (OCT)',   'tipo' => 'imagen',       'area' => 'imagenes',   'cap' => 1, 'activo' => 1],
        ['id' => 's_img2',  'sede_id' => 'ceibos',    'label' => 'Imágenes B (campo)', 'tipo' => 'imagen',       'area' => 'imagenes',   'cap' => 1, 'activo' => 1],
        ['id' => 's_com',   'sede_id' => 'ceibos',    'label' => 'Asesoría comercial', 'tipo' => 'comercial',    'area' => 'comercial',  'cap' => 1, 'activo' => 1],
        ['id' => 's_vcA',   'sede_id' => 'villaclub', 'label' => 'Consultorio A',      'tipo' => 'consultorio',  'area' => 'consulta',   'cap' => 1, 'activo' => 1],
        ['id' => 's_vcB',   'sede_id' => 'villaclub', 'label' => 'Consultorio B',      'tipo' => 'consultorio',  'area' => 'consulta',   'cap' => 1, 'activo' => 1],
        ['id' => 's_vcqx',  'sede_id' => 'villaclub', 'label' => 'Quirófano VC',       'tipo' => 'quirofano',    'area' => 'quirurgico', 'cap' => 1, 'activo' => 1],
        ['id' => 's_vcimg', 'sede_id' => 'villaclub', 'label' => 'Imágenes VC',        'tipo' => 'imagen',       'area' => 'imagenes',   'cap' => 1, 'activo' => 1],
    ], ['id'], ['sede_id', 'label', 'tipo', 'area', 'cap', 'activo']);

    DB::table('agenda_tipos_cita')->upsert([
        ['id' => 't_cons',    'label' => 'Consulta oftalmológica',    'area' => 'consulta',   'dur_minutos' => 20, 'requiere_tipo_sala' => '["consultorio"]',     'activo' => 1],
        ['id' => 't_primera', 'label' => 'Consulta primera vez',      'area' => 'consulta',   'dur_minutos' => 30, 'requiere_tipo_sala' => '["consultorio"]',     'activo' => 1],
        ['id' => 't_postop',  'label' => 'Control post-operatorio',   'area' => 'consulta',   'dur_minutos' => 15, 'requiere_tipo_sala' => '["consultorio"]',     'activo' => 1],
        ['id' => 't_opto',    'label' => 'Optometría / refracción',   'area' => 'consulta',   'dur_minutos' => 30, 'requiere_tipo_sala' => '["box","consultorio"]','activo' => 1],
        ['id' => 't_faco',    'label' => 'Facoemulsificación + LIO',  'area' => 'quirurgico', 'dur_minutos' => 45, 'requiere_tipo_sala' => '["quirofano"]',        'activo' => 1],
        ['id' => 't_vpp',     'label' => 'Vitrectomía pars plana',    'area' => 'quirurgico', 'dur_minutos' => 90, 'requiere_tipo_sala' => '["quirofano"]',        'activo' => 1],
        ['id' => 't_antivegf','label' => 'Inyección intravítrea',     'area' => 'quirurgico', 'dur_minutos' => 20, 'requiere_tipo_sala' => '["procedimiento"]',    'activo' => 1],
        ['id' => 't_yag',     'label' => 'Capsulotomía láser YAG',    'area' => 'quirurgico', 'dur_minutos' => 15, 'requiere_tipo_sala' => '["laser"]',            'activo' => 1],
        ['id' => 't_oct',     'label' => 'OCT macular',               'area' => 'imagenes',   'dur_minutos' => 15, 'requiere_tipo_sala' => '["imagen"]',           'activo' => 1],
        ['id' => 't_campo',   'label' => 'Campimetría 24-2',          'area' => 'imagenes',   'dur_minutos' => 20, 'requiere_tipo_sala' => '["imagen"]',           'activo' => 1],
        ['id' => 't_topo',    'label' => 'Topografía corneal',        'area' => 'imagenes',   'dur_minutos' => 15, 'requiere_tipo_sala' => '["imagen"]',           'activo' => 1],
        ['id' => 't_cotiza',  'label' => 'Cotización / afiliación',   'area' => 'comercial',  'dur_minutos' => 20, 'requiere_tipo_sala' => '["comercial"]',        'activo' => 1],
        ['id' => 't_preqx',   'label' => 'Valoración pre-quirúrgica', 'area' => 'comercial',  'dur_minutos' => 15, 'requiere_tipo_sala' => '["comercial"]',        'activo' => 1],
    ], ['id'], ['label', 'area', 'dur_minutos', 'requiere_tipo_sala', 'activo']);

    $horarios = [
        ['medico_id' => 'md_dra_carolina_ramirez', 'dia_semana' => 1, 'hora_ini' => '08:00:00', 'hora_fin' => '13:00:00', 'sede_id' => 'ceibos',    'activo' => 1],
        ['medico_id' => 'md_dra_carolina_ramirez', 'dia_semana' => 4, 'hora_ini' => '08:00:00', 'hora_fin' => '14:00:00', 'sede_id' => 'ceibos',    'activo' => 1],
    ];

    if (DB::table('agenda_horarios')->count() === 0) {
        DB::table('agenda_horarios')->insert($horarios);
    }
}
```

> **Nota:** Los IDs de horarios de ejemplo ya usan el formato `md_` que genera `doctorSlug()`. Si los médicos reales del PP tienen slugs distintos, esos horarios simplemente no matchean — no es un error, se configurarán manualmente.

- [ ] **Step 2: Correr el seeder en staging para limpiar los falsos**

En el servidor de staging:
```bash
/usr/bin/php8.3-cli artisan db:seed --class=AgendaV3Seeder
```

Expected: Sin errores. `agenda_medicos` queda vacía (se llenará en el próximo hit a `/config`).

- [ ] **Step 3: Commit**

```bash
git add laravel-app/database/seeders/AgendaV3Seeder.php
git commit -m "fix(agenda-v3): seeder no inserta médicos falsos, trunca tabla al inicio"
```

---

## Task 3: Fix `syncMedicosFromPP()` — full-replace + TTL 30 min

**Files:**
- Modify: `laravel-app/app/Modules/Agenda/Http/Controllers/AgendaV3Controller.php` (método `syncMedicosFromPP`, líneas ~424-476)

El problema actual: la lógica de limpieza `where('id', 'not like', 'md_%')->delete()` es incorrecta porque los médicos nuevos que inserta el sync SÍ usan prefix `md_` (generado por `doctorSlug()`). Además la cache de 6h impide re-sync. 

El fix: reemplazar por un full-replace: obtener todos los slugs del PP, upsertarlos, luego desactivar los que no aparecieron.

- [ ] **Step 1: Reemplazar el método `syncMedicosFromPP()` completo**

Localizar el método en el controller (empieza en `private function syncMedicosFromPP(): void`) y reemplazarlo por:

```php
private function syncMedicosFromPP(): void
{
    if (Cache::has('agenda_v3.medicos_synced')) {
        return;
    }

    try {
        $rawDoctors = DB::select(
            "SELECT DISTINCT TRIM(doctor) AS doctor
             FROM procedimiento_proyectado
             WHERE COALESCE(sigcenter_present, 1) = 1
               AND doctor IS NOT NULL AND TRIM(doctor) != ''
             LIMIT 60"
        );

        $defaultSede = (string) (DB::table('agenda_sedes')->where('activo', true)->value('id') ?? 'ceibos');
        $colors      = ['#5156be', '#2ca361', '#d34b5b', '#d59623', '#3d7ac7', '#7c5fc2', '#4a9a9e', '#b55c32'];
        $idx         = 0;
        $syncedIds   = [];

        foreach ($rawDoctors as $row) {
            $name = trim((string) ($row->doctor ?? ''));
            if ($name === '' || !$this->isDoctorLikeName($name)) {
                continue;
            }

            $id    = $this->doctorSlug($name);
            $label = $this->formatDoctorName($name);

            DB::table('agenda_medicos')->updateOrInsert(
                ['id' => $id],
                [
                    'nombre'       => $label,
                    'especialidad' => 'Oftalmología',
                    'areas'        => '["consulta","quirurgico","imagenes"]',
                    'sede_id'      => $defaultSede,
                    'color'        => $colors[$idx % count($colors)],
                    'iniciales'    => $this->getIniciales($label),
                    'activo'       => true,
                ]
            );

            $syncedIds[] = $id;
            $idx++;
        }

        // Desactivar médicos que ya no aparecen en PP (en vez de borrar)
        if (!empty($syncedIds)) {
            DB::table('agenda_medicos')
                ->whereNotIn('id', $syncedIds)
                ->update(['activo' => false]);
        }

        Cache::put('agenda_v3.medicos_synced', true, 1800); // 30 minutos
    } catch (\Throwable) {
        // Non-fatal — app still works with existing data
    }
}
```

- [ ] **Step 2: Verificar que no hay referencias al TTL viejo (21600) en el archivo**

```bash
grep -n "21600\|medicos_synced" laravel-app/app/Modules/Agenda/Http/Controllers/AgendaV3Controller.php
```

Expected: solo aparece `1800` y la referencia en `forceSync()` (que se agrega en Task 4). Si aparece `21600`, eliminarlo.

- [ ] **Step 3: Commit parcial**

```bash
git add laravel-app/app/Modules/Agenda/Http/Controllers/AgendaV3Controller.php
git commit -m "fix(agenda-v3): syncMedicosFromPP full-replace + TTL 30min"
```

---

## Task 4: Agregar endpoint `POST /sync` para force re-sync

**Files:**
- Modify: `laravel-app/app/Modules/Agenda/Http/Controllers/AgendaV3Controller.php` (agregar método público `forceSync`)
- Modify: `laravel-app/routes/v2/agenda.php` (registrar la ruta)

- [ ] **Step 1: Agregar el método `forceSync()` al controller**

Añadir este método público justo después de `deleteBloqueo()` (antes del bloque `private function`):

```php
public function forceSync(Request $request): JsonResponse
{
    if (!Auth::check()) {
        return response()->json(['error' => 'Sesión expirada'], 401);
    }

    Cache::forget('agenda_v3.medicos_synced');
    $this->syncMedicosFromPP();

    $count = DB::table('agenda_medicos')->where('activo', true)->count();

    return response()->json([
        'ok'      => true,
        'medicos' => $count,
        'mensaje' => "Sync completado: {$count} médicos activos.",
    ]);
}
```

- [ ] **Step 2: Registrar la ruta en `routes/v2/agenda.php`**

Agregar al final del bloque de rutas V3 (después de `deleteBloqueo`):

```php
Route::post('/api/agenda/v3/sync', [AgendaV3Controller::class, 'forceSync']);
```

El bloque completo de rutas V3 debe quedar:

```php
// Agenda V3 — React SPA + API
Route::get('/agenda/v3',                          [AgendaV3Controller::class, 'shell']);
Route::get('/api/agenda/v3/config',               [AgendaV3Controller::class, 'config']);
Route::get('/api/agenda/v3/citas',                [AgendaV3Controller::class, 'listCitas']);
Route::post('/api/agenda/v3/citas',               [AgendaV3Controller::class, 'createCita']);
Route::put('/api/agenda/v3/citas/{id}',           [AgendaV3Controller::class, 'updateCita'])->whereNumber('id');
Route::post('/api/agenda/v3/citas/{id}/avanzar',  [AgendaV3Controller::class, 'avanzarCita'])->whereNumber('id');
Route::post('/api/agenda/v3/citas/{id}/consulta', [AgendaV3Controller::class, 'finalizarConsulta'])->whereNumber('id');
Route::delete('/api/agenda/v3/citas/{id}',        [AgendaV3Controller::class, 'cancelarCita'])->whereNumber('id');
Route::get('/api/agenda/v3/bloqueos',             [AgendaV3Controller::class, 'listBloqueos']);
Route::post('/api/agenda/v3/bloqueos',            [AgendaV3Controller::class, 'createBloqueo']);
Route::delete('/api/agenda/v3/bloqueos/{id}',     [AgendaV3Controller::class, 'deleteBloqueo'])->whereNumber('id');
Route::post('/api/agenda/v3/sync',                [AgendaV3Controller::class, 'forceSync']);
```

- [ ] **Step 3: Commit**

```bash
git add laravel-app/app/Modules/Agenda/Http/Controllers/AgendaV3Controller.php \
        laravel-app/routes/v2/agenda.php
git commit -m "feat(agenda-v3): endpoint POST /v3/sync para force re-sync de médicos"
```

---

## Task 5: Fix filtro de sede case-insensitive en `fetchPPCitas()`

**Files:**
- Modify: `laravel-app/app/Modules/Agenda/Http/Controllers/AgendaV3Controller.php` (método `fetchPPCitas`, dentro del `array_map`)

El problema: `procedimiento_proyectado.sede_departamento` puede tener "CEIBOS", "ceibos", "Ceibos", etc. El mapa actual solo indexa con `mb_strtolower()`, pero si la query SQL tiene un filtro exacto, las citas de otras sedes no llegan. Se agregan entradas UPPER al mapa y se normaliza la búsqueda.

- [ ] **Step 1: Ampliar `sedesMap` con variantes de capitalización**

Dentro de `fetchPPCitas()`, reemplazar el bloque de construcción del `$sedesMap`:

```php
// Antes:
$sedesMap = [];
DB::table('agenda_sedes')->get(['id', 'label'])->each(function ($s) use (&$sedesMap) {
    $sedesMap[mb_strtolower(trim($s->label), 'UTF-8')] = $s->id;
});
```

Por:

```php
$sedesMap = [];
DB::table('agenda_sedes')->get(['id', 'label'])->each(function ($s) use (&$sedesMap) {
    $lower = mb_strtolower(trim($s->label), 'UTF-8');
    $upper = mb_strtoupper(trim($s->label), 'UTF-8');
    $sedesMap[$lower]  = $s->id;
    $sedesMap[$upper]  = $s->id;
    // Variante abreviada: "Villa Club" → "villaclub", "VILLACLUB"
    $slug = preg_replace('/\s+/', '', $lower);
    $sedesMap[$slug]   = $s->id;
});
```

- [ ] **Step 2: Cambiar el filtro SQL de sede a case-insensitive**

Dentro de `fetchPPCitas()`, reemplazar:

```php
if ($sedeId !== '') {
    // Find the label for this sede to match against sede_departamento
    $sedeLabel = DB::table('agenda_sedes')->where('id', $sedeId)->value('label');
    if ($sedeLabel) {
        $sql  .= " AND TRIM(pp.sede_departamento) = ?";
        $bind[] = $sedeLabel;
    }
}
```

Por:

```php
if ($sedeId !== '') {
    $sedeLabel = DB::table('agenda_sedes')->where('id', $sedeId)->value('label');
    if ($sedeLabel) {
        $sql  .= " AND UPPER(TRIM(pp.sede_departamento)) LIKE UPPER(?)";
        $bind[] = '%' . trim((string) $sedeLabel) . '%';
    }
}
```

- [ ] **Step 3: En el `array_map`, normalizar lookup con UPPER también**

Dentro del `array_map` de `fetchPPCitas()`, reemplazar:

```php
$sedeRaw  = mb_strtolower(trim((string) ($pp->sede_raw ?? '')), 'UTF-8');
$sedeSlug = $sedesMap[$sedeRaw] ?? array_values($sedesMap)[0] ?? 'ceibos';
```

Por:

```php
$sedeRawOrig  = trim((string) ($pp->sede_raw ?? ''));
$sedeRawLower = mb_strtolower($sedeRawOrig, 'UTF-8');
$sedeRawUpper = mb_strtoupper($sedeRawOrig, 'UTF-8');
$sedeRawSlug  = preg_replace('/\s+/', '', $sedeRawLower) ?? $sedeRawLower;
$sedeSlug = $sedesMap[$sedeRawLower]
         ?? $sedesMap[$sedeRawUpper]
         ?? $sedesMap[$sedeRawSlug]
         ?? array_values($sedesMap)[0]
         ?? 'ceibos';
```

- [ ] **Step 4: Commit**

```bash
git add laravel-app/app/Modules/Agenda/Http/Controllers/AgendaV3Controller.php
git commit -m "fix(agenda-v3): filtro sede case-insensitive en fetchPPCitas"
```

---

## Task 6: Push y activación en staging

- [ ] **Step 1: Push del branch**

```bash
git push -u origin claude/zen-hamilton-IyzzU
```

- [ ] **Step 2: Correr el seeder en staging para limpiar médicos falsos**

(Si no se hizo en Task 2 ya)

```bash
/usr/bin/php8.3-cli artisan db:seed --class=AgendaV3Seeder
```

- [ ] **Step 3: Limpiar cache de archivos para que syncMedicosFromPP() corra en el próximo request**

```bash
/usr/bin/php8.3-cli artisan cache:clear
```

- [ ] **Step 4: Verificar que el endpoint de sync funciona**

Desde el navegador autenticado o con curl (reemplazar `<cookie>` y `<csrf>` con valores reales de sesión):

```bash
curl -X POST https://s908326448.onlinehome.us/v2/api/agenda/v3/sync \
  -H "X-CSRF-TOKEN: <token>" \
  -H "Cookie: <session_cookie>" \
  -H "Accept: application/json"
```

Expected response:
```json
{"ok": true, "medicos": 8, "mensaje": "Sync completado: 8 médicos activos."}
```

El número exacto depende de cuántos médicos distintos hay en `procedimiento_proyectado`.

- [ ] **Step 5: Verificar en el frontend**

Abrir `https://s908326448.onlinehome.us/v2/agenda/v3` en el navegador.

Expected:
- El dropdown de médicos muestra nombres reales del PP (no "Dr. Andrés Vargas")
- Las citas del día aparecen en el calendario y FlowBoard
- El filtro por sede "Ceibos" muestra citas de `procedimiento_proyectado` con `sede_departamento` = "CEIBOS" o variantes

---

## Criterios de éxito

- [ ] No aparece "Dr. Andrés Vargas" ni ningún médico del seeder en la UI
- [ ] Los médicos listados coinciden con los que tienen citas en `procedimiento_proyectado`
- [ ] Las citas del día actual aparecen en el calendario/FlowBoard
- [ ] `POST /v2/api/agenda/v3/sync` retorna `{"ok": true, "medicos": N}`
- [ ] Filtro por sede funciona con "CEIBOS", "Ceibos" y "ceibos"
