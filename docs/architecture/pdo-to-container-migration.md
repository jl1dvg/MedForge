# Migración de PDO manual al Service Container de Laravel

> Este documento es el **plan de migración** — tiene fecha de expiración. Los principios permanentes que rigen toda la arquitectura de MedForge, código nuevo incluido, están en [`medforge-architecture-principles.md`](./medforge-architecture-principles.md).

**Meta final:** MedForge deja de depender *conceptualmente* de PDO — no solo de recibirlo manualmente. La aplicación debe sentirse una app Laravel nativa (Service Container, DI, testeable), conservando el SQL optimizado donde sea la mejor herramienta.

**Estado actual:** Fase 0 y Fase 1 completadas para el servicio piloto (`CronTaskRepository`). Fases 0-1 para el resto de los 32 servicios auditados: pendientes. Fase 2 y Fase 3: no iniciadas — dependen de que 0 y 1 estén completas en cada módulo.

---

## 0. Resumen ejecutivo

MedForge corre sobre Laravel, pero una parte grande del código heredado (32 servicios) recibe la conexión a base de datos como un objeto `PDO` crudo, pasado a mano en cada `new Servicio($pdo)`. Esto bloquea el uso del Service Container: nada de esto es inyectable, mockeable, ni resoluble automáticamente por el framework.

La migración completa tiene **4 fases**, cada una con un objetivo distinto y una condición de salida clara. No se saltan fases dentro de un mismo módulo — un servicio no entra a Fase 2 sin haber completado 0 y 1.

| Fase | Objetivo | Toca SQL | Toca lógica de negocio | Condición de salida |
|---|---|---|---|---|
| **0** | Eliminar instanciación manual (`new Servicio($pdo)`) — resolver por el Service Container | No | No | Ningún `new Servicio(...)` fuera de un `ServiceProvider` o factory explícita; todo pasa por constructor injection o `app(Servicio::class)` |
| **1** | Eliminar `PDO` de las firmas de constructor — usar `ConnectionInterface` de Laravel | No | No | Ningún `__construct(PDO $x)` en el módulo; `$connection->getPdo()` es un detalle interno, no una firma pública |
| **2** | Extraer la capa de acceso a datos hacia Repositories — el Service deja de conocer SQL | No (el SQL se mueve, no se reescribe) | No | Dashboards/reportes grandes tienen un `XRepository` con los métodos de datos; el Service orquesta y aplica reglas de negocio, no arma queries |
| **3** | Evaluar caso por caso Query Builder / Eloquent | Sí, donde aporte mantenibilidad sin sacrificar rendimiento | No (mismo comportamiento) | Decisión explícita por Repository: "se queda en SQL crudo porque X" o "se migra a Query Builder porque Y" — documentada, no implícita |

Este documento (versión anterior) cubría el trabajo de Fase 0+1 para un solo servicio piloto. Esta revisión reorganiza el plan completo según las 4 fases y deja los criterios de Fase 2/3 explícitos para cuando el módulo correspondiente esté listo.

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

## 2. Propuesta de patrón por fase

### 2.1 Fase 0 + Fase 1 (esta ronda): el Service sigue teniendo SQL embebido, pero vive del Container

```
Controller → Service → ConnectionInterface (resuelto por el container) → PDO interno cuando se necesita
```

Los servicios existentes mantienen su forma actual (una clase con SQL embebido), pero:
- **Fase 0**: nadie hace `new Servicio($pdo)` — se resuelven por constructor injection o `app(Servicio::class)`.
- **Fase 1**: el tipo del parámetro del constructor deja de ser `PDO` y pasa a ser `Illuminate\Database\ConnectionInterface`. Internamente, si el código necesita la API específica de PDO (`prepare()`, `bindValue()` con tipos explícitos, `lastInsertId()`, `PDO::FETCH_ASSOC`), se obtiene con `$connection->getPdo()` — una sola vez, dentro de la clase, nunca desde afuera.

**Por qué se separan en dos fases y no una sola "quitar PDO":** son dos problemas distintos con dos riesgos distintos. Fase 0 (dónde se instancia) es puramente de wiring — cero riesgo, se revierte con un `git revert` trivial. Fase 1 (qué tipo recibe el constructor) toca la firma pública de la clase — cualquier código que la instancie directamente (tests, comandos, otros servicios) debe actualizarse también. Completar 0 primero en todos los módulos, y recién después 1, evita mezclar ambos tipos de cambio en el mismo commit y hace cada PR trivial de revisar.

