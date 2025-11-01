<?php
// Rutas del módulo Pacientes
return [
    // GET /pacientes  -> vista (tabla)
    ['GET', '/pacientes', ['Modules\Pacientes\Controllers\Pacientes', 'index']],
    // GET /pacientes/datatable -> JSON para DataTables (server-side)
    ['GET', '/pacientes/datatable', ['Modules\Pacientes\Controllers\Pacientes', 'datatable']],
    // GET /pacientes/detalle/{hc} -> ejemplo de detalle (opcional)
    ['GET', '/pacientes/detalle/([A-Za-z0-9\-\.]+)', ['Modules\Pacientes\Controllers\Pacientes', 'detalle']],
];