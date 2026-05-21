# Spec: Rediseño v2-form — Perfil completo con Tabs

**Fecha:** 2026-05-21
**Estado:** Aprobado — listo para plan de implementación
**Vista afectada:** `resources/views/usuarios/v2-form.blade.php`
**Vista a eliminar:** `resources/views/usuarios/v2-edit.blade.php` (huérfana — nunca renderizada)
**Controlador:** `app/Modules/Usuarios/Http/Controllers/UsuariosUiController.php`
**JS:** `resources/js/v2/user-edit.js`
**CSS:** `resources/css/usuarios.css`

---

## Problema

La vista `v2-form.blade.php` (usada tanto para crear como editar usuarios) presenta:

1. **Todos los grupos de permisos abiertos** — scroll interminable de checkboxes, sin acordeón
2. **Sin distinción heredado vs. directo** — todos los checkboxes son editables; el admin no sabe qué viene del rol
3. **`confirm()` nativo** en el botón Eliminar — no estilizable, no accesible
4. **Layout plano** — datos personales, rol, permisos y uploads en un solo scroll largo sin jerarquía visual
5. **`v2-edit.blade.php` huérfana** — existe en disco pero el controlador nunca la renderiza; genera confusión

---

## Decisiones de diseño

| Decisión | Elección | Razón |
|---|---|---|
| Patrón de layout | Tabs (Perfil / Acceso / Documentos) | Separa contextos heterogéneos; mismo patrón del drawer; evita scroll interminable |
| Header | Avatar grande + nombre + handle + especialidad + badge estado | Identidad clara sin tener que leer los campos del formulario |
| Tab Perfil | Identidad en grid 3 col + foto de perfil con drop zone + switches de estado | Agrupa datos personales y estado de la cuenta |
| Tab Acceso | Rol select + plantilla rápida + accordion de permisos con herencia visible | Consistente con el drawer; progressive disclosure |
| Tab Documentos | Drop zones para sello, firma digital, sello+firma combinados | Documentos médicos aislados en su propio contexto |
| Footer | Persistente en todos los tabs: [Eliminar] + [Guardar cambios] | Siempre accesible sin importar qué tab esté activo |
| Herencia de rol | Checkboxes inherited = disabled + tag "rol"; preview instantáneo al cambiar rol | Mismo comportamiento que el drawer (ya validado) |
| confirm() | Reemplazado por modal de confirmación (mismo que el índice) | Consistente con el módulo; estilizable y accesible |
| v2-edit.blade.php | Eliminar | Huérfana confirmada — ninguna ruta la renderiza |
| CSS | Extender `resources/css/usuarios.css` con estilos de tabs y form | Sin CSS inline en el template |

---

## Estructura de la página

### Header (persistente, fuera de los tabs)

```
┌────────────────────────────────────────────────────────────────┐
│  ← Volver a Usuarios                   breadcrumb: / Usuarios  │
├────────────────────────────────────────────────────────────────┤
│  [Avatar 48px]  Nombre completo                                │
│                 handle · [badge Aprobado/Pendiente] · Especialidad │
└────────────────────────────────────────────────────────────────┘
```

- Avatar: inicial o foto, border-radius 12px, 48×48px, fondo cyan
- Badge estado: verde "✓ Aprobado" / ámbar "Pendiente"
- **Modo edit**: avatar con inicial del nombre o foto real, nombre completo, handle, especialidad, badge
- **Modo create**: avatar con "+" o letra "N", texto "Nuevo usuario", sin handle ni badge (aún no existe)

### Tab bar

```
[ Perfil ]  [ Acceso ]  [ Documentos ]
```

- Tab activo: subrayado cyan + texto cyan + font-weight 600
- Comportamiento client-side con JS (sin recarga de página)

---

## Tab: Perfil

### Sección Identidad (grid 3 columnas)

| Campo | Comportamiento |
|---|---|
| Usuario | Input disabled (no editable, readonly visual) |
| Correo electrónico | Input text editable |
| Especialidad | Select editable |
| Nombre | Input text editable |
| Primer apellido | Input text editable |
| Segundo apellido | Input text editable (opcional) |
| Nombre medio | Input text editable (opcional) |

En modo **create**: además muestra campo Contraseña y Confirmar contraseña.

### Sección Foto de perfil

- Preview circular del avatar actual (si existe)
- Drop zone con `data-upload-drop-zone="profile_photo_file"`
- Checkbox "Eliminar foto actual" (visible solo si hay foto)
- Misma lógica de upload que el formulario actual

### Sección Estado de la cuenta

Switches Bootstrap (toggle checkboxes):
- **Cuenta aprobada** (`is_approved`)
- **Suscripción activa** (`is_subscribed`)
- **Notificaciones WhatsApp** (`whatsapp_notify`)

---

## Tab: Acceso

### Rol asignado

