# Principios de arquitectura MedForge

Este documento no es un plan de migración — es la constitución técnica de MedForge. El [plan de migración PDO → Container](./pdo-to-container-migration.md) tiene fecha de expiración (termina cuando el último servicio deja de recibir `PDO`); estos principios no. Se aplican a código nuevo desde hoy, y son el criterio contra el que se revisa cualquier PR, migrado o no.

Cuando un principio de este documento y una decisión puntual de un PR entren en conflicto, gana este documento. Si un principio deja de tener sentido, se discute y se cambia acá explícitamente — no se lo ignora en silencio caso por caso.

---

## 1. Dependency Injection es obligatoria

Toda clase que necesite una colaboración (conexión a base de datos, otro servicio, un cliente HTTP, un logger) la recibe por constructor. Nunca la construye ni la busca por sí misma.

```php
// ❌ Prohibido
class ExamenesReportingService {
    public function algo() {
        $pdo = DB::connection()->getPdo();
        $mailer = new NotificationMailer($pdo);
    }
}

// ✅ Correcto
class ExamenesReportingService {
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly NotificationMailer $mailer,
    ) {}
}
```

Esto no es una preferencia de estilo: es lo que hace posible que el Service Container resuelva, mockee y testee cualquier clase sin tocar su código interno.

## 2. Ningún `new Service()` fuera de factories o Service Providers

Si una clase necesita una instancia de otra clase gestionada por el framework (un Service, un Repository, un cliente externo), se resuelve — nunca se construye a mano.

- En un Controller o un Service: recibirla por constructor (Laravel la autowirea).
- En un comando Artisan, un job, o código que no puede recibir inyección directa: `app(Clase::class)`.
- Si la construcción es genuinamente compleja (necesita lógica condicional, distintos entornos, parámetros que no vienen del container): un Service Provider con `$this->app->bind(...)` o una Factory explícita — no un `new` disperso por el código.

`new` sigue siendo válido para **objetos de valor** (DTOs, colecciones, excepciones, value objects) que no tienen dependencias externas ni ciclo de vida gestionado. La regla aplica a clases que representan una colaboración/servicio, no a cualquier instanciación de PHP.

## 3. Los Controllers nunca conocen SQL

Un Controller orquesta HTTP: recibe el request, valida entrada, llama a un Service, decide qué vista/respuesta devolver. No arma queries, no llama a `DB::` directamente, no sabe qué tabla existe.

```php
// ❌ Prohibido en un Controller
public function index(Request $request) {
    $rows = DB::select('SELECT * FROM protocolo_data WHERE ...');
}

// ✅ Correcto
public function index(Request $request, CirugiasDashboardService $service) {
    $report = $service->buildReportPayload($start, $end, $sedeFilter);
    return view('cirugias.dashboard', ['report' => $report]);
}
```

Si un Controller legacy todavía tiene SQL embebido, migrarlo a un Service es prerrequisito antes de cualquier otro cambio en ese Controller — no se agrega funcionalidad nueva sobre un Controller que viola este principio.

## 4. Los Services contienen reglas de negocio

Un Service responde preguntas de negocio ("¿cuántas cirugías están pendientes de facturar?", "¿este paciente califica para X convenio?"), no preguntas de almacenamiento ("¿qué SQL trae esas filas?"). Cuando un Service es pequeño, es aceptable que también contenga el SQL directamente (ver Fase 0-1 del plan de migración: el objetivo inmediato es sacar `PDO` de la firma, no reorganizar cada clase). Pero el destino final, para todo servicio que crezca, es que las reglas de negocio y el acceso a datos vivan en clases separadas — ver principio 5 y 9.

## 5. Los Repositories contienen acceso a datos

Un Repository sabe cómo traer y persistir datos — SQL, joins, agregaciones — y no sabe nada de reglas de negocio. No decide si un pendiente de facturar cuenta como "backlog crítico"; solo sabe traer las filas que un criterio de fecha/sede/afiliación pide. Un Repository:

- Recibe `ConnectionInterface` (o `PDO` internamente si el método concreto lo necesita), nunca lógica de negocio.
- Expone métodos con nombres de datos (`fetchTopCirujanosRows`, `findProtocolosByPeriodo`), no nombres de negocio (`calcularOportunidadEstimada`).
- No decide formatos de salida para UI (`$moneyFmt`, labels traducidos) — eso es del Service que lo consume.

## 6. SQL complejo es válido y preferido cuando aporta rendimiento

