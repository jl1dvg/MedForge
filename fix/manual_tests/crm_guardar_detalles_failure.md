# Prueba manual: fallo al guardar detalles de CRM

**Objetivo:** confirmar que cuando `guardarDetalles` falla, la API devuelve un mensaje con referencia de error y el evento queda registrado (mensaje completo y stack) en `storage/logs/crm.jsonl`.

## Pasos

1. Preparar un caso que fuerce un error en `SolicitudCrmService::guardarDetalles` (por ejemplo, configurando el servicio para lanzar una excepción controlada en un entorno de prueba).
2. Realizar una petición autenticada `POST` a `/modules/solicitudes/controllers/SolicitudController.php?action=crmGuardarDetalles&solicitudId={ID}` con un payload válido.
3. Validar que la respuesta HTTP es `500` y contiene `{"success":false,"error":"No se pudieron guardar los cambios del CRM (ref: <id>)"}` donde `<id>` es una referencia hexadecimal de 12 caracteres generada para el error.
4. Abrir `storage/logs/crm.jsonl` y verificar que se registró una entrada con `"channel":"crm"`, los campos `solicitud_id`, `usuario_id`, `error_id` igual al `<id>` devuelto en la respuesta, y que incluye `exception.message` y `trace` con la traza completa.

## Resultado esperado

- Se devuelve un mensaje con referencia de error y código HTTP `500` sin exponer detalles internos.
- La traza de la excepción, el mensaje completo y los identificadores (`solicitud_id`, `usuario_id`, `error_id`) quedan almacenados en `storage/logs/crm.jsonl`.
- Si la tabla `patient_data` carece de `first_name/last_name` y solo tiene `name`, la reintentos eliminan esas columnas y la petición termina en éxito sin error de columna desconocida.
