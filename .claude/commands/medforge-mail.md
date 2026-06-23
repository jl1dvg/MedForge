# medforge-mail — Email corporativo de actualización MedForge

Cuando el usuario invoca `/medforge-mail`, genera un email HTML con la identidad visual de MedForge y lo guarda como borrador en Gmail usando el MCP de Gmail.

## Flujo

1. Si no hay argumentos, pregunta al usuario:
   - **Versión** (ej. `v2.14.0`)
   - **Fecha** (ej. `4 de junio de 2026`) — si no la da, usa la fecha de hoy
   - **Destinatarios** (ej. `equipo@clinica.com`) — puede ser uno o varios emails
   - **Cambios** — pide que los liste por tipo:
     - ✨ Nuevas funcionalidades
     - 🔧 Mejoras
     - 🐛 Correcciones
   - **Introducción opcional** — un párrafo libre para el cuerpo del email

2. Con esa info, genera el HTML completo usando la plantilla de abajo.

3. Llama a `mcp__2c7f3015-3b5e-42a4-855a-3799a28d9896__create_draft` con:
   - `to`: los destinatarios indicados
   - `subject`: `MedForge {version} — Actualización de plataforma`
   - `htmlBody`: el HTML generado
   - `body`: versión texto plano resumida

4. Confirma al usuario que el borrador fue creado con enlace/ID.

---

## Identidad de marca MedForge

**Colores:**
- Navy oscuro (fondo header/footer): `#1B1464`
- Navy texto: `#1B1464`
- Cyan: `#00CCFF`
- Magenta/purple: `#CC00FF`
- Gradiente del logo: `from #CC00FF (bottom-left) to #00CCFF (top-right)`
- Body background: `#F0F2F8`
- Card background: `#FFFFFF`
- Texto secundario: `#555566`
- Texto muted: `#888899`
- Borde suave: `#ECEEF5`

**Tipografía:** Roboto (Google Fonts), fallback Arial, sans-serif

**Logo (SVG inline — siempre embeber en el email, nunca URL externa):**
```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 80" width="52" height="52" style="display:block;">
  <defs>
    <linearGradient id="mfg" x1="0" y1="1" x2="1" y2="0">
      <stop offset="0%" stop-color="#CC00FF"/>
      <stop offset="100%" stop-color="#00CCFF"/>
    </linearGradient>
  </defs>
  <circle cx="40" cy="40" r="40" fill="url(#mfg)"/>
  <polygon points="47,8 26,44 39,44 33,72 54,36 41,36" fill="#1B1464"/>
</svg>
```

---

## Plantilla HTML base

Adapta el contenido (versión, fecha, párrafo intro, secciones de cambios) pero mantén siempre la estructura y estilos exactos.