```php
// Fase 0: aún PDO, pero ya no hay "new Servicio($pdo)" sueltos
public function __construct(private PDO $db) {}
// resuelto vía app(Servicio::class) o constructor injection en el controller,
// con un binding en un ServiceProvider: $this->app->bind(Servicio::class,
// fn ($app) => new Servicio($app->make('db')->connection()->getPdo()));

// Fase 1: el binding explícito ya no hace falta — Laravel resuelve
// ConnectionInterface nativamente, sin ServiceProvider
public function __construct(private readonly \Illuminate\Database\ConnectionInterface $connection)
{
    $this->pdo = $connection->getPdo();
}
```

En la práctica, para la mayoría de los 32 servicios auditados, Fase 0 y Fase 1 se hacen en el mismo PR (como en el piloto de `CronTaskRepository`, §3) porque el servicio no tiene otros consumidores que dependan de recibir `PDO` explícitamente. Se separan explícitamente en dos PRs solo cuando el servicio es instanciado también desde comandos Artisan o scripts fuera del framework HTTP (ej. `CronRunner`), donde conviene primero mover el wiring (Fase 0) y validar en staging antes de tocar la firma (Fase 1).

- **No se instancia el servicio manualmente.** Se resuelve vía constructor injection en controladores (`public function __construct(private AlgunServicio $service) {}`) o vía `app(AlgunServicio::class)` en código que aún no puede recibir inyección (comandos legacy, `routes/console.php`).
- **`Illuminate\Database\ConnectionInterface` no requiere binding manual** — Laravel ya lo resuelve out-of-the-box contra la conexión default (`registerCoreContainerAliases()` en el framework la alias-ea a `db.connection`). Confirmado en el piloto de esta fase (ver §3).
- **El SQL no se toca en 0 ni en 1.** Sigue siendo `$this->pdo->prepare(...)`, exactamente igual que hoy.
- Para servicios usados solo como dependencia interna de otros servicios (caso `AfiliacionDimensionService`), también se migran a recibir `ConnectionInterface` y se resuelven vía `app(AfiliacionDimensionService::class)` en vez de `new AfiliacionDimensionService($this->db)` — mismo patrón, sin excepciones.

### 2.2 Fase 2: extraer Repository — el Service deja de conocer SQL

```
Controller → Service (lógica de negocio) → Repository (acceso a datos) → PDO / ConnectionInterface
```

Una vez que un módulo completó 0 y 1, se evalúa si amerita extraer un `XRepository` dedicado. **No es automático para los 32 servicios** — aplica donde el Service es grande y mezcla reglas de negocio con SQL (los dashboards/reportes: `CirugiasDashboardService`, `BillingInformeDataService`, `ExamenesReportingService`, `HonorariosDashboardDataService`). Criterio para decidir si un servicio entra a Fase 2:

- Tiene métodos de más de ~80 líneas donde SQL y lógica de negocio están entrelazados.
- Se reutiliza el mismo query (o una variante) en más de un método del propio servicio.
- Es un candidato a tener tests unitarios de la lógica de negocio sin pegarle a la base de datos (mockeando el Repository).

El SQL **se mueve tal cual al Repository**, no se reescribe. El Service pasa a orquestar: llama al Repository, aplica reglas de negocio sobre los datos crudos, arma el payload de salida. Ejemplo del shape esperado, sin tocar ni una consulta:

```php
// Antes (Fase 1): CirugiasDashboardService tiene el JOIN completo inline
class CirugiasDashboardService {
    public function __construct(private readonly ConnectionInterface $connection) {}
    public function getTopCirujanos(...): array {
        $sql = "SELECT ... FROM protocolo_data pr LEFT JOIN ...";
        // prepare/execute/fetch, todo acá adentro
    }
}

// Después (Fase 2): el SQL se muda, línea por línea, al Repository
class CirugiasReportRepository {
    public function __construct(private readonly ConnectionInterface $connection) {}
    public function fetchTopCirujanosRows(string $start, string $end, string $sedeFilter): array {
        $sql = "SELECT ... FROM protocolo_data pr LEFT JOIN ..."; // idéntico al de antes
        // prepare/execute/fetch, sin cambios
    }
}

class CirugiasDashboardService {
    public function __construct(private readonly CirugiasReportRepository $repository) {}
    public function getTopCirujanos(...): array {
        $rows = $this->repository->fetchTopCirujanosRows(...);
        // agregaciones/reglas de negocio sobre $rows, igual que antes
    }
}
```

### 2.3 Fase 3: Query Builder / Eloquent, caso por caso

Una vez que un Repository existe y está aislado, se evalúa **por método, no por módulo entero**, si conviene migrarlo al Query Builder de Laravel (`DB::table(...)->where(...)`) o directamente a un modelo Eloquent. No es una meta en sí misma — es una herramienta que se usa donde reduce complejidad sin costar rendimiento.

