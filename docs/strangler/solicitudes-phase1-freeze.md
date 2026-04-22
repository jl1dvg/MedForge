# Solicitudes Phase 1 Freeze

Fecha: 2026-04-22
Estado: activo

## Objetivo

Congelar el alcance de `Solicitudes legacy` para que el trabajo nuevo ocurra solo en Laravel y el módulo no siga acumulando deuda de migración.

## Reglas

### 1. Legacy de Solicitudes queda congelado

No se deben agregar nuevas funciones, vistas, endpoints ni integraciones en:

- [modules/solicitudes](/Users/jorgeluisdevera/PhpstormProjects/MedForge/modules/solicitudes)

Se permite tocar legacy solo para:

- apagar rutas;
- redirigir a v2;
- remover código muerto;
- corregir un incidente crítico de producción mientras se implementa el reemplazo en Laravel.

### 2. Nuevos cambios van solo a Laravel

Toda mejora, integración o ajuste funcional de `Solicitudes` debe implementarse en:

- [laravel-app/app/Modules/Solicitudes](/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/app/Modules/Solicitudes)
- [laravel-app/resources/views/solicitudes](/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/resources/views/solicitudes)
- [public/js/pages/solicitudes](/Users/jorgeluisdevera/PhpstormProjects/MedForge/public/js/pages/solicitudes)
- [public/js/pages/shared/crmPanelFactory.js](/Users/jorgeluisdevera/PhpstormProjects/MedForge/public/js/pages/shared/crmPanelFactory.js)
- [laravel-app/routes/v2/solicitudes.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/routes/v2/solicitudes.php)

### 3. No nuevas dependencias a LegacySessionAuth en Solicitudes

Mientras dure el cutover, no se deben introducir nuevas referencias a:

- `LegacySessionAuth`
- `legacy.auth`
- `legacy.permission`

dentro del alcance de `Solicitudes` en Laravel.

La dependencia actual debe reducirse, no crecer.

## Validación operativa

Ejecutar:

```bash
php tools/tests/solicitudes_cutover_guard.php
```

El script falla si detecta:

- cambios staged/unstaged dentro de `modules/solicitudes`;
- nuevas referencias a `LegacySessionAuth` en archivos de `Solicitudes` dentro de Laravel.

## Excepciones

Si un cambio necesita tocar legacy por incidente real:

1. documentar por qué no puede resolverse solo en Laravel;
2. implementar el equivalente o reemplazo en Laravel en el mismo ciclo;
3. dejar plan de eliminación del parche legacy.
