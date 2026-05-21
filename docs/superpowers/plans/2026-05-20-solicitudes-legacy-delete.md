# Solicitudes Legacy Delete Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminar `modules/solicitudes/` y `api/solicitudes/` del sistema legacy para desbloquear la eliminación futura de `modules/WhatsApp/` y cerrar el ~20% pendiente del módulo Solicitudes.

**Architecture:** El módulo ya tiene todas sus rutas como redirects a `/v2/`. El bloqueador son 4 dependencias de clases legacy desde otros módulos aún activos: `SolicitudModel` (usado por Examenes, Pacientes, controllers raíz) y `SolicitudCrmService` (usado por CronManager y Mail). Se eliminan las dependencias moviendo el modelo al directorio raíz y reemplazando los usos de CrmService con llamadas directas a PDO. Adicionalmente se crea el endpoint Laravel para crear solicitudes (usado por la extensión Chrome).

**Tech Stack:** PHP 8.2, Laravel 12, MySQL/PDO, Chrome Extension (cive_extention)

---

## Archivos a tocar

| Acción | Archivo |
|--------|---------|
| Crear | `laravel-app/app/Modules/Solicitudes/Services/SolicitudesCreateService.php` |
| Modificar | `laravel-app/app/Modules/Solicitudes/Http/Controllers/SolicitudesWriteController.php` |
| Modificar | `laravel-app/routes/v2/solicitudes.php` |
| Modificar | `public/index.php` (agregar `/solicitudes/guardar` a bridge prefixes) |
| Reemplazar | `models/SolicitudModel.php` (proxy → contenido real) |
| Modificar | `modules/CronManager/Services/CronRunner.php` (retirar runSolicitudesCrmSyncTask) |
| Modificar | `modules/Mail/Controllers/MailboxController.php` (inline PDO) |
| Eliminar | `modules/solicitudes/` (directorio completo) |
| Eliminar | `api/solicitudes/` (directorio completo) |

---

## Task 1: Crear endpoint Laravel para crear solicitudes

El archivo `api/solicitudes/guardar.php` y la extensión Chrome en `cive_extention/js/solicitud.js:58` llaman a `/solicitudes/guardar.php`. Laravel no tiene este endpoint aún.

**Files:**
- Create: `laravel-app/app/Modules/Solicitudes/Services/SolicitudesCreateService.php`
- Modify: `laravel-app/app/Modules/Solicitudes/Http/Controllers/SolicitudesWriteController.php`
- Modify: `laravel-app/routes/v2/solicitudes.php`
- Modify: `public/index.php`

- [ ] **Step 1.1: Crear SolicitudesCreateService**

