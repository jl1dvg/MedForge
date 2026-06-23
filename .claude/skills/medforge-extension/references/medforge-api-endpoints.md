# MedForge API — Endpoints para la Extensión Chrome

Base URL: `https://medforge.cive.ec/api`  
Auth: `Authorization: Bearer {sanctum_token}`  
Content-Type: `application/json`

---

## Autenticación

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| POST | `/extension/auth/login` | Login desde popup de extensión → retorna token |
| POST | `/extension/auth/logout` | Invalida token actual |
| GET  | `/extension/auth/verify` | Verifica si token sigue válido |

```json
// POST /extension/auth/login — body
{ "email": "user@cive.ec", "password": "..." }

// Respuesta
{ "token": "sanctum_token", "user": { "id": 1, "name": "Dr. De Vera" } }
```

---

## Ingestión de datos desde SigCenter

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| POST | `/extension/ingest/patient` | Envía datos de paciente leídos de SigCenter |
| POST | `/extension/ingest/appointment` | Envía datos de cita/consulta |
| POST | `/extension/ingest/exam` | Envía resultados de examen |
| POST | `/extension/ingest/protocol` | Envía protocolo quirúrgico leído |

```json
// POST /extension/ingest/patient — body
{
  "sigcenter_id": "12345",
  "cedula": "0912345678",
  "nombre": "Juan Pérez",
  "historia": "HC-2024-001",
  "source_url": "https://sigcenter.cive.ec/paciente/12345",
  "captured_at": "2025-05-23T10:30:00Z"
}
```

---

## Autofill — MedForge → SigCenter

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/extension/fill/pending` | Obtiene tareas de autofill pendientes para el usuario activo |
| POST | `/extension/fill/{id}/done` | Marca tarea de autofill como completada |
| GET | `/extension/fill/protocol/{patient_id}` | Obtiene datos de protocolo para autofill |

```json
// GET /extension/fill/pending — respuesta
{
  "tasks": [
    {
      "id": 101,
      "type": "fill_protocol",
      "sigcenter_url": "https://sigcenter.cive.ec/protocolo/edit/55",
      "data": {
        "diagnostico": "Catarata nuclear OD",
        "tratamiento": "Facoemulsificación + LIO"
      }
    }
  ]
}
```

---

## Estado de la extensión

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| POST | `/extension/heartbeat` | Ping periódico — registra sesión activa de la extensión |
| GET  | `/extension/config` | Obtiene configuración activa (selectores, flags de features) |

---

## Errores estándar

| HTTP | Código interno | Descripción |
|------|---------------|-------------|
| 401 | `UNAUTHENTICATED` | Token inválido o expirado → re-login |
| 403 | `FORBIDDEN` | Usuario no tiene permiso para esta acción |
| 422 | `VALIDATION_ERROR` | Datos enviados inválidos — ver `errors` en respuesta |
| 429 | `RATE_LIMITED` | Demasiadas solicitudes — esperar `retry_after` segundos |
| 500 | `SERVER_ERROR` | Error interno — reportar a Consulmed |

```json
// Estructura de error estándar
{
  "message": "Token expirado",
  "code": "UNAUTHENTICATED",
  "errors": {}
}
```
