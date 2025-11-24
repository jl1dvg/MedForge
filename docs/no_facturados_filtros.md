# Flujo de filtros en "Procedimientos no facturados"

Este módulo usa filtros rápidos para acotar el listado que se consume desde `/api/billing/no-facturados`.

## Campos disponibles
- **Fecha desde / hasta**: rango aplicado sobre la fecha del procedimiento.
- **Afiliación**: texto exacto de la afiliación.
- **Estado revisión**: `Revisado` o `Pendiente` (solo aplica a quirúrgicos).
- **Tipo**: `Quirúrgico` o `No quirúrgico`.
- **Paciente / HC**: búsqueda parcial por nombre o historia clínica.
- **Procedimiento / Código**: coincidencia parcial por texto o código.
- **Rango valor**: valores mínimo y/o máximo del monto estimado.

## Vistas guardadas
Las combinaciones de filtros pueden guardarse como **vistas** para reutilizarlas desde la cabecera de filtros.

1. Configura los campos y presiona **"Guardar vista"**.
2. Asigna un nombre (ej.: "Pendientes seguros", "Mayor a $500").
3. Selecciona la vista en el selector de "Vistas" para aplicar los filtros guardados.
4. Usa el ícono de papelera para eliminar la vista seleccionada.

Las vistas se almacenan en `localStorage` del navegador bajo la clave `billing.no-facturados.vistas` y son locales a cada usuario/navegador.

## Reglas de carga y estado
- Las tablas se construyen con DataTables en modo server-side y muestran estados de carga personalizados.
- Ante errores de red o del endpoint, se presenta un aviso contextual bajo la tabla afectada con un mensaje resumido.
- El modal de preview muestra un indicador de carga y un mensaje de error claro en caso de fallos.
