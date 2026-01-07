# Prueba manual: fallo al guardar detalles de CRM

**Objetivo:**

- Confirmar que cuando `guardarDetalles` falla, la API devuelve un mensaje con referencia de error y el evento queda registrado (mensaje completo y stack) en `storage/logs/crm.jsonl`.
- Validar que al modificar etapa/responsable/fuente para un lead ya vinculado, el CRM persiste los cambios (lead.status/assigned_to/source) y la petición no falla.
- Verificar que, si se actualiza el lead directamente desde el módulo de CRM, el panel de CRM en Solicitudes muestra los valores modificados (etapa, responsable, fuente, email/teléfono).

## Pasos

1. Preparar un caso que fuerce un error en `SolicitudCrmService::guardarDetalles` (por ejemplo, configurando el servicio para lanzar una excepción controlada en un entorno de prueba).
2. Realizar una petición autenticada `POST` a `/modules/solicitudes/controllers/SolicitudController.php?action=crmGuardarDetalles&solicitudId={ID}` con un payload válido.
3. Validar que la respuesta HTTP es `500` y contiene `{"success":false,"error":"No se pudieron guardar los cambios del CRM (ref: <id>)"}` donde `<id>` es una referencia hexadecimal de 12 caracteres generada para el error.
4. Abrir `storage/logs/crm.jsonl` y verificar que se registró una entrada con `"channel":"crm"`, los campos `solicitud_id`, `usuario_id`, `error_id` igual al `<id>` devuelto en la respuesta, y que incluye `exception.message` y `trace` con la traza completa.
5. Con una solicitud que ya tenga `crm_lead_id` asociado, enviar un `POST` a la misma ruta cambiando únicamente `pipeline_stage`, `responsable_id` y/o `fuente`.
6. Confirmar que la respuesta es exitosa (`success:true`) y que en el CRM el lead asociado refleja los valores actualizados en `status` (etapa), `assigned_to` y `source`.
7. Editar el lead desde el panel principal de CRM (cambiar etapa, responsable, fuente, email o teléfono) y abrir el panel de CRM de la solicitud; confirmar que los campos mostrados coinciden con los cambios hechos en el lead.

## Resultado esperado

- Se devuelve un mensaje con referencia de error y código HTTP `500` sin exponer detalles internos.
- La traza de la excepción, el mensaje completo y los identificadores (`solicitud_id`, `usuario_id`, `error_id`) quedan almacenados en `storage/logs/crm.jsonl`.
- Si la tabla `patient_data` carece de `first_name/last_name` y solo tiene `name`, la reintentos eliminan esas columnas y la petición termina en éxito sin error de columna desconocida.
- Para leads existentes, la actualización de etapa/responsable/fuente persiste en el CRM sin generar errores de guardado.
