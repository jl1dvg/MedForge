@extends('layouts.medforge')

@php
    $summary = is_array($importSummary ?? null) ? $importSummary : [];
    $issues = is_array($summary['issues'] ?? null) ? $summary['issues'] : [];
    $importFiles = is_array($importFiles ?? null) ? $importFiles : [];
    $createMissingChecked = old('create_missing', '1') === '1';
    $dryRunChecked = old('dry_run', '1') === '1';
@endphp

@section('content')
    <div class="content-header">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="me-auto">
                <h3 class="page-title">Carga masiva de códigos</h3>
                <div class="d-inline-block align-items-center">
                    <nav>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/v2/codes">Códigos</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Carga masiva</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="/v2/codes" class="btn btn-secondary btn-sm">← Volver</a>
                <a href="/v2/codes/create" class="btn btn-primary btn-sm">Nuevo código</a>
            </div>
        </div>
    </div>

    <section class="content">
        @if(($status ?? null) === 'imported')
            <div class="alert alert-success">Importación ejecutada. Revisa el resumen para confirmar códigos y precios cargados.</div>
        @elseif(($status ?? null) === 'validated')
            <div class="alert alert-info">Validación completada. No se guardó información porque activaste "Solo validar".</div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                <strong>No se pudo procesar el archivo.</strong>
                <ul class="mb-0 mt-2">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="row g-3">
            <div class="col-xl-7 col-12">
                <div class="card">
                    <div class="card-header">
                        <strong>Archivo de importación</strong>
                    </div>
                    <div class="card-body">
                        <form method="post" action="/v2/codes/import" enctype="multipart/form-data" class="row g-3">
                            @csrf
                            <div class="col-12">
                                <label class="form-label">Archivo ya cargado en servidor</label>
                                <select name="stored_file" class="form-select">
                                    <option value="">— Seleccionar archivo de storage/imports/codes —</option>
                                    @foreach($importFiles as $importFile)
                                        @php $fileName = (string) ($importFile['name'] ?? ''); @endphp
                                        @if($fileName !== '')
                                            <option value="{{ $fileName }}" @selected(old('stored_file') === $fileName)>
                                                {{ $fileName }}
                                            </option>
                                        @endif
                                    @endforeach
                                </select>
                                <div class="form-text">Si seleccionas uno de estos archivos, el import usara el archivo guardado en `storage/imports/codes`.</div>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Excel o CSV</label>
                                <input type="file" name="file" class="form-control" accept=".xlsx,.xls,.csv,.txt">
                                <div class="form-text">Opcional si ya escogiste un archivo del servidor. Formato recomendado: `.xlsx`. Tambien acepta `.xls`, `.csv` y exportaciones HTML guardadas como `.xls`.</div>
                            </div>

                            <div class="col-md-6">
                                <input type="hidden" name="dry_run" value="0">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="import-dry-run" name="dry_run" value="1" @checked($dryRunChecked)>
                                    <label class="form-check-label" for="import-dry-run">Solo validar primero</label>
                                </div>
                                <small class="text-muted">Lee el archivo completo y muestra el resumen sin guardar cambios.</small>
                            </div>

                            <div class="col-md-6">
                                <input type="hidden" name="create_missing" value="0">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="import-create-missing" name="create_missing" value="1" @checked($createMissingChecked)>
                                    <label class="form-check-label" for="import-create-missing">Crear códigos faltantes</label>
                                </div>
                                <small class="text-muted">Si el código no existe en `tarifario_2014`, se crea automáticamente.</small>
                            </div>

                            <div class="col-12">
                                <div class="alert alert-light border mb-0">
                                    <strong>Columnas esperadas</strong><br>
                                    Requeridas: `Afiliación`, `Precio` y al menos una entre `Código Tarifario` o `Código Particular`.<br>
                                    Opcionales: `Afiliacion ID`, `Siglas Tipo Procedimiento en Archivo Plano`, `Código Dependencia`, `Nombre`, `Activo`.
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="alert alert-info mb-0">
                                    El import agrupa por código, crea o actualiza el registro una sola vez y sincroniza los precios por afiliación en `prices`.
                                    Para códigos nuevos, los precios base `N1/N2/N3` se inicializan con el primer precio válido encontrado en el archivo.
                                </div>
                            </div>

                            <div class="col-12">
                                <button class="btn btn-primary" type="submit">
                                    <i class="mdi mdi-upload"></i> Procesar archivo
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-xl-5 col-12">
                <div class="card h-100">
                    <div class="card-header">
                        <strong>Formato reconocido</strong>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>Columna</th>
                                    <th>Uso</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr>
                                    <td>Afiliación</td>
                                    <td>Busca el `pricelevel` en `afiliacion_categoria_map`.</td>
                                </tr>
                                <tr>
                                    <td>Código Tarifario</td>
                                    <td>Identificador principal del código.</td>
                                </tr>
                                <tr>
                                    <td>Código Particular</td>
                                    <td>Se usa solo si `Código Tarifario` viene vacío.</td>
                                </tr>
                                <tr>
                                    <td>Nombre</td>
                                    <td>Llena `descripcion` y `short_description`.</td>
                                </tr>
                                <tr>
                                    <td>Precio</td>
                                    <td>Se guarda en `prices` para la afiliación de la fila.</td>
                                </tr>
                                <tr>
                                    <td>Siglas Tipo Procedimiento...</td>
                                    <td>Intenta mapearse a `superbill` si coincide con una categoría existente.</td>
                                </tr>
                                <tr>
                                    <td>Activo</td>
                                    <td>Marca el código como activo/inactivo.</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            @if($summary !== [])
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex flex-wrap gap-2 align-items-center">
                            <strong>Resumen del último proceso</strong>
                            <span class="badge {{ !empty($summary['dry_run']) ? 'bg-info' : 'bg-success' }}">
                                {{ !empty($summary['dry_run']) ? 'Solo validación' : 'Importación aplicada' }}
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="row g-3 mb-3">
                                <div class="col-md-3 col-6">
                                    <div class="border rounded p-3 h-100">
                                        <div class="text-muted small">Archivo</div>
                                        <div class="fw-semibold">{{ (string) ($summary['filename'] ?? '—') }}</div>
                                        <div class="small text-muted">Hoja: {{ (string) ($summary['sheet_name'] ?? '—') }}</div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="border rounded p-3 h-100">
                                        <div class="text-muted small">Filas leídas</div>
                                        <div class="fs-4 fw-bold">{{ (int) ($summary['rows_total'] ?? 0) }}</div>
                                        <div class="small text-muted">Códigos agrupados: {{ (int) ($summary['codes_total'] ?? 0) }}</div>
                                    </div>
                                </div>
                                <div class="col-md-2 col-6">
                                    <div class="border rounded p-3 h-100">
                                        <div class="text-muted small">Creados</div>
                                        <div class="fs-4 fw-bold text-success">{{ (int) ($summary['created_codes'] ?? 0) }}</div>
                                    </div>
                                </div>
                                <div class="col-md-2 col-6">
                                    <div class="border rounded p-3 h-100">
                                        <div class="text-muted small">Actualizados</div>
                                        <div class="fs-4 fw-bold text-primary">{{ (int) ($summary['updated_codes'] ?? 0) }}</div>
                                    </div>
                                </div>
                                <div class="col-md-2 col-6">
                                    <div class="border rounded p-3 h-100">
                                        <div class="text-muted small">Precios</div>
                                        <div class="fs-4 fw-bold">{{ (int) ($summary['prices_synced'] ?? 0) }}</div>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-3 col-6">
                                    <div class="alert alert-light border mb-0">
                                        <strong>Omitidos:</strong> {{ (int) ($summary['skipped_codes'] ?? 0) }}
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">
                                    <div class="alert alert-warning mb-0">
                                        <strong>Advertencias:</strong> {{ (int) ($summary['warnings_count'] ?? 0) }}
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <h5 class="mb-3">Incidencias detectadas</h5>
                            @if($issues === [])
                                <p class="text-muted mb-0">No se registraron advertencias en el resumen guardado.</p>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped align-middle mb-0">
                                        <thead>
                                        <tr>
                                            <th style="width: 120px;">Fila</th>
                                            <th>Detalle</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($issues as $issue)
                                            <tr>
                                                <td>{{ $issue['row'] !== null ? (int) $issue['row'] : '—' }}</td>
                                                <td>{{ (string) ($issue['message'] ?? '') }}</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                @if(((int) ($summary['warnings_count'] ?? 0)) > count($issues))
                                    <p class="text-muted small mt-2 mb-0">
                                        Se muestran solo las primeras {{ count($issues) }} incidencias para mantener el resumen legible.
                                    </p>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </section>
@endsection