```php
<?php

namespace App\Modules\Solicitudes\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SolicitudesCreateService
{
    /**
     * Crea una o más solicitudes de procedimiento quirúrgico.
     *
     * @param array{
     *     hcNumber: string,
     *     form_id: string,
     *     solicitudes: array<int, array<string,mixed>>
     * } $data
     * @return array{success: bool, message: string, ids?: list<int>}
     */
    public function guardar(array $data): array
    {
        if (empty($data['hcNumber']) || empty($data['form_id']) || !isset($data['solicitudes']) || !is_array($data['solicitudes'])) {
            return ['success' => false, 'message' => 'Datos no válidos o incompletos'];
        }

        $hcNumber  = trim((string) $data['hcNumber']);
        $formId    = trim((string) $data['form_id']);
        $solicitudes = $data['solicitudes'];

        if ($solicitudes === []) {
            return ['success' => false, 'message' => 'No se recibieron solicitudes para guardar'];
        }

        $ids = [];

        try {
            DB::transaction(function () use ($hcNumber, $formId, $solicitudes, &$ids): void {
                foreach ($solicitudes as $sol) {
                    $procedimiento = $this->clean($sol['procedimiento'] ?? null);
                    $doctor        = $this->clean($sol['doctor'] ?? null);
                    $ojo           = $this->clean($sol['ojo'] ?? null);
                    $prioridad     = $this->normPrioridad($sol['prioridad'] ?? null);
                    $afiliacion    = $this->clean($sol['afiliacion'] ?? null);
                    $afiliacionCat = $this->clean($sol['afiliacion_categoria'] ?? null);
                    $empresaSeg    = $this->clean($sol['empresa_seguro'] ?? null);
                    $sede          = $this->clean($sol['sede'] ?? null);
                    $observacion   = $this->clean($sol['observacion'] ?? null);
                    $fecha         = $this->normFecha($sol['fecha'] ?? null);
                    $codeId        = isset($sol['code_id']) && is_numeric($sol['code_id']) ? (int) $sol['code_id'] : null;

                    $id = DB::table('solicitud_procedimiento')->insertGetId([
                        'hc_number'           => $hcNumber,
                        'form_id'             => $formId,
                        'procedimiento'       => $procedimiento,
                        'doctor'              => $doctor,
                        'ojo'                 => $ojo,
                        'prioridad'           => $prioridad,
                        'afiliacion'          => $afiliacion,
                        'afiliacion_categoria'=> $afiliacionCat,
                        'empresa_seguro'      => $empresaSeg,
                        'sede'                => $sede,
                        'observacion'         => $observacion,
                        'fecha'               => $fecha,
                        'code_id'             => $codeId,
                        'estado'              => 'recibida',
                        'created_at'          => now()->toDateTimeString(),
                        'updated_at'          => now()->toDateTimeString(),
                    ]);

                    if ($id > 0) {
                        $ids[] = $id;
                    }
                }
            });
        } catch (\Throwable $e) {
            Log::error('SolicitudesCreateService::guardar error', ['message' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Error al guardar la solicitud: ' . $e->getMessage()];
        }

        return [
            'success' => true,
            'message' => count($ids) . ' solicitud(es) guardada(s) exitosamente',
            'ids'     => $ids,
        ];
    }

    private function clean(mixed $v): ?string
    {
        if (!is_string($v)) {
            return null;
        }
        $v = trim($v);
        if ($v === '' || in_array(mb_strtoupper($v), ['SELECCIONE', 'NINGUNO'], true)) {
            return null;
        }
        return $v;
    }

    private function normPrioridad(mixed $v): string
    {
        $v = is_string($v) ? mb_strtoupper(trim($v)) : $v;
        return ($v === 'SI' || $v === 1 || $v === '1' || $v === true) ? 'SI' : 'NO';
    }

    private function normFecha(mixed $v): ?string
    {
        $v = is_string($v) ? trim($v) : null;
        if (!$v) {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $v)) {
            return $v;
        }
        foreach (['d/m/Y H:i', 'd-m-Y H:i', 'd/m/Y', 'd-m-Y', 'm/d/Y H:i', 'm-d-Y H:i'] as $fmt) {
            $dt = \DateTime::createFromFormat($fmt, $v);
            if ($dt instanceof \DateTime) {
                return $dt->format(strlen($fmt) >= 10 ? 'Y-m-d H:i:s' : 'Y-m-d');
            }
        }
        return null;
    }
}
```

- [ ] **Step 1.2: Agregar método `guardarSolicitud` a SolicitudesWriteController**

Abrir `laravel-app/app/Modules/Solicitudes/Http/Controllers/SolicitudesWriteController.php`.
Agregar al principio del archivo la importación:
```php
use App\Modules\Solicitudes\Services\SolicitudesCreateService;
```

Agregar el método al final de la clase (antes del cierre `}`):
```php
public function guardarSolicitud(Request $request): JsonResponse
{
    $data = $request->all();
    $result = (new SolicitudesCreateService())->guardar($data);
    $status = ($result['success'] ?? false) ? 200 : 422;
    return new JsonResponse($result, $status);
}
```

Verificar que `JsonResponse` y `Request` están importados en el controlador.

- [ ] **Step 1.3: Agregar rutas en `routes/v2/solicitudes.php`**

En el bloque `Route::middleware('legacy.alias:php')->group(function (): void {` agregar al final:
```php
Route::post('/api/solicitudes/guardar.php', [SolicitudesWriteController::class, 'guardarSolicitud']);
```

En el bloque `Route::middleware('legacy.alias:clean')->group(function (): void {` agregar al final:
```php
Route::post('/api/solicitudes/guardar', [SolicitudesWriteController::class, 'guardarSolicitud']);
Route::post('/solicitudes/guardar', [SolicitudesWriteController::class, 'guardarSolicitud']);
```

- [ ] **Step 1.4: Agregar `/solicitudes/guardar` al bridge en `public/index.php`**

