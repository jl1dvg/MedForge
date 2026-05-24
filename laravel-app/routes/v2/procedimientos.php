<?php

use App\Modules\Procedimientos\Http\Controllers\ProcedimientosReadController;
use Illuminate\Support\Facades\Route;

// Rutas sin prefijo /v2 — consumidas por CiveExtension directamente.
// La extensión construye la URL con su apiBaseUrl configurado; dependiendo de si
// el ajuste incluye o no "/api", la URL resultante puede ser:
//   • /procedimientos/listar.php      (apiBaseUrl = https://cive.consulmed.me)
//   • /api/procedimientos/listar.php  (apiBaseUrl = https://cive.consulmed.me/api)
// Cubrimos ambas para ser robustos ante cualquier configuración.
//
// CORS: consultas.cors agrega los headers Access-Control-Allow-* necesarios.
// Auth:  cive.extension.auth valida el ID de extensión Chrome o secreto server-to-server.
