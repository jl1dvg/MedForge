# Agenda V3 — Real Data Bridge

**Fecha:** 2026-06-05  
**Branch:** claude/zen-hamilton-IyzzU (PR #364)  
**Estado:** Diseño aprobado, pendiente implementación

---

## Problema

El frontend Agenda V3 ya está desplegado y la migración fue corrida, pero muestra data falsa del seeder (ej. "Dr. Andrés Vargas") en lugar de los médicos y citas reales que existen en `procedimiento_proyectado` (synced desde SigCenter).

### Causas raíz identificadas

1. **Seeder + sync coexisten sin limpieza limpia**: El seeder inserta médicos con IDs prefijo `md_`. El `syncMedicosFromPP()` solo elimina registros donde `id NOT LIKE 'md_%'`, por lo que los médicos del seeder sobreviven junto a los reales.

2. **Cache bloquea re-sync**: `syncMedicosFromPP()` guarda un flag de 6 horas en cache. Una vez que corrió (probablemente al primer hit de `/config`), no vuelve a sincronizar hasta que expire.

3. **Filtro de sede en `fetchPPCitas` es case-sensitive exacto**: Compara `TRIM(pp.sede_departamento) = ?` con el label de la sede. Si `procedimiento_proyectado` tiene "CEIBOS" y `agenda_sedes` tiene "Ceibos", ninguna cita legacy aparece.

---

## Solución — Opción A: Limpieza quirúrgica del seeder + fix del sync

### 1. Quitar médicos del seeder

`AgendaV3Seeder` no debe insertar filas en `agenda_medicos`. Los médicos se obtienen exclusivamente de `procedimiento_proyectado` vía sync. El seeder solo gestiona:
- `agenda_sedes` (2 sedes reales de CIVE)
- `agenda_salas` (salas reales por sede)
- `agenda_tipos_cita` (catálogo de tipos)
- `agenda_horarios` (horarios base por sede)

Si ya corrió el seeder con médicos falsos, el seeder debe **truncar** `agenda_medicos` al inicio de su ejecución.

### 2. Full-replace en `syncMedicosFromPP()`

Cambiar la lógica de limpieza de:
```php
DB::table('agenda_medicos')->where('id', 'not like', 'md_%')->delete();
```
a un **upsert con deactivación de ausentes**:
- Obtener todos los slugs que vinieron del PP en esta pasada
- Desactivar (`activo = false`) los médicos que NO aparecieron
- Hacer `updateOrInsert` de los que sí aparecieron (con `activo = true`)
- Esto preserva médicos históricos (con sus colores/configuraciones) sin eliminarlos permanentemente

### 3. Reducir TTL de cache y agregar endpoint de force-sync

- Reducir cache de `agenda_v3.medicos_synced` de 6h a **30 minutos**
- Agregar endpoint admin `POST /v2/api/agenda/v3/sync` que:
  - Limpia la cache key `agenda_v3.medicos_synced`
  - Llama `syncMedicosFromPP()` inmediatamente
  - Retorna conteo de médicos sincronizados
- Solo accesible con permiso `administrativo`

### 4. Corregir filtro de sede en `fetchPPCitas`

Cambiar el filtro de comparación exacta a una búsqueda tolerante:
```sql
-- Antes (case-sensitive exacto):
AND TRIM(pp.sede_departamento) = ?

-- Después (case-insensitive con normalización):
AND UPPER(TRIM(pp.sede_departamento)) LIKE UPPER(?)
```
Donde el valor a buscar es el label de la sede (ej. "Ceibos" → busca `LIKE '%CEIBOS%'`).

**Implementación elegida:** al construir `sedesMap` en `fetchPPCitas`, agregar una segunda clave por cada sede con el label en `UPPER()`, y buscar también con `mb_strtoupper($sedeRaw)` al resolver el slug. Esto no requiere cambiar el SQL.

---

## Flujo de datos resultante

```
GET /v2/api/agenda/v3/config
  → syncMedicosFromPP() [si cache expiró o force-sync]
      → lee DISTINCT doctor FROM procedimiento_proyectado
      → upsert en agenda_medicos (activo=true)
      → desactiva médicos ausentes (activo=false)
  → retorna sedes, medicos (solo activo=true), salas, tipos, horarios

GET /v2/api/agenda/v3/citas?fecha=2026-06-05&sede=ceibos
  → agenda_citas_v3 (citas creadas manualmente en V3)
  → fetchPPCitas() [bridge a procedimiento_proyectado]
      → JOIN patient_data + visitas
      → filtro de sede case-insensitive
  → merge + deduplicación + orden por hora_ini
```

---

## Archivos a modificar

| Archivo | Cambio |
|---------|--------|
| `database/seeders/AgendaV3Seeder.php` | Truncar `agenda_medicos` al inicio, eliminar insert de médicos |
| `app/Modules/Agenda/Http/Controllers/AgendaV3Controller.php` | Full-replace en `syncMedicosFromPP()`, fix filtro sede, nuevo endpoint `sync`, cache 30 min |
| `routes/v2/agenda.php` | Registrar ruta `POST /v3/sync` |

---

## Criterios de éxito

- [ ] `/v2/agenda/v3` muestra médicos reales de `procedimiento_proyectado`
- [ ] Las citas del día en `procedimiento_proyectado` aparecen en el FlowBoard y calendario
- [ ] `POST /v2/api/agenda/v3/sync` retorna médicos actualizados sin reiniciar servidor
- [ ] Filtro por sede funciona con variantes de capitalización ("CEIBOS", "Ceibos", "ceibos")
- [ ] No aparecen médicos del seeder (Dr. Andrés Vargas, etc.)
