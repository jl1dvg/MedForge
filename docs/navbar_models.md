# Modelo de navegación seleccionado

La navegación quedó definida como `header utilitario + sidebar por dominios operativos + subnavegación dentro de cada módulo`. El objetivo es evitar que el header compita con el menú principal y reducir el número de accesos primarios visibles al mismo tiempo.

## Secciones principales

1. **Inicio**
   - Vista general del sistema (`/dashboard`).

2. **Comercial**
   - CRM (`/crm`).
   - Flujo de pacientes (`/pacientes/flujo`).
   - Campañas y Leads (`/leads`).
   - Automatizaciones de WhatsApp (`/whatsapp/autoresponder`, permisos administrativos).
   - Plantillas de WhatsApp (`/whatsapp/templates`, permisos administrativos).

3. **Operación diaria**
   - Agenda (`/agenda`).
   - Lista de Pacientes (`/pacientes`).
   - Derivaciones (`/derivaciones`).
   - Agendamiento (`/turnoAgenda/agenda-doctor/index`).
   - Certificación biométrica (`/pacientes/certificaciones`, permisos de verificación de pacientes).
   - Chat de WhatsApp para seguimiento directo (`/whatsapp/chat`, permisos administrativos).
   - Mailbox (`/mailbox`, según permisos).

4. **Clínica**
   - Solicitudes (Kanban) (`/solicitudes`).
   - Protocolos realizados (`/cirugias`).
   - Dashboard quirúrgico (`/cirugias/dashboard`, según permisos).
   - Planificador de IPL (`/ipl`).
   - Exámenes (`/examenes`).
   - Exámenes realizados (`/imagenes/examenes-realizados`).
   - Dashboard imágenes (`/imagenes/dashboard`).
   - Plantillas de protocolos (`/protocolos`).

5. **Inventario**
   - Lista de insumos (`/insumos`).
   - Lista de medicamentos (`/insumos/medicamentos`).
   - Catálogo de lentes (`/insumos/lentes`).
   - Dashboard farmacia (`/farmacia`).

6. **Finanzas**
   - Facturación por afiliación: ISSPOL, ISSFA, IESS, Particulares, No Facturado (`/informes/*`, `/billing/no-facturados`).
   - Dashboard billing (`/billing/dashboard`).
   - Reportes de flujo de pacientes (`/views/reportes/estadistica_flujo.php`).

7. **Administración** (según permisos)
   - Doctores (`/doctores`).
   - Usuarios (`/usuarios`).
   - Roles (`/roles`).
   - Ajustes (`/settings`).
   - Plantillas de correo (`/mail-templates/cobertura`).
   - Cron Manager (`/cron-manager`).
   - Catálogo de códigos (`/codes`).
   - Constructor de paquetes (`/codes/packages`).

## Header utilitario

- Búsqueda global.
- Accesos rápidos a módulos críticos.
- Notificaciones reales.
- Menú de usuario.

## Ventajas operativas

- **Claridad por responsabilidades**: cada rol identifica rápidamente su espacio de trabajo.
- **Menos ruido en primer nivel**: agenda, doctores e imágenes dejan de vivir como bloques sueltos.
- **Permisos alineados**: los accesos sensibles siguen condicionados a permisos administrativos.
- **Escalabilidad**: la navegación puede crecer desde una configuración central sin volver a endurecer el partial.
