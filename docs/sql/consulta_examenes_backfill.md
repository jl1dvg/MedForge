# Backfill de exámenes normalizados

Los tableros de exámenes consumen la tabla `consulta_examenes`, donde cada examen solicitado
se almacena como una fila independiente. Antes de que existiera esa normalización, los exámenes
vivían como un arreglo JSON en el campo `consulta_data.examenes`, por ejemplo:

```json
[
    {
        "codigo": "281229",
        "nombre": "PAQUIMETRIA CORNEAL",
        "lateralidad": "AO"
    },
    {
        "codigo": "281295",
        "nombre": "RETINOGRAFIA PANORAMICA  (AO)",
        "lateralidad": "AO"
    }
]
```

El comando `tools/sync_consulta_examenes.php` lee esos payloads históricos, aplica las mismas reglas
que usa la app al guardar nuevas consultas (sanitiza nombre, lateralidad, prioridad, etc.) y llena la
tabla `consulta_examenes`. Está pensado para ejecutarse cada vez que se migra información legada o
cuando se importan lotes desde `consulta_data`.

## Uso

```bash
php tools/sync_consulta_examenes.php [opciones]
```

Opciones relevantes:

- `--dry-run`: procesa y valida los registros pero no escribe en la base de datos. Útil para
  verificar cuántos exámenes se normalizarán.
- `--verbose`: imprime cada consulta procesada y el número de exámenes detectados.
- `--hc=HC123`: limita la migración a un número de historia clínica específico.
- `--form=F001`: limita la migración a un `form_id` concreto.
- `--limit=100`: procesa solo los primeros `N` registros que cumplan los filtros.

## Ejemplos

Simular la migración completa para revisar estadísticas:

```bash
php tools/sync_consulta_examenes.php --dry-run --verbose
```

Ejecutar la sincronización real para un paciente en particular:

```bash
php tools/sync_consulta_examenes.php --hc=HC123
```

Cada ejecución es idempotente: antes de insertar los exámenes normalizados se elimina cualquier
registro previo para la combinación `(form_id, hc_number)`, de modo que es seguro correr el comando
más de una vez.
