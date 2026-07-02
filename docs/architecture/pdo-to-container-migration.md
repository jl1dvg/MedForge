# Migración de PDO manual al Service Container de Laravel

**Estado:** Fase 1 completada (auditoría + propuesta + piloto). Migración de módulos: pendiente.
**Alcance de esta fase:** Arquitectura únicamente. Cero cambios de SQL, cero cambios de comportamiento, cero funcionalidad nueva.

---

## 0. Resumen ejecutivo

MedForge corre sobre Laravel, pero una parte grande del código heredado (30+ servicios) recibe la conexión a base de datos como un objeto `PDO` crudo, pasado a mano en cada `new Servicio($pdo)`. Esto bloquea el uso del Service Container: nada de esto es inyectable, mockeable, ni resoluble automáticamente por el framework.

Esta fase:
1. Audita el alcance real del problema (qué servicios, cuántos sitios de instanciación).
2. Propone un patrón único para todo MedForge.
3. Migra **un solo servicio piloto** (`CronTaskRepository`) para validar el patrón sin tocar SQL ni comportamiento.
4. Deja un plan de migración incremental por módulo, ordenado por riesgo/dependencias.

No se tocó `CirugiasDashboardService` ni ningún otro servicio de negocio en esta fase — eso es la Fase 2+.

---

## 1. Auditoría

### 1.1 Servicios que reciben `PDO` en el constructor

Búsqueda: `grep -rn "__construct(.*PDO " app/Modules`

| Módulo | Servicios afectados | Cantidad |
|---|---|---|
| Billing | `BillingParticularesReportService`, `BillingSoamRuleAdapter`, `HonorariosDashboardDataService`, `BillingDashboardDataService`, `BillingLeakageService`, `BillingPreviewService`, `BillingProcedimientosKpiService`, `BillingInformePacienteService`, `BillingSoamAdapter`, `NoFacturadosQueryService` | 10 |
| Examenes (incluye Imágenes) | `ConsultaExamenSyncService`, `ImagenesUiService`, `ExamenesParityService`, `ExamenesReportingService`, `ExamenesPrefacturaService` | 5 |
| Reporting | `ConsultaReportDataService`, `CoberturaReportDataService`, `PostSurgeryRestReportDataService`, `AbstractSolicitudReport` | 4 |
| Cirugías | `CirugiasDashboardService`, `CirugiaService`, `ProtocolosTemplateReadService`, `ProtocolosTemplateWriteService` | 4 |
| Mail | `MailboxService`, `MailProfileService`, `NotificationMailer` | 3 |
| CronManager | `CronRunner`, `CronTaskRepository` (✅ migrado en esta fase) | 2 |
| Solicitudes | `SolicitudesPrefacturaService` | 1 |
| Shared | `AfiliacionDimensionService` (usado como dependencia interna por casi todos los módulos de arriba) | 1 |
| IdentityVerification | `VerificationModel` | 1 |
| Consultas | `ConsultasParityService` | 1 |

**Total: 32 servicios** con `PDO` en el constructor.

### 1.2 Sitios de instanciación manual (`new Servicio($pdo)` / `new Servicio(DB::connection()->getPdo())`)

Búsqueda combinada de `->getPdo()` (65 ocurrencias) y `new XService($pdo|$db|$this->pdo|$this->db)` (58 ocurrencias) en controladores y servicios.

Patrón típico encontrado en **todos** los controladores HTTP legacy:

```php
public function __construct()
{
    $pdo = DB::connection()->getPdo();
    $this->service = new AlgunServicio($pdo);
}
```

Y dentro de los propios servicios, instanciación en cadena de sub-servicios:

```php
$this->afiliacionDimensions = new AfiliacionDimensionService($this->db);
```

`AfiliacionDimensionService` es especialmente crítico: es instanciado manualmente **dentro** de al menos 8 servicios distintos (Cirugías, Billing ×5, Examenes/Imágenes, Farmacia). Cualquier plan de migración debe resolver esta dependencia transitiva primero o en paralelo — es la raíz de un árbol de instanciaciones manuales.

### 1.3 Módulos fuera del listado de constructor-PDO pero relevantes al pedido