MedForge tiene reportes con joins condicionales dinámicos, agregaciones pesadas y filtros que cambian de forma según el contexto (ver `CirugiasDashboardService`, `BillingInformeDataService`). Escribir SQL a mano ahí no es deuda técnica — es la herramienta correcta. El estándar de MedForge no es "todo SQL crudo es legacy que hay que eliminar"; es "el acceso a datos vive en la capa correcta (Repository), esté escrito como SQL crudo, Query Builder o Eloquent según lo que ese caso necesite".

Un query optimizado explícitamente por un incidente de producción (ver PRs #463, #472, #473 del reporte de Cirugías) no se toca para "modernizarlo" sin una razón de negocio o performance que lo justifique — se documenta como intencional.

## 7. Eloquent no es un objetivo, es una herramienta

Migrar a Eloquent nunca es, por sí solo, el criterio de éxito de un PR. Se usa donde el caso es simple (CRUD directo, sin joins condicionales ni agregaciones complejas) y donde la legibilidad/mantenibilidad que da un Model supera el costo de tener una capa de abstracción adicional sobre la tabla. Si un reporte necesita SQL crudo para rendir bien, se queda en SQL crudo — indefinidamente, no como una fase transitoria hacia Eloquent.

## 8. Query Builder se usa cuando mejora legibilidad sin degradar performance

Entre SQL crudo (`$pdo->prepare(...)`) y Eloquent hay un punto medio: el Query Builder de Laravel (`DB::table(...)->where(...)`). Se prefiere sobre SQL crudo cuando:

- El query es un filtro/orden/paginación directo, sin joins condicionales armados dinámicamente.
- No depende de tipado explícito de PDO (`PDO::PARAM_INT`/`PARAM_NULL`) de forma crítica para el resultado.
- El binding fluido de Laravel no complica ni oscurece el query respecto a la versión en SQL crudo.

Si alguno de esos puntos no se cumple, se queda en SQL crudo dentro del Repository. No hay presión por "modernizar" un query que funciona y rinde bien.

## 9. Ningún módulo nuevo puede usar arquitectura legacy

Todo módulo o feature que se cree desde ahora en adelante nace ya con:

- Sus servicios resueltos por el Service Container (principios 1 y 2) desde el primer commit.
- Ningún `PDO` en ninguna firma de constructor — se recibe `ConnectionInterface` desde el día uno si el módulo necesita SQL crudo.
- Separación Controller → Service → Repository desde el diseño inicial si el módulo tiene lógica de acceso a datos no trivial (más de un par de queries, o queries reutilizadas en más de un lugar). Para un CRUD mínimo de una sola entidad, un Service que incluya su propio acceso a datos es aceptable — la separación es obligatoria cuando el tamaño/complejidad lo amerita (ver criterio del principio 10), no un ritual para cualquier módulo trivial.

No existe "lo hacemos legacy ahora y lo arreglamos después" para código nuevo. La deuda técnica que se permite es la heredada (los 32 servicios auditados en el plan de migración), no la que se crea hoy.

## 10. Criterio objetivo para marcar un servicio como candidato a Fase 2 (Repository)

Un servicio se marca como candidato a extracción de Repository (Fase 2 del plan de migración) cuando cumple **cualquiera** de estas condiciones:

- Supera ~1000 líneas de código.
- Mezcla reglas de negocio con acceso a datos de forma entrelazada (no hay una separación clara de "esta sección arma SQL" vs "esta sección decide qué significa el resultado").
- El mismo query, o una variante cercana, se repite en más de un método del propio servicio.
- Es un dashboard o reporte ejecutivo consumido directamente por un Controller (alto tráfico, alta visibilidad de cualquier regresión).

Esto no obliga a extraer el Repository de inmediato — obliga a **marcarlo** en el plan de migración (columna "¿Candidato a Fase 2?") para que la decisión de cuándo hacerlo sea explícita y no un descubrimiento accidental en medio de otro trabajo.

---

## Cómo se usa este documento

- Todo PR de arquitectura (migración PDO→Container, extracción de Repository, evaluación de Query Builder/Eloquent) se revisa contra estos 10 principios, no contra preferencias del momento.
- Todo módulo nuevo se diseña leyendo primero este documento, no copiando el patrón del módulo legacy más cercano.
- Si en algún punto un principio resulta impráctico para un caso real, se corrige acá — con una sección de historial de cambios, no editando en silencio.