Buscar la línea:
```php
$laravelBridgeExact = ['/auth/login', '/auth/logout', '/whatsapp/webhook'];
```

Agregar la ruta al array exact (POST a guardar.php viene como path literal):
```php
$laravelBridgeExact = ['/auth/login', '/auth/logout', '/whatsapp/webhook', '/solicitudes/guardar.php', '/api/solicitudes/guardar.php'];
```

- [ ] **Step 1.5: Verificar sintaxis**

```bash
cd /Users/jorgeluisdevera/PhpstormProjects/MedForge
php -l laravel-app/app/Modules/Solicitudes/Services/SolicitudesCreateService.php
php -l laravel-app/app/Modules/Solicitudes/Http/Controllers/SolicitudesWriteController.php
php -l public/index.php
```

Esperado: `No syntax errors detected` en los 3 archivos.

- [ ] **Step 1.6: Commit**

```bash
git -C /Users/jorgeluisdevera/PhpstormProjects/MedForge add \
  laravel-app/app/Modules/Solicitudes/Services/SolicitudesCreateService.php \
  laravel-app/app/Modules/Solicitudes/Http/Controllers/SolicitudesWriteController.php \
  laravel-app/routes/v2/solicitudes.php \
  public/index.php
git -C /Users/jorgeluisdevera/PhpstormProjects/MedForge commit -m "feat(solicitudes): add Laravel create-solicitud endpoint for Chrome extension compatibility"
```

---

## Task 2: Mover SolicitudModel a models/ raíz (eliminar dependencia de modules/solicitudes/)

`models/SolicitudModel.php` actualmente es un proxy que require `modules/solicitudes/models/SolicitudModel.php` si existe. Cuando eliminemos el módulo, el proxy quedaría con la clase fallback (incompleta). La solución es reemplazar el proxy con el contenido real del modelo.

**Files:**
- Modify/Replace: `models/SolicitudModel.php`

- [ ] **Step 2.1: Leer contenido actual del modelo modular**

```bash
wc -l /Users/jorgeluisdevera/PhpstormProjects/MedForge/modules/solicitudes/models/SolicitudModel.php
head -5 /Users/jorgeluisdevera/PhpstormProjects/MedForge/modules/solicitudes/models/SolicitudModel.php
```

Confirmar que está en `namespace Models;` y extiende ninguna clase.

- [ ] **Step 2.2: Reemplazar models/SolicitudModel.php con el contenido real**

```bash
# Copiar el modelo modular completo al root models/
cp /Users/jorgeluisdevera/PhpstormProjects/MedForge/modules/solicitudes/models/SolicitudModel.php \
   /Users/jorgeluisdevera/PhpstormProjects/MedForge/models/SolicitudModel.php
```

- [ ] **Step 2.3: Verificar que el archivo copiado arranca con `<?php` y `namespace Models;`**

```bash
head -10 /Users/jorgeluisdevera/PhpstormProjects/MedForge/models/SolicitudModel.php
```

Esperado: primera línea `<?php`, namespace `Models`, class `SolicitudModel`.

- [ ] **Step 2.4: Verificar sintaxis del modelo copiado**

```bash
php -l /Users/jorgeluisdevera/PhpstormProjects/MedForge/models/SolicitudModel.php
```

Esperado: `No syntax errors detected`.

- [ ] **Step 2.5: Verificar que los consumidores del modelo siguen compilando**

```bash
php -l /Users/jorgeluisdevera/PhpstormProjects/MedForge/modules/examenes/models/ExamenesModel.php
php -l /Users/jorgeluisdevera/PhpstormProjects/MedForge/modules/Pacientes/Services/Paciente360Service.php
php -l /Users/jorgeluisdevera/PhpstormProjects/MedForge/controllers/SolicitudController.php
```

Esperado: `No syntax errors detected` en los 3 archivos.

- [ ] **Step 2.6: Commit**

```bash
git -C /Users/jorgeluisdevera/PhpstormProjects/MedForge add models/SolicitudModel.php
git -C /Users/jorgeluisdevera/PhpstormProjects/MedForge commit -m "refactor: promote SolicitudModel from modules/solicitudes to root models/"
```

---

## Task 3: Retirar SolicitudCrmService de CronRunner

`CronRunner::runSolicitudesCrmSyncTask()` usa `SolicitudCrmService->bootstrapChecklist()`. Esta funcionalidad ya está cubierta por el comando Artisan `solicitudes:crm-sync` (agregado en Phase D del proyecto). El task del cron puede retirarse de forma segura.

