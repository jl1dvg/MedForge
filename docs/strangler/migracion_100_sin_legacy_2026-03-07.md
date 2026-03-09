# Criterio de cierre: migración 100% sin legacy
Fecha: 2026-03-07

## Definición de 100% migrado
Se considera 100% migrado únicamente cuando backend y frontend operan en Laravel v2 sin depender de controladores, sesiones, rutas o fallback de legacy.

## Estado actual
- **No cumple aún** el 100%: existen dependencias legacy activas en autenticación/sesión, reporting y paridad de exámenes.

## Dependencias legacy activas
1. `LegacySessionAuth` se usa como mecanismo de autenticación en múltiples controladores v2.
2. `ExamenesParityController` usa `LegacyExamenesBridge` para varios endpoints (bridge a `Controllers\\ExamenController`).
3. Reporting de solicitudes usa `Controllers\\SolicitudController` desde Laravel.
4. Hay redirects/proxies en `routes/api.php` para rutas históricas de reportes/imágenes.
5. UI de solicitudes y exámenes mantiene fallback por feature flag a rutas legacy (`redirectLegacy`).
6. Servicios de negocio con fallback a tablas legacy en ciertos escenarios (billing/prefactura/derivaciones históricas).

## Criterios de salida (checklist)
- [ ] Auth nativo Laravel (guard/session) en lugar de `PHPSESSID` legacy.
- [ ] Exámenes: eliminar bridge legacy y operar 100% con servicios nativos Laravel.
- [ ] Reporting: eliminar dependencia a controladores legacy.
- [ ] Solicitudes/Exámenes UI: remover fallback `redirectLegacy` y ejecutar sólo v2.
- [ ] Rutas legacy alias/redireccionadas: mantener solo compatibilidad temporal controlada o retirarlas.
- [ ] Datos legacy fallback: reducir a cero en flujos de operación normal.
- [ ] Suite smoke/E2E validada sobre rutas v2 sin invocar código legacy.

## Orden recomendado de ejecución
1. Migrar auth/sesión (bloqueante transversal).
2. Cerrar exámenes parity -> servicio nativo completo.
3. Cerrar reporting de solicitudes -> service layer Laravel.
4. Apagar fallback UI de solicitudes/exámenes.
5. Ejecutar ensayo de corte y remover proxies legacy innecesarios.