```html
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>MedForge — Actualización {{VERSION}}</title>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body style="margin:0;padding:0;background:#F0F2F8;font-family:'Roboto',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#F0F2F8;padding:40px 16px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

  <!-- ── HEADER ── -->
  <tr>
    <td style="background:#1B1464;border-radius:14px 14px 0 0;padding:32px 40px 28px;">
      <table cellpadding="0" cellspacing="0"><tr>
        <td style="vertical-align:middle;padding-right:14px;">
          <!-- Logo SVG inline -->
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 80" width="52" height="52" style="display:block;">
            <defs>
              <linearGradient id="mfg" x1="0" y1="1" x2="1" y2="0">
                <stop offset="0%" stop-color="#CC00FF"/>
                <stop offset="100%" stop-color="#00CCFF"/>
              </linearGradient>
            </defs>
            <circle cx="40" cy="40" r="40" fill="url(#mfg)"/>
            <polygon points="47,8 26,44 39,44 33,72 54,36 41,36" fill="#1B1464"/>
          </svg>
        </td>
        <td style="vertical-align:middle;">
          <div style="font-family:'Roboto',Arial,sans-serif;font-size:26px;font-weight:700;color:#FFFFFF;letter-spacing:-0.3px;line-height:1;">MedForge</div>
          <div style="font-size:11px;color:#00CCFF;font-weight:500;margin-top:3px;letter-spacing:0.3px;">by Medforge SAS</div>
        </td>
      </tr></table>

      <!-- Gradient divider -->
      <div style="height:3px;background:linear-gradient(90deg,#CC00FF 0%,#00CCFF 100%);border-radius:2px;margin:24px 0 20px;"></div>

      <div style="font-family:'Roboto',Arial,sans-serif;font-size:20px;font-weight:700;color:#FFFFFF;margin:0 0 6px;">Actualización de plataforma</div>
      <div style="font-size:13px;color:#8899CC;font-weight:400;">{{VERSION}} &nbsp;·&nbsp; {{FECHA}}</div>
    </td>
  </tr>

  <!-- ── BODY ── -->
  <tr>
    <td style="background:#FFFFFF;padding:36px 40px;">

      <!-- Saludo e intro -->
      <p style="font-size:15px;font-weight:600;color:#1B1464;margin:0 0 10px;">Hola equipo,</p>
      <p style="font-size:14px;color:#555566;line-height:1.75;margin:0 0 32px;">
        {{INTRO_PARRAFO}}
      </p>

      <!-- ── SECCIÓN: Nuevas funcionalidades (solo si hay items) ── -->
      <!-- EJEMPLO — reemplazar con los items reales: -->
      <table cellpadding="0" cellspacing="0" style="width:100%;margin-bottom:28px;">
        <tr>
          <td style="padding-bottom:12px;">
            <span style="display:inline-block;background:#F0E8FF;color:#7B00CC;font-size:11px;font-weight:700;letter-spacing:0.8px;padding:4px 12px;border-radius:20px;text-transform:uppercase;">✨ Nuevo</span>
          </td>
        </tr>
        <!-- Un item por fila -->
        <tr>
          <td style="padding:8px 0 8px 4px;border-bottom:1px solid #F0F2F8;">
            <table cellpadding="0" cellspacing="0"><tr>
              <td style="vertical-align:top;padding-right:10px;padding-top:2px;">
                <div style="width:7px;height:7px;border-radius:50%;background:linear-gradient(135deg,#CC00FF,#00CCFF);margin-top:4px;"></div>
              </td>
              <td>
                <div style="font-size:14px;font-weight:600;color:#1B1464;margin-bottom:2px;">Título del cambio</div>
                <div style="font-size:13px;color:#666677;line-height:1.5;">Descripción breve de qué hace y por qué es útil para el equipo.</div>
              </td>
            </tr></table>
          </td>
        </tr>
      </table>

      <!-- ── SECCIÓN: Mejoras (solo si hay items) ── -->
      <table cellpadding="0" cellspacing="0" style="width:100%;margin-bottom:28px;">
        <tr>
          <td style="padding-bottom:12px;">
            <span style="display:inline-block;background:#E8F4FF;color:#0066BB;font-size:11px;font-weight:700;letter-spacing:0.8px;padding:4px 12px;border-radius:20px;text-transform:uppercase;">🔧 Mejoras</span>
          </td>
        </tr>
        <tr>
          <td style="padding:8px 0 8px 4px;border-bottom:1px solid #F0F2F8;">
            <table cellpadding="0" cellspacing="0"><tr>
              <td style="vertical-align:top;padding-right:10px;padding-top:2px;">
                <div style="width:7px;height:7px;border-radius:50%;background:#0099EE;margin-top:4px;"></div>
              </td>
              <td>
                <div style="font-size:14px;font-weight:600;color:#1B1464;margin-bottom:2px;">Título de la mejora</div>
                <div style="font-size:13px;color:#666677;line-height:1.5;">Descripción de qué mejoró.</div>
              </td>
            </tr></table>
          </td>
        </tr>
      </table>

      <!-- ── SECCIÓN: Correcciones (solo si hay items) ── -->
      <table cellpadding="0" cellspacing="0" style="width:100%;margin-bottom:32px;">
        <tr>
          <td style="padding-bottom:12px;">
            <span style="display:inline-block;background:#FFF0F0;color:#CC2200;font-size:11px;font-weight:700;letter-spacing:0.8px;padding:4px 12px;border-radius:20px;text-transform:uppercase;">🐛 Correcciones</span>
          </td>
        </tr>
        <tr>
          <td style="padding:8px 0 8px 4px;border-bottom:1px solid #F0F2F8;">
            <table cellpadding="0" cellspacing="0"><tr>
              <td style="vertical-align:top;padding-right:10px;padding-top:2px;">
                <div style="width:7px;height:7px;border-radius:50%;background:#FF4444;margin-top:4px;"></div>
              </td>
              <td>
                <div style="font-size:14px;font-weight:600;color:#1B1464;margin-bottom:2px;">Título del bug fix</div>
                <div style="font-size:13px;color:#666677;line-height:1.5;">Qué estaba fallando y cómo quedó.</div>
              </td>
            </tr></table>
          </td>
        </tr>
      </table>

      <!-- Cierre -->
      <p style="font-size:13px;color:#888899;line-height:1.7;margin:0;padding-top:20px;border-top:1px solid #ECEEF5;">
        ¿Tienes preguntas o encontraste algo que no funciona bien? Escríbenos a
        <a href="mailto:soporte@medforge.app" style="color:#7B00CC;text-decoration:none;font-weight:500;">soporte@medforge.app</a>
        o responde este correo.
      </p>
    </td>
  </tr>

  <!-- ── FOOTER ── -->
  <tr>
    <td style="background:#1B1464;border-radius:0 0 14px 14px;padding:22px 40px;text-align:center;">
      <p style="font-family:'Roboto',Arial,sans-serif;font-size:12px;color:#8899CC;margin:0 0 4px;">MedForge &nbsp;·&nbsp; by Medforge SAS</p>
      <a href="mailto:soporte@medforge.app" style="font-size:12px;color:#00CCFF;text-decoration:none;">soporte@medforge.app</a>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>
```

---

## Reglas al generar el HTML

1. **No incluyas secciones vacías** — si no hay "Correcciones", omite ese bloque completo.
2. **Cada item tiene título + descripción** — el título en negrita navy, la descripción en gris 13px.
3. **Reemplaza todos los `{{PLACEHOLDER}}`** antes de enviar al MCP.
4. **`{{INTRO_PARRAFO}}**` — si el usuario no da uno, genera uno natural en español que mencione la versión y que los cambios están detallados abajo.
5. El `linearGradient id="mfg"` — si el email tiene múltiples SVGs en el mismo documento, asegúrate que el ID sea único (`mfg1`, `mfg2`, etc.).
6. **Asunto del email**: `MedForge {{VERSION}} — Actualización de plataforma`
7. **Cuerpo texto plano** (campo `body`): genera un resumen legible sin HTML, con los títulos de cada cambio en viñetas.