| Módulo | Uso de PDO/getPdo() | Nota |
|---|---|---|
| WhatsApp | 0 archivos | Ya migrado — no usa PDO crudo en ningún servicio. Fuera del alcance de esta migración. |
| CRM | 2 archivos (`CrmCaseService`, `CrmProposalController`) | Bajo acoplamiento, candidato temprano. |
| Farmacia | 2 archivos (`FarmaciaDashboardService`, `RecetasConciliacionSyncService`) | Depende de `AfiliacionDimensionService`. |
| Imágenes (vive en módulo `Examenes`) | 8 archivos | Ver fila "Examenes" arriba — es el mismo módulo. |
| Facturación (`Billing`) | 10 servicios + 6 controladores | El módulo con más superficie. Requiere la migración más cuidadosa. |

---

## 2. Propuesta de patrón

### 2.1 Opciones evaluadas

**Opción A — Repository explícito por tabla/agregado**
```
Controller → Service → Repository → DB::select()
```
Introduce una capa de Repository dedicada por entidad (`ProtocoloRepository`, `SolicitudRepository`, etc.), con métodos de acceso a datos puros, y los Services contienen solo lógica de negocio.

**Opción B — Servicio recibe la Conexión de Laravel directamente**
```
Controller → Service → ConnectionInterface (resuelto por el container) → PDO interno cuando se necesita
```
Los servicios existentes mantienen su forma actual (una clase con SQL embebido), pero cambian el tipo del parámetro del constructor de `PDO` a `Illuminate\Database\ConnectionInterface`. Internamente, si el código necesita la API específica de PDO (`prepare()`, `bindValue()` con tipos explícitos, `lastInsertId()`, `PDO::FETCH_ASSOC`), se obtiene con `$connection->getPdo()` — una sola vez, dentro de la clase, nunca desde afuera.

### 2.2 Decisión: Opción B para esta fase, Opción A como visión a largo plazo

**Para reportes SQL complejos (el caso dominante en MedForge: `CirugiasDashboardService`, `BillingInformeDataService`, `ExamenesReportingService`, etc.) la Opción B es la correcta ahora:**

1. **Cero riesgo de reescritura de SQL.** El código ya usa `$pdo->prepare()/execute()/fetchAll(PDO::FETCH_ASSOC)` con SQL muy afinado (joins condicionales, CTEs, agregaciones). Forzar todo a través de `DB::select()` de Laravel cambia el tipo de retorno (stdClass en vez de array asociativo) y complica el binding de tipos explícitos (`PDO::PARAM_INT`, `PDO::PARAM_NULL`) que varios de estos reportes usan activamente (ver `CirugiasDashboardService::finishLog()`, por ejemplo). Migrar a Opción A de una sola vez implicaría tocar SQL — prohibido en esta fase.
2. **Resuelve el problema real: el Service Container.** El dolor actual no es "el SQL está en el Service" — es que nadie puede inyectar estos servicios, mockearlos en tests, ni dejar que Laravel resuelva sus dependencias. Cambiar `PDO $db` por `ConnectionInterface $connection` resuelve exactamente eso sin tocar una sola línea de SQL.
3. **Es reversible y gradual.** Cada servicio se migra de forma aislada (un archivo, sin efectos en cascada), y el comportamiento es idéntico porque `$connection->getPdo()` devuelve el mismo objeto PDO subyacente que hoy se obtiene manualmente vía `DB::connection()->getPdo()`. Mismo driver, misma conexión, mismo charset, mismas transacciones.
4. **Deja la puerta abierta a Opción A después.** Una vez que todos los servicios sean resueltos por el container, extraer un Repository de un Service concreto (para los casos que lo ameriten — reportes muy grandes como `CirugiasDashboardService`, con 2000+ líneas) es un refactor *interno* al servicio, no arquitectónico. Se puede hacer módulo por módulo, sin bloquear el resto.

**Regla del patrón, válida para todo MedForge a partir de ahora:**

```php
// ANTES (legacy, prohibido en código nuevo)
public function __construct(private PDO $db) {}

// AHORA (patrón MedForge)
public function __construct(private readonly \Illuminate\Database\ConnectionInterface $connection)
{
    // Solo si el código necesita la API cruda de PDO (fetchAll con modo específico,
    // bindValue con tipo explícito, lastInsertId, transacciones manuales):
    $this->pdo = $connection->getPdo();
}
```

