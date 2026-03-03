## MedForge Migration PR Checklist

### Contexto
- Módulo:
- Wave (0/1/2/3):
- Riesgo (Bajo/Medio/Alto):

### Scope
- [ ] Read parity
- [ ] Write parity
- [ ] UI cutover

### Endpoints incluidos
- legacy:
- v2:

### Flags
- [ ] `<MODULE>_V2_READS_ENABLED`
- [ ] `<MODULE>_V2_WRITES_ENABLED`
- [ ] `<MODULE>_V2_UI_ENABLED`

Valores usados en validación:

```env
# ejemplo
<MODULE>_V2_READS_ENABLED=1
<MODULE>_V2_WRITES_ENABLED=1
<MODULE>_V2_UI_ENABLED=0
```

### Validación de smoke
Comandos ejecutados:

```bash
php tools/tests/http_smoke.php --module=<module>
php tools/tests/http_smoke.php --module=<module> --cookie='PHPSESSID=...'
```

Resultados:
- [ ] Guest OK
- [ ] Auth OK
- [ ] Destructive (si aplica) OK

### Observabilidad
- [ ] `X-Request-Id` visible en trazas
- [ ] Logs de error revisables para este módulo

### Rollback
Pasos exactos:
1.
2.
3.

Tiempo esperado: `<5 min`

### Evidencia
- Capturas / logs / links:
