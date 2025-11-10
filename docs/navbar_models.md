# Modelo de navegación seleccionado (Modelo B)

El navbar se reorganizó siguiendo el enfoque por roles internos del Modelo B. Cada bloque agrupa herramientas afines a un equipo o función específica dentro de la clínica.

## Secciones principales

1. **Inicio**
   - Vista general del sistema (`/dashboard`).

2. **Marketing y captación**
   - CRM (`/crm`).
   - Flujo de pacientes (`/views/pacientes/flujo/flujo.php`).
   - Campañas y Leads (`/leads`).
   - Automatizaciones de WhatsApp (`/whatsapp/autoresponder`, permisos administrativos).
   - Plantillas de WhatsApp (`/whatsapp/templates`, permisos administrativos).

3. **Atención al paciente**
   - Lista de Pacientes (`/pacientes`).
   - Certificación biométrica (`/pacientes/certificaciones`, permisos de verificación de pacientes).
   - Chat de WhatsApp para seguimiento directo (`/whatsapp/chat`, permisos administrativos).

4. **Operaciones clínicas**
   - Solicitudes (Kanban) (`/solicitudes`).
   - Protocolos realizados (`/cirugias`).
   - Planificador de IPL (`/ipl`).
   - Plantillas de protocolos (`/protocolos`).

5. **Inventario y logística**
   - Lista de insumos (`/insumos`).
   - Lista de medicamentos (`/insumos/medicamentos`).

6. **Finanzas y análisis**
   - Facturación por afiliación: ISSPOL, ISSFA, IESS, Particulares, No Facturado (`/informes/*`, `/billing/no-facturados`).
   - Reportes de flujo de pacientes (`/views/reportes/estadistica_flujo.php`).

7. **Administración y TI** (según permisos)
   - Usuarios (`/usuarios`).
   - Roles (`/roles`).
   - Ajustes (`/settings`).
   - Cron Manager (`/cron-manager`).
   - Codificación (`/views/codes/index.php`).

## Ventajas operativas

- **Claridad por responsabilidades**: cada rol identifica rápidamente su espacio de trabajo.
- **Permisos alineados**: los accesos sensibles (WhatsApp, ajustes, cron) siguen condicionados a permisos administrativos.
- **Escalabilidad**: permite incorporar nuevas herramientas por área sin reordenar por completo el menú.