**Files:**
- Modify: `modules/CronManager/Services/CronRunner.php`

- [ ] **Step 3.1: Localizar la importación y método en CronRunner.php**

```bash
grep -n "SolicitudCrmService\|runSolicitudesCrmSyncTask\|ensureSolicitudModuleLoaded" \
  /Users/jorgeluisdevera/PhpstormProjects/MedForge/modules/CronManager/Services/CronRunner.php | head -20
```

Anotar los números de línea de:
- El `use Modules\Solicitudes\Services\SolicitudCrmService;`
- El método `runSolicitudesCrmSyncTask()`
- `ensureSolicitudModuleLoaded()` (si existe)

- [ ] **Step 3.2: Eliminar la importación de SolicitudCrmService**

En `modules/CronManager/Services/CronRunner.php`, eliminar la línea:
```php
use Modules\Solicitudes\Services\SolicitudCrmService;
```

- [ ] **Step 3.3: Reemplazar el cuerpo de `runSolicitudesCrmSyncTask()`**

Localizar el método completo `private function runSolicitudesCrmSyncTask(): array { ... }` y reemplazar su cuerpo completo con:
```php
    /**
     * @return array{status?:string,message?:string,details?:array}
     */
    private function runSolicitudesCrmSyncTask(): array
    {
        // Retirado: esta tarea es ahora ejecutada por el comando Artisan
        // `solicitudes:crm-sync` registrado en el scheduler de Laravel.
        // Ver routes/console.php: Schedule::command('solicitudes:crm-sync ...')->hourly()
        return [
            'status'  => 'retired',
            'message' => 'Tarea migrada a Artisan solicitudes:crm-sync (Laravel scheduler).',
        ];
    }
```

- [ ] **Step 3.4: Eliminar `ensureSolicitudModuleLoaded()` si solo era usado por este task**

```bash
grep -n "ensureSolicitudModuleLoaded" \
  /Users/jorgeluisdevera/PhpstormProjects/MedForge/modules/CronManager/Services/CronRunner.php
```

Si SOLO aparece en `runSolicitudesCrmSyncTask()` (ya reemplazado), eliminar también el método `ensureSolicitudModuleLoaded()`.

- [ ] **Step 3.5: Verificar sintaxis**

```bash
php -l /Users/jorgeluisdevera/PhpstormProjects/MedForge/modules/CronManager/Services/CronRunner.php
```

Esperado: `No syntax errors detected`.

- [ ] **Step 3.6: Verificar que no quedan referencias a SolicitudCrmService en CronRunner**

```bash
grep "SolicitudCrmService\|ensureSolicitudModule" \
  /Users/jorgeluisdevera/PhpstormProjects/MedForge/modules/CronManager/Services/CronRunner.php
```

Esperado: sin output.

- [ ] **Step 3.7: Commit**

```bash
git -C /Users/jorgeluisdevera/PhpstormProjects/MedForge add \
  modules/CronManager/Services/CronRunner.php
git -C /Users/jorgeluisdevera/PhpstormProjects/MedForge commit -m "refactor(cronmanager): retire SolicitudCrmService dependency — task handled by Artisan scheduler"
```

---

## Task 4: Reemplazar SolicitudCrmService en MailboxController

`MailboxController` usa `SolicitudCrmService` para dos operaciones:
1. `registrarNota(int $solicitudId, string $nota, ?int $autorId)` → INSERT en `solicitud_crm_notas`
2. `obtenerContactoPaciente(int $solicitudId)` → SELECT con JOIN para obtener nombre/email del paciente

Ambas se pueden reemplazar con PDO directo ya que el controlador ya tiene `$this->pdo`.

**Files:**
- Modify: `modules/Mail/Controllers/MailboxController.php`

- [ ] **Step 4.1: Leer el MailboxController completo para entender el contexto**

```bash
grep -n "SolicitudCrmService\|solicitudCrm\|registrarNota\|obtenerContactoPaciente\|class MailboxController\|private.*pdo\|protected.*pdo\|\$this->pdo" \
  /Users/jorgeluisdevera/PhpstormProjects/MedForge/modules/Mail/Controllers/MailboxController.php | head -30
```

