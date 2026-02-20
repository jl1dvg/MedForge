# Proxy bridge (`/v2/*` -> Laravel)

This migration uses reverse-proxy strangler routing.

- `/v2/*` -> Laravel app
- everything else -> legacy app

Use these examples from the Laravel project:

- `/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/deploy/nginx.strangler.conf`
- `/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/deploy/apache-vhost.strangler.conf`

## Required headers

- `X-Request-Id`
- `X-Forwarded-For`
- `X-Forwarded-Proto`
