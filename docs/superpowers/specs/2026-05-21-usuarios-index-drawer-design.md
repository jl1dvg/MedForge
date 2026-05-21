# Spec: Rediseño Módulo Usuarios — Tabla + Drawer

**Fecha:** 2026-05-21
**Estado:** Aprobado — listo para plan de implementación
**Vistas afectadas:** `resources/views/usuarios/v2-index.blade.php`, `resources/views/usuarios/v2-edit.blade.php`
**Controlador:** `app/Modules/Usuarios/Http/Controllers/UsuariosUiController.php`

---

## Problema

La vista `/usuarios` tiene 9 columnas en la tabla (Foto, Usuario, Nombre, Correo, Rol, Permisos, Estado, Perfil, Acciones), tres cards de resumen de privilegios en acordeón, y mezcla de conceptos en las columnas de Permisos y Estado. El resultado es una página densa, ilegible en pantallas medianas, e imposible en móvil.

La vista `/usuarios/{id}/edit` muestra todos los grupos de permisos abiertos a la vez (scroll interminable de checkboxes), no distingue permisos heredados del rol vs. permisos directos, y usa `confirm()` nativo para confirmar eliminación.

Ambas vistas tienen CSS inline en `<style>` dentro del template.

---

## Decisiones de diseño

| Decisión | Elección | Razón |
|---|---|---|
| Patrón master-detail | Tabla + Drawer lateral | Mantiene el contexto de la lista mientras se edita |
| Columnas de la tabla | 5: Avatar, Nombre/handle, Especialidad, Rol, Estado | Solo info necesaria para identificar y filtrar |
| Resumen de privilegios (accordion) | Eliminado | Redundante con la información del drawer |
| Contenido del drawer | Rol + permisos directos + link a perfil completo | Quick edit para lo más frecuente; perfil completo en su propia página |
| Grupos de permisos | Acordeón colapsado por defecto | Progressive disclosure — evita el scroll interminable |
| Herencia de rol | Visible en checkboxes (tag "rol", disabled) | El admin entiende qué viene del rol y qué es directo |
| Eliminación | Movida al drawer, con modal de confirmación propio | Elimina el riesgo de clic accidental desde la tabla |
| confirm() | Reemplazado por modal Blade/Alpine o JS propio | No bloqueable, estilizable, accesible |
| CSS inline | Movido a `resources/css/usuarios.css` compilado por Vite | Separación de responsabilidades, evita duplicación |

---

## Vista `/usuarios` — nueva estructura

### Topbar
```
[Título: Usuarios]  [breadcrumb: Inicio / Usuarios]    [Administrar roles] [+ Nuevo usuario]
```

### Filtros (una fila, sin card separada)
- **Buscar** — input text, filtra en tiempo real sobre nombre, username, email, especialidad
- **Especialidad** — select con las especialidades del catálogo
- **Rol** — select con los roles existentes
- **Estado** — select: Todos / Aprobado / Pendiente
- **Contador** — "N usuarios" actualizado en tiempo real
- **Limpiar** — reset de todos los filtros

### Tabla
Columnas: Avatar · Nombre+handle · Especialidad · Rol · Estado · [botón Editar]

- Avatar: inicial o foto, border-radius: 8px, 32×32px
- Nombre: `font-weight: 600`, handle debajo en color muted
- Especialidad: dot de color por categoría (médico vs. administrativo) + texto
- Rol: badge de color según rol
- Estado: badge verde "✓ Aprobado" o ámbar "⏳ Pendiente"
- Botón "Editar": visible solo en hover de la fila (no satura visualmente)
- Fila activa (drawer abierto): fondo `#e0f2fe` + borde izquierdo `3px solid #0891b2`
- Click en cualquier celda de la fila abre el drawer del usuario

### Ordenación
- Columnas Nombre y Especialidad tienen ordenación cliente-side
- Ícono SVG (↑ / ↓ / ⇅), no carácter Unicode

### Sin accordion de privilegios
El bloque de Superusuarios / Acceso administrativo / Accesos totales se elimina completamente.

---

## Drawer lateral — tab "Acceso"