Criterios para decidir SÍ migrar un método a Query Builder/Eloquent:
- El query es un CRUD simple (un `SELECT` con `WHERE`s directos, sin joins condicionales dinámicos ni agregaciones complejas).
- No depende de tipado explícito de PDO (`PDO::PARAM_INT`/`PARAM_NULL`) de forma crítica.
- Ganar test-ability / legibilidad supera el costo de la reescritura y su QA.

Criterios para decidir NO migrar (se queda en SQL crudo dentro del Repository, indefinidamente):
- Reportes con joins condicionales armados dinámicamente según filtros (el patrón dominante en `CirugiasDashboardService`, `BillingInformeDataService`: SQL que cambia de forma según qué filtros llegan).
- Queries con CTEs, subqueries correlacionadas convertidas a JOINs derivados (como el fix de sede de PR #463), o agregaciones que ya fueron afinadas por performance.
- Cualquier query que haya sido optimizada explícitamente por un incidente de producción (documentar el PR del fix como razón de "no tocar").

Cada decisión de Fase 3 se documenta en el propio Repository (comentario o sección en este documento) — no se asume "Eloquent es mejor" por defecto.

---

## 3. Prueba piloto (Fase 0 + Fase 1): `CronTaskRepository`

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

## 4. Plan de migración por módulo (Fase 0 + Fase 1)

Orden recomendado, de menor a mayor riesgo/superficie. La columna **¿Candidato a Fase 2?** marca qué módulos, una vez completadas 0 y 1, tienen servicios lo bastante grandes/mixtos (SQL + lógica de negocio) como para justificar extraer un Repository — no implica que se haga en la misma ronda.

| Orden | Módulo | Servicios a migrar (Fase 0+1) | Dificultad | Riesgo | Dependencias | ¿Candidato a Fase 2? | Justificación del orden |
|---|---|---|---|---|---|---|---|
| 1 | **Shared** | `AfiliacionDimensionService` | Baja | Medio | Ninguna, pero es dependencia transitiva de ~8 servicios | No — es ya una capa de acceso a datos pura, no mezcla lógica de negocio | Migrar primero porque desbloquea la migración limpia de todos los que lo instancian con `new AfiliacionDimensionService($db)`. Si se migra después, cada consumidor debe resolverlo dos veces (una vez como servicio propio, otra como dependencia interna). |
| 2 | **CRM** | `CrmCaseService` | Baja | Bajo | Ninguna | No, por ahora (módulo chico) | Módulo chico (2 archivos), buen segundo caso de prueba real (no piloto) para confirmar el patrón en un controlador+servicio de negocio, no solo un repository. |
| 3 | **Farmacia** | `FarmaciaDashboardService`, `RecetasConciliacionSyncService` | Baja-Media | Bajo | `AfiliacionDimensionService` (paso 1) | Sí — `FarmaciaDashboardService` es un dashboard | Módulo autocontenido, bajo tráfico, buen lugar para validar el patrón en un dashboard antes de tocar los dashboards de alto tráfico (Cirugías/Billing). |
| 4 | **Consultas** | `ConsultasParityService` | Media | Medio | `ConsultaExamenSyncService` (Examenes) | No | Instancia servicios de Examenes internamente — coordinar con el paso 5. |
| 5 | **Cirugías** | `CirugiaService`, `ProtocolosTemplateReadService`, `ProtocolosTemplateWriteService`, `CirugiasDashboardService` (último) | Media-Alta | Medio-Alto | `AfiliacionDimensionService` | **Sí, prioritario** — `CirugiasDashboardService` (2000+ líneas, dashboard/reporte ejecutivo) es el ejemplo canónico de §2.2 | Migrar primero los 3 servicios chicos y dejar `CirugiasDashboardService` para el final del módulo, cuando el patrón ya esté probado 4 veces. Una vez en Fase 1, es el primer candidato real a Fase 2 (`CirugiasReportRepository`) dado su tamaño y el historial reciente de incidentes de performance. |
| 6 | **Examenes / Imágenes** | `ConsultaExamenSyncService`, `ImagenesUiService`, `ExamenesParityService`, `ExamenesReportingService`, `ExamenesPrefacturaService` | Alta | Medio-Alto | `AfiliacionDimensionService`, `Mail` (envían correos) | Sí — `ExamenesReportingService`, `ImagenesUiService` (dashboard de reportes de imágenes) | Mayor cantidad de sub-servicios instanciados en cadena (`LeadConfigurationService`, `PusherConfigService`, `ExamenCrmService`, `ExamenMailLogService` — todos con `new X($this->db)` internamente). Requiere migrar la cadena completa en el mismo PR para no dejar wiring mixto. |
| 7 | **Mail** | `MailboxService`, `MailProfileService`, `NotificationMailer` | Media | Alto | Ninguna propia, pero es consumida por Examenes, CRM, Solicitudes | No | Se migra antes de Billing/Facturación porque varios flujos de facturación envían notificaciones por mail — mejor que Mail ya esté resuelto por el container cuando se migre Billing. |
| 8 | **Reporting** | `ConsultaReportDataService`, `CoberturaReportDataService`, `PostSurgeryRestReportDataService`, `AbstractSolicitudReport` | Media | Medio | Ninguna directa | Sí — son generadores de reportes, encajan naturalmente en el patrón Repository | Generadores de reportes PDF/export, bajo tráfico interactivo (se ejecutan on-demand, no en cada carga de dashboard) — riesgo controlado si algo sale mal. |
| 9 | **Solicitudes** | `SolicitudesPrefacturaService` | Media | Medio | `Mail`, `Codes` (`CodesPackageService`) | No | Coordinar con Mail (paso 7) ya migrado. |
| 10 | **Billing / Facturación** | `BillingParticularesReportService`, `BillingSoamRuleAdapter`, `HonorariosDashboardDataService`, `BillingDashboardDataService`, `BillingLeakageService`, `BillingPreviewService`, `BillingProcedimientosKpiService`, `BillingInformePacienteService`, `BillingSoamAdapter`, `NoFacturadosQueryService` | Alta | **Alto** | `AfiliacionDimensionService`, `Mail` | **Sí, el más grande** — `BillingInformeDataService`, `HonorariosDashboardDataService`, `BillingDashboardDataService` son dashboards/reportes grandes, mismo perfil que `CirugiasDashboardService` | El módulo con más servicios (10) y más dinero en juego (facturación real). Se deja al final porque para entonces el patrón ya se validó en 9 módulos distintos. Requiere el mayor cuidado en QA manual antes de mergear (afecta facturación real, cartera, pagos IESS). |
| 11 | **IdentityVerification** | `VerificationModel` | Baja | Bajo | Ninguna | No | Aislado, se puede intercalar en cualquier punto — bajo impacto si algo falla. |

**WhatsApp no aparece en la tabla**: ya no usa PDO crudo en ningún servicio (confirmado en la auditoría, §1.3) — queda fuera del alcance de esta migración.

### Reglas de ejecución — Fase 0 + Fase 1 (por módulo)

1. Un PR por módulo (o por servicio, si el módulo es grande como Billing/Examenes) — nunca mezclar la migración de arquitectura con cambios funcionales.
2. Cada PR: cambiar `PDO $db` → `ConnectionInterface $connection` + `$this->pdo = $connection->getPdo()`, actualizar los sitios de instanciación manual a constructor injection o `app(Servicio::class)`, `php -l` en todos los archivos tocados, y verificación manual en staging del flujo afectado (sin tests automatizados existentes para la mayoría de estos servicios, el QA manual en staging es obligatorio antes de mergear a `main`).
3. No convertir SQL a Eloquent ni a `DB::select()` fluido en esta ronda — Fase 2 y Fase 3 son posteriores y se evalúan por separado, servicio por servicio, según los criterios de §2.2 y §2.3.

### Reglas de ejecución — Fase 2 (por servicio, cuando aplique)

1. Solo se inicia Fase 2 en un servicio después de que su módulo completó Fase 0 y Fase 1 (regla dura — no se extrae Repository de un servicio que todavía recibe `PDO`).
2. El SQL se mueve línea por línea al Repository — se prohíbe "aprovechar" el movimiento para optimizar o simplificar queries en el mismo PR (eso ensucia el diff y mezcla riesgo de regresión funcional con refactor arquitectónico).
3. Un PR de Fase 2 = un Repository. No se extraen dos Repositories en el mismo PR salvo que sean del mismo servicio y compartan casi todo el contexto.
4. Primer candidato real: `CirugiasDashboardService` → `CirugiasReportRepository`, una vez que Cirugías (orden 5) complete Fase 0+1. Es el caso de uso que más beneficio inmediato tiene (2000+ líneas, historial de incidentes de performance ya documentado en PRs #463/#472/#473) y sirve de plantilla para el resto de los dashboards.

### Reglas de ejecución — Fase 3 (por método, cuando aplique)

1. Se evalúa método por método dentro de un Repository ya extraído, nunca "migrar todo el Repository a Eloquent" de una.
2. Se aplican los criterios de §2.3 antes de tocar cualquier query — si no cumple los criterios de "SÍ migrar", se documenta la decisión de quedarse en SQL crudo directamente como comentario en el método.
3. Fase 3 no tiene fecha objetivo. Es oportunista: se hace cuando un Repository específico necesita mantenimiento de todos modos (bug fix, feature nueva) y el método en cuestión cumple los criterios — no se agenda como trabajo dedicado salvo que el negocio lo pida explícitamente.