- **No se instancia el servicio manualmente.** Se resuelve vía constructor injection en controladores (`public function __construct(private AlgunServicio $service) {}`) o vía `app(AlgunServicio::class)` en código que aún no puede recibir inyección (comandos legacy, `routes/console.php`).
- **`Illuminate\Database\ConnectionInterface` no requiere binding manual** — Laravel ya lo resuelve out-of-the-box contra la conexión default (`registerCoreContainerAliases()` en el framework la alias-ea a `db.connection`). Confirmado en el piloto de esta fase (ver §3).
- **El SQL no se toca.** Sigue siendo `$this->pdo->prepare(...)`, exactamente igual que hoy.
- Para servicios usados solo como dependencia interna de otros servicios (caso `AfiliacionDimensionService`), también se migran a recibir `ConnectionInterface` y se resuelven vía `app(AfiliacionDimensionService::class)` en vez de `new AfiliacionDimensionService($this->db)` — mismo patrón, sin excepciones.

---

## 3. Prueba piloto: `CronTaskRepository`

### Por qué este servicio

- Pequeño (150 líneas de lógica real), autocontenido, dos únicos consumidores (`CronManagerController`, `CronRunner`).
- Ya se llama "Repository" — encaja naturalmente como primer caso de prueba del patrón sin necesidad de crear una clase nueva.
- Usa la API completa de PDO que nos preocupa preservar: `prepare/execute/fetchAll(PDO::FETCH_ASSOC)`, `bindValue` con `PDO::PARAM_INT`/`PDO::PARAM_NULL` explícitos, `lastInsertId()`, `exec()` para DDL. Si el patrón funciona acá, funciona para el resto.

### Cambios realizados

**`app/Modules/CronManager/Repositories/CronTaskRepository.php`**
```php
// Antes
public function __construct(private PDO $pdo)
{
    $this->ensureSchema();
}

// Después
private PDO $pdo;

public function __construct(ConnectionInterface $connection)
{
    $this->pdo = $connection->getPdo();
    $this->ensureSchema();
}
```
Ningún otro método del archivo cambió — todo el SQL, todos los `prepare()/execute()/bindValue()` quedan idénticos.

**`app/Modules/CronManager/Http/Controllers/CronManagerController.php`**
```php
// Antes
public function __construct()
{
    $this->scheduleRepository = new CronScheduleRepository();
}
private function getRepository(): CronTaskRepository
{
    if ($this->repository === null) {
        $this->repository = new CronTaskRepository(DB::connection()->getPdo());
    }
    return $this->repository;
}

// Después
public function __construct(private readonly CronTaskRepository $repository)
{
    $this->scheduleRepository = new CronScheduleRepository();
}
private function getRepository(): CronTaskRepository
{
    return $this->repository;
}
```
El controlador se resuelve exclusivamente vía el router (`[CronManagerController::class, 'index']`), así que Laravel autowirea `CronTaskRepository` sin ningún binding adicional.

**`app/Modules/CronManager/Services/CronRunner.php`**

`CronRunner` sigue recibiendo `PDO $pdo` (no forma parte de este piloto — tiene ~15 usos internos de `$this->pdo` para otros servicios legacy, fuera de alcance). Solo se actualizó el único punto donde instanciaba `CronTaskRepository`:
```php
// Antes
$this->repository = new CronTaskRepository($pdo);

// Después
$this->repository = app(CronTaskRepository::class);
```

### Validación

- `php -l` limpio en los 3 archivos.
- `php artisan tinker --execute="app(CronTaskRepository::class)"` — el container resuelve `ConnectionInterface` automáticamente sin binding manual y llega hasta el intento real de conexión a MySQL (falla en este sandbox por no tener MySQL corriendo, lo cual confirma que la resolución de dependencias fue exitosa — el error es de infraestructura, no de wiring).
- Cero cambios de SQL, cero cambios de firma pública salvo el tipo del parámetro del constructor.

---

## 4. Plan de migración por módulo

Orden recomendado, de menor a mayor riesgo/superficie:

