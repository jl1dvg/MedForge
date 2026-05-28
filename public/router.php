<?php
// Local dev router - mirrors .htaccess rewrite rules for php -S
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Serve real files/dirs from root public directly
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

// Laravel asset paths live in laravel-app/public/ - serve them directly
$laravelPublic = __DIR__ . '/../laravel-app/public';
if ($uri !== '/' && file_exists($laravelPublic . $uri)) {
    $ext = pathinfo($uri, PATHINFO_EXTENSION);
    $mimeTypes = [
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'woff2'=> 'font/woff2',
        'woff' => 'font/woff',
        'ttf'  => 'font/ttf',
        'svg'  => 'image/svg+xml',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'ico'  => 'image/x-icon',
        'map'  => 'application/json',
        'json' => 'application/json',
    ];
    if (isset($mimeTypes[$ext])) {
        header('Content-Type: ' . $mimeTypes[$ext]);
    }
    readfile($laravelPublic . $uri);
    return;
}

// Legacy /dashboard redirect → Laravel v2
if ($uri === '/dashboard') {
    header('Location: /v2/dashboard', true, 302);
    exit;
}

// Strangler bridge routes → Laravel
if (preg_match('#^/auth/(login|logout)$#', $uri) ||
    preg_match('#^/(usuarios|roles)(/.*)?$#', $uri) ||
    preg_match('#^/v2(/.*)?$#', $uri)) {
    require __DIR__ . '/v2_kernel.php';
    return;
}

// Everything else → legacy
require __DIR__ . '/index.php';
