# MedForge — Contexto para Claude

## Deploy: GitHub Actions (NUNCA pushear directo a main)

El flujo de trabajo es:

```
feat/mi-feature  (o fix/, chore/, etc.)
      │
      ▼
   PR → main ──► GitHub Actions dispara deploy-staging automáticamente
                 (probar en staging antes de aprobar)
      │
      ▼
   Apruebas y haces merge
      │
      ▼
   main ──► GitHub Actions dispara deploy-production automáticamente
```

### Triggers del workflow `.github/workflows/deploy.yml`
- `push` a `main` → producción
- `push` a `staging` → staging (deploy directo sin PR, para hotfixes de infra)
- `pull_request` targeting `main` → staging automático
- `workflow_dispatch` → manual (elegir entorno)

### Reglas
- Todo cambio de código va en una rama propia y entra por PR a `main`
- El PR despliega automáticamente a staging para que el usuario pueda probar
- Solo mergear cuando el usuario haya revisado y aprobado en staging
- La rama de trabajo designada por el agente es `claude/multi-server-db-staging-scraper-LJIT3`

## Arquitectura multi-servidor

| Servidor | `SERVER_ROLE` | Responsabilidad |
|---|---|---|
| Staging | `scraper` | Todo el scraping externo (CIVE, IESS, NAS, Sigcenter, IA) |
| Producción | `production` | Lógica de negocio (recordatorios, billing, CRM, KPIs) |
| DB | — | MySQL compartido entre ambos servidores |

- El filtrado se hace en `CronRunner::definitions()` y en el bloque `SERVER_ROLE` de `routes/console.php`
- Sin `SERVER_ROLE` (dev local) → corren todas las tareas
- PHP CLI: `/usr/bin/php8.3-cli` (no usar `php` — es CGI en webspace-data.io)
- Composer: `composer.phar` local en cada servidor (no `composer` global)
