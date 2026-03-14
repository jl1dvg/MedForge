# Guía de uso del módulo Codes (v2 Laravel)

## 1) Alcance del módulo
El módulo `Codes` en v2 cubre:
- Catálogo de códigos (`/v2/codes`).
- Creación/edición/eliminación de códigos.
- Relación entre códigos.
- Precios base por nivel (`N1`, `N2`, `N3`).
- Precios dinámicos por afiliación (tomados desde `afiliacion_categoria_map`).
- Constructor de paquetes (`/v2/codes/packages`).

## 2) Permisos requeridos
- Ver catálogo: `codes.view` o `codes.manage` o `administrativo`.
- Crear/editar/eliminar/relacionar y paquetes: `codes.manage` o `administrativo`.
- Búsqueda de códigos para paquetes: `codes.view|codes.manage|crm.view|crm.manage|administrativo`.

## 3) Flujo de uso del catálogo
## 3.1 Listado y filtros
- Ir a `/v2/codes`.
- Filtros disponibles:
  - Texto (`codigo` o `descripcion`).
  - Tipo (`code_type`).
  - Categoría (`superbill`).
  - Checkboxes: activos, reportables, financieros.
- Botón `Recargar` refresca DataTable sin perder filtros.

## 3.2 Crear código
- Ir a `/v2/codes/create`.
- Completar al menos:
  - `Codigo`.
  - Campos clínicos/administrativos opcionales (`modifier`, `code_type`, `superbill`, etc.).
  - Precios base (`N1`, `N2`, `N3`) si aplica.
  - Precios por afiliación en la tabla de `pricelevel`.
- Guardar.

## 3.3 Editar código
- Desde la tabla, `Editar`.
- Actualizar campos.
- Ajustar precios por afiliación.
- Guardar cambios.

## 3.4 Eliminar o activar/desactivar
- En edición:
  - `Eliminar` borra el código.
  - `Activar/Desactivar` alterna el estado `active`.

## 4) Precios por afiliación (nueva lógica)
## 4.1 Fuente de niveles
- El formulario usa `afiliacion_categoria_map` como fuente de `pricelevel`.
- Cada fila de afiliación genera un campo editable de precio.
- `level_key` usado para persistencia: `afiliacion_norm`.

## 4.2 Persistencia
- Tabla de destino: `prices`.
- Estructura usada:
  - `code_id` = ID de `tarifario_2014`.
  - `level_key` = `afiliacion_norm`.
  - `price` = valor ingresado.
- Si un precio de afiliación se deja vacío y se guarda, se elimina ese registro puntual en `prices`.

## 5) Relaciones entre códigos
- En edición del código:
  - Ingresar `related_id`.
  - Elegir tipo de relación (`maps_to` o `relates_to`).
  - Agregar.
- Se puede quitar una relación existente con `Quitar`.

## 6) Constructor de paquetes
Ruta: `/v2/codes/packages`.

Funciones:
- Listar paquetes.
- Crear/editar nombre, categoría, descripción y estado.
- Agregar ítems manuales.
- Buscar y agregar códigos al paquete.
- Guardar o eliminar paquete.

Reglas:
- El paquete requiere nombre y al menos un ítem.
- Totales se calculan por cantidad, precio unitario y descuento.

## 7) Endpoints v2 principales
- `GET /v2/codes`
- `GET /v2/codes/create`
- `POST /v2/codes`
- `GET /v2/codes/{id}/edit`
- `POST /v2/codes/{id}`
- `POST /v2/codes/{id}/delete`
- `POST /v2/codes/{id}/toggle`
- `POST /v2/codes/{id}/relate`
- `POST /v2/codes/{id}/relate/del`
- `GET /v2/codes/datatable`
- `GET /v2/codes/packages`
- `GET /v2/codes/api/packages`
- `GET /v2/codes/api/packages/{id}`
- `POST /v2/codes/api/packages`
- `POST /v2/codes/api/packages/{id}`
- `POST /v2/codes/api/packages/{id}/delete`
- `GET /v2/codes/api/search`

## 8) Cutover desde legacy
Existe flag de redirección:
- `CODES_V2_UI_ENABLED=true`

Con el flag activo, rutas legacy `/codes*` redirigen a `/v2/codes*`.

## 9) Validaciones de negocio relevantes
- Restricción de duplicado:
  - `(codigo, code_type, modifier)` debe ser único.
- Precios:
  - Solo numéricos.
- Relaciones:
  - `related_id` obligatorio y mayor a cero.

## 10) Checklist rápido QA
- Abrir `/v2/codes` y verificar carga de tabla.
- Crear código nuevo con precios por afiliación.
- Editar código y cambiar 2-3 precios por afiliación.
- Eliminar un precio de afiliación (dejar vacío y guardar).
- Crear paquete y agregar código desde búsqueda.
- Confirmar permisos (usuario con `codes.view` no debe poder editar).

