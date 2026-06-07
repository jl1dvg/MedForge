# Solicitudes V3 CRM Operativo Design

Fecha: 2026-06-03

## Contexto

El PR 317 migra la vista de solicitudes quirurgicas a React. La interfaz ya existe y no se busca redisenarla. El problema principal es que varios sectores del panel lateral CRM y de prefactura todavia usan informacion incompleta, local o simulada: notas que no persisten, envios de WhatsApp/correo que solo muestran toast, propuestas hardcodeadas, botones sin accion real y datos clinico-operativos duplicados o inventados.

El CRM central tambien migro a React, por lo que la solucion no debe quedar acoplada solo al drawer de solicitudes. La nueva capa debe servir para Solicitudes V3 y para el CRM central React.

## Objetivo

Crear una capa de APIs V3 limpia y reutilizable para CRM, conectando las acciones visibles del panel a backend real, manteniendo el diseno actual. Separar el dominio CRM comercial compartido del dominio quirurgico-operativo de solicitudes.

## No Objetivos

- No cambiar el diseno visual general del panel.
- No crear una nueva experiencia de navegacion.
- No mantener mocks visibles como si fueran funcionalidad real.
- No permitir items libres/manuales en propuestas en esta etapa.
- No inventar campos quirurgicos o clinicos si no existe fuente real.

## Arquitectura

Se separan dos superficies de API:

1. `/v3/crm/...`
   - Casos CRM reutilizables por Solicitudes V3 y CRM central React.
   - Responsable, contactos, notas, tareas, actividad, comunicaciones, propuestas y documentos.

2. `/v3/solicitudes/...`
   - Informacion especifica de solicitudes quirurgicas.
   - Prefactura, cobertura, procedimiento, agenda, derivacion, diagnosticos y aptitud clinica.

Los endpoints V3 pueden reutilizar internamente servicios V2 ya existentes, pero el contrato publico debe ser estable, normalizado y pensado para React. El frontend no debe depender de nombres legacy inconsistentes ni de texto formateado cuando necesita datos estructurados.

## Contrato de Datos

Los payloads deben exponer IDs estables:

- `case_id`
- `solicitud_id`
- `form_id`
- `paciente_id`

Contactos:

- `primary_phone`
- `alternate_phones`
- `primary_email`
- `alternate_emails`

Afiliacion:

- `insurance_company`
- `insurance_plan`
- `insurance_code`

Cada vista decide que mostrar:

- Cards y filtros: solo empresa aseguradora.
- CRM y prefactura: plan de afiliacion, sin codigo ni empresa cuando no sea necesario.

Actividad:

- `id`
- `type`
- `occurred_at`
- `author`
- `description`
- `reference`

Propuestas:

- items
- tarifa usada
- afiliacion usada
- subtotal, impuestos y total
- estado
- PDF
- canales de envio

Aptitud clinica:

- estado
- fecha
- responsable
- observacion
- fuente del dato

## Panel CRM

Se mantiene el panel actual con sus tabs:

- `Seguimiento`
- `Tareas`
- `Notas`
- `Comunicacion`
- `Propuestas`
- `Documentos`

### Seguimiento

Debe ser una vista comercial resumida:

- Detalles CRM.
- Responsable editable.
- Contacto principal y alternos.
- Fuente/convenio.
- Sede.
- Actividad reciente real.

El checklist operativo no debe mostrarse aqui. Esa responsabilidad queda en `Tareas`.

### Tareas

Debe ser el unico lugar del checklist operativo y tareas del caso:

- Crear tarea.
- Completar tarea.
- Reabrir tarea.
- Actualizar prioridad, vencimiento y responsable cuando el backend lo permita.

Los pasos operativos como documentacion, cobertura, apto oftalmologo, apto anestesia, listo para agenda y programada viven aqui, no duplicados en seguimiento.

### Notas

Las notas deben persistir contra API real:

- Guardar nota.
- Mostrar autor, fecha y contenido.
- Borrar nota solo si el usuario es autor o admin.
- Usar borrado logico para conservar trazabilidad.
- El textarea debe usar el ancho util del panel.

### Comunicacion

WhatsApp y correo deben dejar de ser acciones simuladas:

