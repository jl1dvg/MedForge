# Guía rápida de problemas frecuentes del CRM

## Errores observados

1. `content.js:24 Failed to parse item from local storage: short`
   - El script está intentando leer un valor del `localStorage` y convertirlo a JSON, pero el contenido guardado no es un JSON válido. 
   - Suele ocurrir cuando se guardó solo una cadena simple (ej. `short`) y luego se lee con `JSON.parse()`.
   - **Cómo corregirlo:** limpiar el `localStorage` de la clave usada por el CRM/extension (`localStorage.removeItem('...')`) o guardar el valor como JSON válido (ej. `"\"short\""` o un objeto `{ value: "short" }`).

2. `No se pudo convertir el lead Error: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'first_name' in 'field list'`
   - El código que convierte un lead a cliente/paciente está intentando insertar o leer una columna `first_name` en `crm_leads`, pero la tabla definida en `database/migrations/20240521_crm_core.sql` usa el campo `name` (nombre completo) y no separa nombre/apellido.
   - **Cómo corregirlo:**
     - Actualizar la consulta para usar `name` en lugar de `first_name`/`last_name`, o
     - Agregar columnas `first_name` y `last_name` a la tabla si la interfaz realmente requiere separar el nombre.
   - Mientras exista la discrepancia, la conversión de leads fallará con este error.

## Circuito del CRM (resumen)

1. **Captura de lead**
   - Formularios o importaciones crean registros en `crm_leads` (`name`, `email`, `phone`, `status`, `source`, `notes`, asignación y autor). 
   - El estado inicial suele ser `nuevo` y se puede cambiar a `en_proceso` o `perdido` según avance.

2. **Gestión y seguimiento**
   - Los leads pueden asignarse a usuarios (`assigned_to`) para llamadas o seguimiento. 
   - Las notas y el estado permiten trazar el progreso. 
   - La vista de lista debería ofrecer acciones rápidas: ver, editar, convertir o eliminar, similar al menú contextual de Perfex.

3. **Conversión a paciente/cliente**
   - Al confirmar la conversión, se sincroniza o crea el paciente en `crm_customers` y se relaciona el lead (`customer_id`).
   - Se valida el `hc_number` (historia clínica) y se copian datos de contacto.
   - Si el esquema no coincide (ej. se espera `first_name`), la conversión falla; por eso es clave alinear columnas con los formularios de conversión.

4. **Relación con otros módulos**
   - Proyectos, tareas y tickets pueden vincularse a un lead (`lead_id` o `related_lead_id`) para mantener trazabilidad.
   - Al convertir el lead, las referencias permiten seguir usando el mismo identificador de `crm_leads`.

## Diseño y experiencia (paridad con Perfex)

- **Acciones en tablas**: Perfex muestra un menú dentro de cada fila (Ver, Editar, Eliminar, Convertir). Si faltan botones de edición/conversión, revise la plantilla de la tabla y añada las acciones usando el `id` del lead (`init_lead(id)` en Perfex) o rutas equivalentes.
- **Ancho completo**: las tablas de Perfex ocupan la anchura del contenedor. Asegúrate de que la vista use una `table-responsive` que no esté dentro de una columna estrecha o ajusta el `col-xx` para usar 12 columnas si quieres que ocupe todo el ancho.
- **Formulario lateral/modals**: Perfex abre el detalle/edición en un modal o panel lateral. Puedes replicar el patrón cargando el formulario de lead en un modal y reutilizando el mismo endpoint de creación/edición; la conversión de lead también puede abrirse como modal para mantener la página de lista limpia.

## Próximos pasos recomendados

1. Alinear el esquema o las consultas para que usen los mismos campos (`name` vs `first_name`/`last_name`).
2. Limpiar el `localStorage` de la extensión o serializar correctamente los valores leídos por `content.js`.
3. Revisar la plantilla de la tabla de leads para añadir las acciones rápidas (Ver/Editar/Convertir/Eliminar) y ajustar el ancho al 100% del contenedor.
4. Implementar el modal o panel lateral para creación/edición/conversión de leads en cada pestaña del CRM para acercar la experiencia a Perfex.