```
[Select de rol — mismo que hoy]
```

- Chip informativo: "Al cambiar el rol, los permisos heredados se actualizan al instante"
- Al cambiar: JS actualiza qué checkboxes están disabled (inherited preview)

### Plantilla rápida (solo si `$permissionProfiles` no está vacío)

```
[Select de plantillas]  [Aplicar]
```

- Aplica solo sobre permisos directos (no afecta los disabled)
- Mismo comportamiento que hoy en `user-edit.js`

### Leyenda de permisos

```
● directo    ○ heredado del rol (no editable)
```

### Acordeón de grupos de permisos

- Un grupo por fila, colapsado por defecto (mismo patrón que el drawer)
- Dentro de cada grupo: grid 2 columnas de checkboxes
  - **Permiso directo**: checkbox activo, editable
  - **Permiso heredado del rol**: checkbox checked + disabled + tag "rol" en color muted
- Al cambiar el rol: JS re-evalúa cuáles son heredados sin guardar (preview instantáneo)

### Datos para JS (window.__USUARIOS_V2_EDIT__)

```js
window.__USUARIOS_V2_EDIT__ = {
    permissionProfiles: { ... },
    rolesWithPermissions: { "1": ["perm.key", ...], ... },  // NUEVO
    currentRoleId: "3",   // NUEVO — para inicializar inherited al cargar
    directPermissions: ["perm.key", ...],  // NUEVO — para distinguir en el estado inicial
};
```

---

## Tab: Documentos

Tres zonas de carga independientes:

| Documento | Campo | Drop zone |
|---|---|---|
| Sello | `firma_file` | Con preview si existe `firma_url`; checkbox "Eliminar sello actual" |
| Firma digital | `signature_file` | Con preview si existe `signature_url`; checkbox "Eliminar firma digital actual" |
| Sello + Firma combinados | `seal_signature_file` | Con preview si existe `seal_signature_url`; checkbox "Eliminar imagen combinada actual" |

- Grid 2 columnas en desktop, 1 columna en móvil
- Misma lógica de drag-and-drop que el formulario actual

---

## Footer (persistente en todos los tabs)

```
[⚠ Eliminar usuario]                    [Guardar cambios]
```

- **Eliminar usuario**: visible solo si `$canDelete === true`. Outline rojo. Abre modal de confirmación (igual al del índice).
- **Guardar cambios**: botón primary. Envía el formulario completo (todos los tabs).

El formulario envuelve todos los tabs — un solo `<form method="POST">` con todos los inputs. Los tabs solo controlan visibilidad, no fragmentan el form.

---

## Modal de confirmación de eliminación

Idéntico al del índice:

```
┌─────────────────────────────────┐
│  ⚠ Confirmar eliminación        │
│                                 │
│  ¿Eliminar a jl1dvg?            │
│  Esta acción no se puede deshacer│
│                                 │
│          [Cancelar]  [Eliminar] │
└─────────────────────────────────┘
```

- `role="dialog"` con `aria-labelledby`
- POST a `/usuarios/{id}/delete` al confirmar
- Cierra con Escape o clic en Cancelar

---

## Accesibilidad

- Tab activo tiene `aria-selected="true"` y `role="tab"`
- Tab panels tienen `role="tabpanel"` con `aria-labelledby`
- Inputs con `<label>` asociado (no placeholder-only)
- Checkboxes disabled tienen `aria-label` que indica "heredado del rol X"
- Modal con `role="dialog"`, `aria-modal="true"`, foco al abrirse en botón Cancelar
- Cierre de modal con Escape

---

## Archivos afectados

| Archivo | Acción | Descripción |
|---|---|---|
| `resources/views/usuarios/v2-form.blade.php` | Reescribir | Nuevo layout con tabs, header, footer persistente |
| `resources/views/usuarios/v2-edit.blade.php` | Eliminar | Vista huérfana — ninguna ruta la renderiza |
| `resources/js/v2/user-edit.js` | Modificar | Agregar lógica de inherited permissions + tab switching + delete modal |
| `resources/css/usuarios.css` | Extender | Estilos de tabs, user-header, form sections, upload zones |
| `app/Modules/Usuarios/Http/Controllers/UsuariosUiController.php` | Modificar | `renderForm()` agrega `rolesWithPermissions` al payload |

---

## Lo que NO cambia

- Lógica de servidor: validaciones, uploads, store/update — sin cambios
- Rutas — sin cambios
- Payload POST enviado al guardar — mismo que hoy
- Drop zones de upload — misma lógica JS (`user-edit.js` ya la maneja)
- Vista `v2-index.blade.php` — ya rediseñada, sin cambios

---

## Fuera de alcance

- Tab "Actividad" (historial de cambios de rol, últimos accesos)
- Auditoría de cambios de permisos
- Vista móvil especial (tabs se mantienen como tabs, no se convierten en accordion)