- [ ] **Step 4.2: Eliminar la importación y propiedad de SolicitudCrmService**

En `modules/Mail/Controllers/MailboxController.php`:

Eliminar:
```php
use Modules\Solicitudes\Services\SolicitudCrmService;
```

Eliminar la propiedad (ejemplo de cómo se declara):
```php
private SolicitudCrmService $solicitudCrm;
```

Eliminar la inicialización en el constructor:
```php
$this->solicitudCrm = new SolicitudCrmService($pdo);
```

- [ ] **Step 4.3: Reemplazar llamada a `registrarNota()`**

Buscar la línea:
```php
$this->solicitudCrm->registrarNota($targetId, $message, $this->getCurrentUserId());
```

Reemplazar con:
```php
$notaTexto = trim(strip_tags((string) $message));
if ($notaTexto !== '') {
    $stmtNota = $this->pdo->prepare(
        'INSERT INTO solicitud_crm_notas (solicitud_id, autor_id, nota, created_at) VALUES (?, ?, ?, NOW())'
    );
    $stmtNota->execute([$targetId, $this->getCurrentUserId(), $notaTexto]);
}
```

- [ ] **Step 4.4: Reemplazar llamada a `obtenerContactoPaciente()`**

Buscar la línea:
```php
$emailContext = $this->solicitudCrm->obtenerContactoPaciente($targetId);
```

Reemplazar con:
```php
$emailContext = null;
$stmtCtx = $this->pdo->prepare(
    "SELECT CONCAT(TRIM(pd.fname), ' ', TRIM(pd.lname)) AS name,
            scd.contacto_email AS email,
            sp.hc_number,
            sp.procedimiento AS descripcion
     FROM solicitud_procedimiento sp
     LEFT JOIN patient_data pd ON pd.hc_number = sp.hc_number
     LEFT JOIN solicitud_crm_detalles scd ON scd.solicitud_id = sp.id
     WHERE sp.id = ?
     LIMIT 1"
);
$stmtCtx->execute([$targetId]);
$ctxRow = $stmtCtx->fetch(\PDO::FETCH_ASSOC);
if ($ctxRow !== false && $ctxRow !== null) {
    $emailContext = array_filter($ctxRow, static fn($v) => $v !== null && $v !== '');
    if ($emailContext === []) {
        $emailContext = null;
    }
}
```

- [ ] **Step 4.5: Verificar que no hay más referencias a solicitudCrm en el controlador**

```bash
grep -n "solicitudCrm\|SolicitudCrmService" \
  /Users/jorgeluisdevera/PhpstormProjects/MedForge/modules/Mail/Controllers/MailboxController.php
```

Esperado: sin output.

- [ ] **Step 4.6: Verificar sintaxis**

```bash
php -l /Users/jorgeluisdevera/PhpstormProjects/MedForge/modules/Mail/Controllers/MailboxController.php
```

Esperado: `No syntax errors detected`.

- [ ] **Step 4.7: Commit**

```bash
git -C /Users/jorgeluisdevera/PhpstormProjects/MedForge add \
  modules/Mail/Controllers/MailboxController.php
git -C /Users/jorgeluisdevera/PhpstormProjects/MedForge commit -m "refactor(mail): inline PDO queries to remove SolicitudCrmService dependency"
```

---

## Task 5: Verificación final y eliminación de modules/solicitudes/ + api/solicitudes/

