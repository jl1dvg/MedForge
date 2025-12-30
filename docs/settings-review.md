# Revisión general de settings

Este documento resume los ajustes disponibles en la plataforma y propone nuevas variables de configuración para reforzar modularidad, gobernanza y escalabilidad.

## Inventario actual por módulo

| Sección | Propósito | Subgrupos destacados |
| --- | --- | --- |
| General | Datos corporativos utilizados en reportes y comunicaciones. | Perfil de empresa (nombre comercial, razón social, dirección, RUC, contacto). |
| Branding | Personalización visual de la plataforma y PDF. | Recursos gráficos (logos, firma); Colores/temas (colores PDF, tema admin). |
| Correo electrónico | Salida de correo y formatos. | SMTP (motor, host, puerto, credenciales, cifrado); Formato (encabezado/pie HTML, firma, remitente). |
| CRM y Pipeline | Ajustes del tablero Kanban clínico/CRM. | Etapas del pipeline, plantillas WhatsApp por etapa, orden y límite de tarjetas. |
| Exámenes | Kanban de exámenes. | Orden predeterminado y límite por columna. |
| Notificaciones | Canales y resúmenes. | Canales email/SMS; Pusher (credenciales, desktop notifications); Resumen diario. |
| Turneros | Pantalla y audio del turnero unificado. | Pantalla completa; Sonidos/volumen/horario silencioso; Preferencias de TTS. |
| Facturación | Reglas de precios. | Reglas por código, afiliación y edad. |
| Mailbox | Inbox unificado. | Habilitación, fuentes visibles, límite y orden de mensajes, composición de notas. |
| Informes de facturación | Parametrización por aseguradora. | Títulos/rutas, afiliaciones, botones de exportación, etiquetas de scraping, filtros por apellido, grupos adicionales JSON. |
| WhatsApp | Integración Cloud API y funnel. | Credenciales, versión, país por defecto, token webhook, nombre de marca, límites diarios, comportamiento de consentimientos y plantillas. |
| Inteligencia Artificial | Proveedor y alcance clínico. | Proveedor activo; credenciales/endpoint/modelo/token limits; funciones habilitadas en consultas. |
| Localización | Regionalización. | Idioma, zona horaria, formato de fecha/hora, moneda. |
| Verificación de identidad | Política biométrica y escalamiento. | Vigencia, umbrales de rostro/firma/biometría única; escalamiento a tickets; parámetros de firma digital en PDF. |
| CIVE Extension | Parámetros de la extensión clínica. | Cliente API (URLs, credenciales, timeouts/reintentos/cache); OpenAI para extensión; reglas de comprobantes y prompts (no exhaustivo). |

## Brechas y oportunidades

1. **Observabilidad y alertas operativas**
   - Logging: nivel global (info/debug/warning), activación de trazas HTTP salientes, retención de logs.
   - Alertas de errores críticos: correo/lista de distribución y toggle para enviar métricas a un APM (Sentry/New Relic genérico).
2. **Seguridad y acceso**
   - Políticas de sesión: duración, expiración por inactividad y recordatorio de reautenticación para acciones sensibles.
   - MFA opcional para roles administrativos, configurable por módulo.
   - Lista blanca de IP para la consola de administración y límite de intentos de login.
3. **Privacidad y cumplimiento**
   - Configuración de retención de datos (ej. purga de archivos subidos y conversaciones tras N días).
   - Anonimización para ambientes de pruebas (toggle que enmascara datos personales en seeds/exportaciones).
4. **Productividad clínica**
   - Plantillas reutilizables: banco de textos rápidos para consultas, procedimientos y notas de evolución.
   - Preferencias de vista en Historia Clínica (orden de secciones, columnas visibles) persistidas por usuario.
5. **Automatizaciones de comunicación**
   - Ventanas de silencio globales para notificaciones (no solo turnero) con horarios y fechas excepcionales.
   - Configuración de colas de envío para WhatsApp/Email: batch size, intervalo y reintentos antes de marcar como fallido.
6. **IA y gobernanza**
   - Límites por rol para cantidad diaria/mensual de llamadas a IA.
   - Toggle de trazabilidad: guardar prompt y respuesta en tabla de auditoría, con TTL configurable.
   - Selección de modelos alternativos por contexto (ej. resúmenes vs. planes) y fallback automático.
7. **Experiencia de datos maestros**
   - Sincronización de catálogos (procedimientos, insumos, diagnósticos) con frecuencia programable y modo "solo lectura".
   - Control de versiones de plantillas de reporte con opción de previsualización antes de publicar.
8. **Rendimiento y UX**
   - Límite de resultados por página para listados globales (Solicitudes, Exámenes, CRM) y selector de columnas.
   - Preferencias de cacheo para tableros Kanban (TTL y estrategia de invalidación por evento).
9. **Integraciones externas**
   - Parámetros genéricos para SMS/Email transaccional alternativo (proveedor secundario y prioridades de failover).
   - Webhooks salientes configurables por evento (creación de paciente, actualización de estado, facturación emitida), con firma compartida y número de reintentos.
10. **Backups y continuidad**
    - Programación de backups lógicos (frecuencia, retención, ubicación S3/FTP) y alertas de éxito/falla.

## Sugerencias concretas para próximos sprints

| Módulo | Nuevos settings propuestos | Beneficio esperado |
| --- | --- | --- |
| Settings → General | Política de retención de archivos (días) y purga automática de adjuntos antiguos. | Reduce riesgos de cumplimiento y uso de almacenamiento. |
| Settings → Notificaciones | Ventanas de silencio global y lista de destinatarios para alertas críticas. | Evita spam en horarios no laborales y asegura escalamiento correcto. |
| WhatsApp | Límite de mensajes por hora, tamaño máximo de adjuntos y reintentos configurables. | Protege la reputación de la línea y controla costos. |
| CRM/Pipeline | Capacidad de definir SLA por etapa (tiempo máximo en columna) y alarmas asociadas. | Mejora seguimiento y priorización de oportunidades. |
| IA | Límites diarios por rol/usuario y registro de prompts/respuestas con TTL. | Control de costos y trazabilidad clínica. |
| Identity Verification | Política de revalidación temprana (ej. re-chequear a los 300 días) y webhook de eventos. | Garantiza vigencia y facilita integraciones downstream. |
| Mailbox | Filtros predefinidos por fuente y reglas automáticas de archivado. | Mantiene el inbox limpio y enfocado. |
| CIVE Extension | Selección de entorno (sandbox/producción) y headers personalizados por tenant. | Facilita pruebas controladas y multicliente. |
| Turnero | Perfil de pantalla por sede (logo, colores, layout) y cadencia de refresco de lista. | Mejora experiencia multisitio y rendimiento. |
| Billing/Informes | Tabla de mapping de códigos internos→externos y tolerancia de redondeo en exportes. | Disminuye errores de conciliación con aseguradoras. |

> Estas propuestas se basan en el inventario actual de `SettingsHelper::definitions()` y en patrones de uso encontrados en módulos de CRM, Billing, WhatsApp, IA, IdentityVerification y CIVE Extension. Pueden implementarse de forma incremental priorizando seguridad, trazabilidad y gobernanza de datos.