| Orden | Módulo | Servicios a migrar | Dificultad | Riesgo | Dependencias | Justificación del orden |
|---|---|---|---|---|---|---|
| 1 | **Shared** | `AfiliacionDimensionService` | Baja | Medio | Ninguna, pero es dependencia transitiva de ~8 servicios | Migrar primero porque desbloquea la migración limpia de todos los que lo instancian con `new AfiliacionDimensionService($db)`. Si se migra después, cada consumidor debe resolverlo dos veces (una vez como servicio propio, otra como dependencia interna). |
| 2 | **CRM** | `CrmCaseService` | Baja | Bajo | Ninguna | Módulo chico (2 archivos), buen segundo caso de prueba real (no piloto) para confirmar el patrón en un controlador+servicio de negocio, no solo un repository. |
| 3 | **Farmacia** | `FarmaciaDashboardService`, `RecetasConciliacionSyncService` | Baja-Media | Bajo | `AfiliacionDimensionService` (paso 1) | Módulo autocontenido, bajo tráfico, buen lugar para validar el patrón en un dashboard antes de tocar los dashboards de alto tráfico (Cirugías/Billing). |
| 4 | **Consultas** | `ConsultasParityService` | Media | Medio | `ConsultaExamenSyncService` (Examenes) | Instancia servicios de Examenes internamente — coordinar con el paso 5. |
| 5 | **Cirugías** | `CirugiaService`, `ProtocolosTemplateReadService`, `ProtocolosTemplateWriteService`, `CirugiasDashboardService` (último) | Media-Alta | Medio-Alto | `AfiliacionDimensionService` | Migrar primero los 3 servicios chicos y dejar `CirugiasDashboardService` (2000+ líneas, el más tocado en las últimas semanas por el fix de performance) para el final del módulo, cuando el patrón ya esté probado 4 veces. |
| 6 | **Examenes / Imágenes** | `ConsultaExamenSyncService`, `ImagenesUiService`, `ExamenesParityService`, `ExamenesReportingService`, `ExamenesPrefacturaService` | Alta | Medio-Alto | `AfiliacionDimensionService`, `Mail` (envían correos) | Mayor cantidad de sub-servicios instanciados en cadena (`LeadConfigurationService`, `PusherConfigService`, `ExamenCrmService`, `ExamenMailLogService` — todos con `new X($this->db)` internamente). Requiere migrar la cadena completa en el mismo PR para no dejar wiring mixto. |
| 7 | **Mail** | `MailboxService`, `MailProfileService`, `NotificationMailer` | Media | Alto | Ninguna propia, pero es consumida por Examenes, CRM, Solicitudes | Se migra antes de Billing/Facturación porque varios flujos de facturación envían notificaciones por mail — mejor que Mail ya esté resuelto por el container cuando se migre Billing. |
| 8 | **Reporting** | `ConsultaReportDataService`, `CoberturaReportDataService`, `PostSurgeryRestReportDataService`, `AbstractSolicitudReport` | Media | Medio | Ninguna directa | Generadores de reportes PDF/export, bajo tráfico interactivo (se ejecutan on-demand, no en cada carga de dashboard) — riesgo controlado si algo sale mal. |
| 9 | **Solicitudes** | `SolicitudesPrefacturaService` | Media | Medio | `Mail`, `Codes` (`CodesPackageService`) | Coordinar con Mail (paso 7) ya migrado. |
| 10 | **Billing / Facturación** | `BillingParticularesReportService`, `BillingSoamRuleAdapter`, `HonorariosDashboardDataService`, `BillingDashboardDataService`, `BillingLeakageService`, `BillingPreviewService`, `BillingProcedimientosKpiService`, `BillingInformePacienteService`, `BillingSoamAdapter`, `NoFacturadosQueryService` | Alta | **Alto** | `AfiliacionDimensionService`, `Mail` | El módulo con más servicios (10) y más dinero en juego (facturación real). Se deja al final porque para entonces el patrón ya se validó en 9 módulos distintos. Requiere el mayor cuidado en QA manual antes de mergear (afecta facturación real, cartera, pagos IESS). |
| 11 | **IdentityVerification** | `VerificationModel` | Baja | Bajo | Ninguna | Aislado, se puede intercalar en cualquier punto — bajo impacto si algo falla. |

**WhatsApp no aparece en la tabla**: ya no usa PDO crudo en ningún servicio (confirmado en la auditoría, §1.3) — queda fuera del alcance de esta migración.

### Reglas de ejecución para cada módulo

1. Un PR por módulo (o por servicio, si el módulo es grande como Billing/Examenes) — nunca mezclar la migración de arquitectura con cambios funcionales.
2. Cada PR: cambiar `PDO $db` → `ConnectionInterface $connection` + `$this->pdo = $connection->getPdo()`, actualizar los sitios de instanciación manual a constructor injection o `app(Servicio::class)`, `php -l` en todos los archivos tocados, y verificación manual en staging del flujo afectado (sin tests automatizados existentes para la mayoría de estos servicios, el QA manual en staging es obligatorio antes de mergear a `main`).
3. No convertir SQL a Eloquent ni a `DB::select()` fluido en esta ronda — eso es una fase posterior y separada, evaluada módulo por módulo según amerite (candidatos naturales: reportes que ya calculan todo en PHP tras un solo `SELECT`, como `CirugiasDashboardService::fetchReportProtocoloAggregates()`).
