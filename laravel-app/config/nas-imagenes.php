<?php

/**
 * NAS de imágenes (exámenes). Estos valores se resuelven aquí —y no con
 * getenv()/$_ENV directos en los servicios— para que `php artisan
 * config:cache` los hornee en bootstrap/cache/config.php. Producción cachea
 * la config en cada deploy, y Laravel deja de cargar el .env en cuanto la
 * config está cacheada (LoadEnvironmentVariables::bootstrap), así que leer
 * el .env "en crudo" después de un deploy devuelve null hasta que alguien
 * corre config:clear manualmente.
 */
return [

    'mount' => env('NAS_IMAGES_MOUNT'),

    'ssh_host' => env('NAS_IMAGES_SSH_HOST'),
    'ssh_port' => (int) env('NAS_IMAGES_SSH_PORT', 22),
    'ssh_user' => env('NAS_IMAGES_SSH_USER'),
    'ssh_pass' => env('NAS_IMAGES_SSH_PASS'),
    'base_path' => env('NAS_IMAGES_BASE_PATH', '/volume1/Imagenes'),

    'cache_dir' => env('NAS_IMAGES_CACHE_DIR', env('IMAGENES_CACHE_DIR')),
    'cache_ttl' => (int) env('NAS_IMAGES_CACHE_TTL', env('IMAGENES_CACHE_TTL', 1800)),
    'list_cache_ttl' => (int) env('NAS_IMAGES_LIST_CACHE_TTL', env('IMAGENES_LIST_CACHE_TTL', 90)),
    'realizadas_cache_ttl' => (int) env('IMAGENES_REALIZADAS_CACHE_SECONDS', 60),
    'informe_datos_cache_ttl' => (int) env('IMAGENES_INFORME_DATOS_CACHE_SECONDS', 300),
    'enable_fallback' => (bool) env('IMAGENES_ENABLE_NAS_FALLBACK', true),

];
