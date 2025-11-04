# Pruebas manuales: control de acceso a ajustes

1. **Preparar usuarios y roles**
   - Crear un rol "Administrador de configuración" en `/roles/create` y asignarle solo el permiso `settings.manage`.
   - Crear un usuario de prueba y asociarlo al nuevo rol sin permisos adicionales.
2. **Verificar acceso permitido**
   - Iniciar sesión con el usuario de prueba.
   - Confirmar que el menú Administración muestra el enlace "Ajustes" y que `/settings` carga el formulario.
   - Guardar cambios en cualquier sección y comprobar el mensaje de éxito.
3. **Verificar acceso denegado**
   - Editar el usuario y retirar el rol o el permiso `settings.manage`.
   - Iniciar sesión nuevamente y confirmar que el enlace "Ajustes" desaparece y acceder a `/settings` devuelve la página de error 403.
4. **Compatibilidad con superusuario**
   - Asignar el permiso `superuser` o `administrativo` a un usuario existente.
   - Validar que puede acceder y guardar ajustes sin restricciones.
