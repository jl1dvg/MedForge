# Dependencias entre los módulos de WhatsApp y Autoresponder

El flujo de autorespuesta ahora se divide entre dos módulos coordinados:

- `modules/Autoresponder` contiene los controladores de UI (páginas de Flowmaker y edición de flujo), los repositorios del flujo (`AutoresponderFlowRepository`, `AutoresponderSessionRepository`) y los servicios de escenario. Estos componentes leen y persisten la definición del flujo usando los servicios compartidos de `Modules\WhatsApp\Support`.
- `modules/WhatsApp` queda enfocado en transporte (webhooks entrantes, mensajería y manejo de plantillas) y consume los repositorios/servicios del submódulo de Autoresponder para resolver y ejecutar los flujos publicados.

Para deployments coordinados:

1. Desplegar primero los cambios en `modules/Autoresponder` para asegurar que los repositorios y controladores estén disponibles para `/whatsapp/flowmaker` y `/whatsapp/api/flowmaker/publish`.
2. Desplegar inmediatamente después `modules/WhatsApp` para que el webhook y el transporte apunten a las nuevas rutas/clases del submódulo.
3. Mantener sincronizados los archivos JS/CSS públicos (`public/js/pages/whatsapp-*`, `public/css/pages/whatsapp-*`) porque ambos módulos los utilizan para renderizar y publicar flujos.

Esto evita discrepancias entre la interfaz de Flowmaker y la ejecución en el webhook de WhatsApp.