- [ ] **Step 5.1: Verificar que no hay más referencias a clases de modules/solicitudes/**

```bash
grep -rn "Modules\\\\[Ss]olicitudes\\\\\|modules/solicitudes\|SolicitudCrmService\|SolicitudEstadoService\|SolicitudKpiService\|SolicitudReminderService\|SolicitudSettingsService\|SolicitudesDashboardService\|CalendarBlockService\|CoberturaMailLogService\|SolicitudHelper" \
  /Users/jorgeluisdevera/PhpstormProjects/MedForge/modules/ \
  /Users/jorgeluisdevera/PhpstormProjects/MedForge/controllers/ \
  /Users/jorgeluisdevera/PhpstormProjects/MedForge/models/ \
  /Users/jorgeluisdevera/PhpstormProjects/MedForge/helpers/ \
  /Users/jorgeluisdevera/PhpstormProjects/MedForge/api/ \
  --include="*.php" 2>/dev/null \
  | grep -v "/modules/solicitudes/"
```

Esperado: **sin output** (cero resultados). Si hay resultados, resolver antes de continuar.

- [ ] **Step 5.2: Verificar que models/SolicitudModel.php NO requiere el módulo legacy**

```bash
grep "modules/solicitudes\|require_once\|ModuleSolicitudModel" \
  /Users/jorgeluisdevera/PhpstormProjects/MedForge/models/SolicitudModel.php
```

Esperado: sin output (el proxy fue reemplazado por el contenido real en Task 2).

- [ ] **Step 5.3: Eliminar modules/solicitudes/**

```bash
rm -rf /Users/jorgeluisdevera/PhpstormProjects/MedForge/modules/solicitudes
echo "Deleted: $?"
ls /Users/jorgeluisdevera/PhpstormProjects/MedForge/modules/ | grep -i solicitud
```

Esperado: `Deleted: 0`, segunda línea sin output.

- [ ] **Step 5.4: Eliminar api/solicitudes/**

```bash
rm -rf /Users/jorgeluisdevera/PhpstormProjects/MedForge/api/solicitudes
echo "Deleted: $?"
ls /Users/jorgeluisdevera/PhpstormProjects/MedForge/api/
```

Esperado: `Deleted: 0`, directorio `solicitudes` no aparece.

- [ ] **Step 5.5: Verificar que consumidores del modelo siguen compilando post-eliminación**

```bash
php -l /Users/jorgeluisdevera/PhpstormProjects/MedForge/models/SolicitudModel.php
php -l /Users/jorgeluisdevera/PhpstormProjects/MedForge/modules/examenes/models/ExamenesModel.php
php -l /Users/jorgeluisdevera/PhpstormProjects/MedForge/modules/Pacientes/Services/Paciente360Service.php
php -l /Users/jorgeluisdevera/PhpstormProjects/MedForge/modules/CronManager/Services/CronRunner.php
php -l /Users/jorgeluisdevera/PhpstormProjects/MedForge/modules/Mail/Controllers/MailboxController.php
```

Esperado: `No syntax errors detected` en todos.

- [ ] **Step 5.6: Verificar que el Laravel side compila**

```bash
cd /Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app && php artisan list solicitudes 2>&1 | head -20
```

Esperado: lista de comandos `solicitudes:*` sin errores PHP.

- [ ] **Step 5.7: Commit y push final**

```bash
git -C /Users/jorgeluisdevera/PhpstormProjects/MedForge add -u \
  modules/solicitudes api/solicitudes
git -C /Users/jorgeluisdevera/PhpstormProjects/MedForge commit -m "chore: delete modules/solicitudes and api/solicitudes — legacy fully retired

All routes already proxied to /v2/. SolicitudModel moved to models/ root.
SolicitudCrmService dependencies replaced in CronManager and Mail.
Creates endpoint is now in Laravel SolicitudesCreateService.
Removes ~36 legacy PHP files.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>"

git -C /Users/jorgeluisdevera/PhpstormProjects/MedForge push origin main
```

---

## Self-Review

### Spec coverage:
- ✅ Chrome extension endpoint: Task 1 crea `/solicitudes/guardar.php` en Laravel + bridge en `public/index.php`
- ✅ SolicitudModel: Task 2 mueve el modelo de 2669 líneas al directorio raíz
- ✅ CronRunner: Task 3 retira el task sin romper funcionalidad (cubierto por Artisan)
- ✅ MailboxController: Task 4 reemplaza las 2 llamadas con PDO directo
- ✅ Eliminación: Task 5 verifica y elimina con confirmación explícita de cero referencias

### Bloqueadores conocidos que NO cubre este plan:
- `modules/WhatsApp/` NO se puede eliminar aún (Examenes y solicitudes legacy usaban su Messenger, pero con la eliminación de `modules/solicitudes/`, el bloqueo de WhatsApp pasa a depender solo de si `modules/examenes/services/ExamenCrmService.php` aún existe)
- `modules/Usuarios/` bloqueado por `modules/Autoresponder/` → no parte de este plan

### Resultado esperado tras completar:
- `modules/solicitudes/` → eliminado
- `api/solicitudes/` → eliminado
- `modules/` pasa de 29 a 28 directorios
- Se desbloquea la evaluación de si `modules/WhatsApp/` puede eliminarse (ya que Examenes quedaría como único consumidor)
