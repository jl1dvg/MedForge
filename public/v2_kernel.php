<?php
// Strangler bridge entrypoint. Keeps legacy runtime intact while routing /v2/*
// requests to the parallel Laravel app in /laravel-app.
require __DIR__ . '/../laravel-app/public/index.php';