El drawer ocupa un ancho fijo (~320px) a la derecha del área de contenido. No es un overlay — empuja la tabla hacia la izquierda.

### Header del drawer
- Avatar + Nombre completo + handle + especialidad
- Botón ✕ (cierra drawer, devuelve foco a la fila)
- Cierre con tecla `Escape`

### Tabs
- **Acceso** (activo por defecto) — rol + permisos
- **Actividad** — reservado para historial (implementación futura, tab visible pero con estado vacío o "próximamente")

### Sección: Rol asignado
- Select de rol (los mismos que hoy)
- Al cambiar el rol, los permisos heredados se actualizan visualmente sin guardar (preview instantáneo)

### Sección: Permisos directos
- Leyenda pequeña: punto cyan = directo, punto cyan outline = heredado del rol
- Un acordeón por grupo de permisos (Solicitudes, Exámenes, Imagenología, Configuración, etc.)
- Todos los grupos colapsados por defecto; el usuario expande los que necesita
- Dentro de cada grupo: grid 2 columnas de checkboxes
  - Permiso directo: checkbox activo, editable
  - Permiso heredado del rol: checkbox checked + disabled + tag pequeño "rol" en color muted
- Plantilla rápida de permisos: selector dentro del drawer (reemplaza el bloque actual fuera del acordeón), con botón "Aplicar" que pre-selecciona los checkboxes sin guardar

### Link a perfil completo
```
[ Editar perfil completo  ↗ ]
```
Link a `/usuarios/{id}/form` (la vista `v2-form.blade.php`). Se abre en la misma pestaña.

### Footer del drawer
- Botón primario "Guardar cambios" (POST a `/usuarios/{id}` — mismo endpoint actual)
- Botón secundario "Eliminar" (abre modal de confirmación propio)

### Modal de confirmación de eliminación
- Reemplaza `confirm()` nativo
- Muestra: "¿Eliminar a {username}? Esta acción no se puede deshacer."
- Botones: "Cancelar" y "Eliminar" (rojo)
- POST a `/usuarios/{id}/delete` al confirmar

---

## Vista `/usuarios/{id}/edit` — estado post-rediseño

Con el drawer, la URL `/usuarios/{id}/edit` sigue existiendo para acceso directo (ej. bookmarks, links desde otras vistas). Su contenido se simplifica o se unifica con el drawer:

**Opción preferida:** La vista `v2-edit.blade.php` renderiza el mismo contenido que el drawer pero en página completa, sin la tabla de fondo. Mismo formulario, mismos acordeones, mismo link a perfil completo. Evita duplicar lógica de permisos.

---

## Accesibilidad (ui-ux-pro-max — prioridad 1)

- Focus se mueve al drawer al abrirse (`focus-management`)
- Drawer cierra con `Escape` (`escape-routes`)
- Checkboxes heredados tienen `disabled` semántico + `aria-label` que indica "heredado del rol X"
- Modal de eliminación usa `role="dialog"` con `aria-labelledby`
- Sort columns tienen `aria-sort` en `<th>`
- Botón "Editar" en hover mantiene `cursor: pointer` y es alcanzable por teclado (`Tab`)

---

## CSS / Assets

- Eliminar los bloques `<style>` inline de `v2-index.blade.php`
- Crear `resources/css/usuarios.css` (o `resources/css/modules/usuarios.css`) con los estilos de tabla, filtros, drawer, badges
- Compilar vía Vite (`vite.config.js` ya incluye el entry point de CSS)
- JS del drawer: `resources/js/v2/usuarios-drawer.js` — maneja open/close, focus trap, Escape, preview de rol

---

## Lo que NO cambia en esta iteración

- Lógica del servidor (`UsuariosUiController`) — sin cambios de backend
- Rutas — sin cambios
- Vista `v2-form.blade.php` (formulario completo de perfil) — sin cambios
- Vista `roles/` — sin cambios
- Datos que se envían al guardar — mismo payload que hoy

---

## Fuera de alcance (iteración futura)

- Tab "Actividad" en el drawer (historial de último login, cambios de rol)
- Paginación server-side o lazy loading para +50 usuarios
- Dark mode
- Vista móvil del drawer (convertir a bottom sheet en pantallas < 768px)
