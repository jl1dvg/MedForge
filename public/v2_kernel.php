<?php
// TEMPORAL: mostrar errores para diagnosticar staging — REVERTIR antes de mergear
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
// Strangler bridge entrypoint. Keeps legacy runtime intact while routing /v2/*
// requests to the parallel Laravel app in /laravel-app.
require __DIR__ . '/../laravel-app/public/index.php';