- Selector de destinatarios desde telefono/email principal y alternos.
- Permitir agregar contacto alterno desde el panel y guardarlo para futuros usos.
- WhatsApp puede enviar a uno o varios telefonos si el servicio lo soporta; si no, enviar uno por vez.
- Correo debe soportar destinatarios principales y CC si el backend lo permite.
- Cada envio exitoso registra actividad reciente.
- Cada error muestra motivo en el panel.

### Propuestas

La propuesta hardcodeada debe eliminarse. La nueva propuesta debe salir de catalogo real:

- Buscar codigos/procedimientos.
- Buscar paquetes.
- Seleccionar solo items de catalogo.
- Calcular tarifa segun afiliacion/plan del caso.
- Si no hay tarifa para esa afiliacion, permitir elegir explicitamente tarifa `PARTICULAR` con aviso visible.
- Guardar propuesta real como borrador.
- Generar PDF real.
- Enviar por correo o WhatsApp.
- Registrar creacion, PDF y envios en actividad reciente.

### Documentos

Debe listar documentos reales del caso y permitir acciones solo donde exista backend:

- Listar adjuntos/documentos.
- Subir documentos si el endpoint existe.
- Borrar documentos solo si hay soporte real y permisos.

## Prefactura

Prefactura queda como vista operativa del caso quirurgico, no como CRM comercial.

### Caso

Debe mostrar:

- Procedimiento.
- Diagnosticos reales desde `diagnosticos_asignados` por `form_id`.
- Paciente.
- Telefono.
- Direccion.
- Edad/sexo.
- Plan de afiliacion sin codigo ni empresa.

El caso `form_id=275872` debe mostrar el diagnostico `H00 - ORZUELO Y CALACIO`, lateralidad `DERECHO`, cuando exista en `diagnosticos_asignados`.

### Cobertura

Debe usar nombres reales del backend:

- `cod_derivacion`
- `fecha_vigencia`
- `archivo_derivacion_path`

No debe buscar aliases inventados si el backend ya tiene nombres definidos.

### Cirugia

Debe recuperar la informacion util de V2:

- LIO/producto.
- Poder.
- Ojo.
- Incision.
- Observaciones.
- Estado de aptitud anestesia.
- Estado de aptitud oftalmologo.
- Estado actual de preanestesia si existe.

Si no hay checklist preoperatorio real, debe mostrar `Sin checklist preoperatorio registrado` y no fabricar un checklist.

### Agenda

Debe usar fuentes reales:

- `bloqueos_agenda[0]`
- `fecha_programada`

No debe inventar `fecha_cirugia`, `quirofano` u otros campos sin fuente.

### Nota Clinica

Debe mostrar solo datos clinicos reales disponibles.

## Errores y Estados

No debe existir exito falso. Cada accion debe tener estados:

- `loading`
- `success`
- `error`

Si falla WhatsApp, correo, PDF, notas, tareas o propuestas, el panel muestra el motivo. Si falta permiso o endpoint, la accion se oculta o queda deshabilitada con razon clara.

## Redis y Performance

Redis puede ayudar despues de corregir contratos y persistencia. Su uso inicial recomendado es cachear datos de lectura compartidos y poco volatiles:

- catalogos de codigos
- paquetes
- usuarios/responsables
- opciones de aseguradoras

No se debe usar Redis para tapar inconsistencias de datos ni para mantener estado local que deba persistir en base de datos.

## Pruebas

Se deben cubrir:

- Tests PHP para endpoints V3 de CRM principales.
- Tests JS/TS para mappers de datos del panel.
- Tests JS/TS para estados de UI cuando hay error/loading.
- Build frontend.
- Typecheck.
- Caso de diagnostico real `form_id=275872`.

## Criterios de Aceptacion

- El CRM central React puede consumir la capa `/v3/crm/...`.
- Solicitudes V3 consume `/v3/crm/...` para CRM y `/v3/solicitudes/...` para prefactura/quirurgico.
- Notas guardan y se pueden borrar segun permisos.
- Tareas operan contra backend real.
- Seguimiento no duplica checklist operativo.
- WhatsApp y correo usan API real o muestran error real.
- Propuestas no muestran ejemplos hardcodeados.
- PDF y envio de propuesta no son simulados.
- Responsable se puede escoger y guardar.
- Contactos alternos se pueden agregar y reutilizar.
- Prefactura muestra diagnosticos reales por `form_id`.
- Prefactura no inventa campos de cobertura, agenda o cirugia.
