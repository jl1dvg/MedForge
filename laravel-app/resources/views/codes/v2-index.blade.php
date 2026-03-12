@extends('layouts.medforge')

@php
    $types = is_array($types ?? null) ? $types : [];
    $cats = is_array($cats ?? null) ? $cats : [];
    $f = is_array($f ?? null) ? $f : [];
    $totalFormatted = number_format((int) ($total ?? 0), 0, '', '.');
@endphp

@section('content')
    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h3 class="page-title">Catálogo de códigos</h3>
                <div class="d-inline-block align-items-center">
                    <nav>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item">
                                <a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Códigos</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div>
                <a href="/v2/codes/create" class="btn btn-primary btn-sm">
                    <i class="mdi mdi-plus"></i> Nuevo código
                </a>
            </div>
        </div>
    </div>

    <section class="content">
        @if(($status ?? null) === 'created')
            <div class="alert alert-success">Código creado correctamente.</div>
        @elseif(($status ?? null) === 'updated')
            <div class="alert alert-success">Código actualizado correctamente.</div>
        @elseif(($status ?? null) === 'deleted')
            <div class="alert alert-success">Código eliminado correctamente.</div>
        @elseif(($status ?? null) === 'not_found')
            <div class="alert alert-warning">El código solicitado no existe.</div>
        @endif

        <div class="row">
            <div class="col-12">
                <div class="box">
                    <div class="box-header with-border d-flex flex-wrap align-items-center gap-3">
                        <div>
                            <h4 class="mb-0">Gestión de códigos</h4>
                            <p class="text-muted mb-0">Total filtrado: {{ $totalFormatted }} registros</p>
                        </div>
                        <div class="ms-auto">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="codes-refresh-btn">
                                <i class="mdi mdi-refresh"></i> Recargar
                            </button>
                        </div>
                    </div>
                    <div class="box-body">
                        <form class="card card-body mb-3" method="get" action="/v2/codes" id="codes-filter-form">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label mb-0">Buscar</label>
                                    <input type="text"
                                           name="q"
                                           class="form-control form-control-sm"
                                           value="{{ (string) ($f['q'] ?? '') }}"
                                           placeholder="Código o descripción">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-0">Tipo</label>
                                    <select name="code_type" class="form-select form-select-sm">
                                        <option value="">— Todos —</option>
                                        @foreach($types as $type)
                                            @php $value = (string) ($type['key_name'] ?? ''); @endphp
                                            <option value="{{ $value }}" @selected((string) ($f['code_type'] ?? '') === $value)>
                                                {{ (string) ($type['label'] ?? $value) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-0">Categoría (superbill)</label>
                                    <select name="superbill" class="form-select form-select-sm">
                                        <option value="">— Todas —</option>
                                        @foreach($cats as $cat)
                                            @php $value = (string) ($cat['slug'] ?? ''); @endphp
                                            <option value="{{ $value }}" @selected((string) ($f['superbill'] ?? '') === $value)>
                                                {{ (string) ($cat['title'] ?? $value) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <div class="d-flex gap-3 flex-wrap">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="f_active" name="active" value="1" @checked(!empty($f['active']))>
                                            <label class="form-check-label" for="f_active">Solo activos</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="f_reportable" name="reportable" value="1" @checked(!empty($f['reportable']))>
                                            <label class="form-check-label" for="f_reportable">Reportables</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="f_finrep" name="financial_reporting" value="1" @checked(!empty($f['financial_reporting']))>
                                            <label class="form-check-label" for="f_finrep">Financieros</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 d-flex gap-2">
                                    <button class="btn btn-primary btn-sm" type="submit">
                                        <i class="mdi mdi-magnify"></i> Aplicar filtros
                                    </button>
                                    <a href="/v2/codes" class="btn btn-outline-secondary btn-sm">Limpiar</a>
                                </div>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table id="codesTable"
                                   class="table table-striped table-sm align-middle w-100"
                                   data-datatable-url="/v2/codes/datatable"
                                   data-index-url="/v2/codes">
                                <thead class="bg-light">
                                <tr>
                                    <th>Código</th>
                                    <th>Modifier</th>
                                    <th>Activo</th>
                                    <th>Categoría</th>
                                    <th>Dx rep.</th>
                                    <th>Fin. rep.</th>
                                    <th>Tipo</th>
                                    <th>Descripción</th>
                                    <th>Descripción corta</th>
                                    <th>Relacionados</th>
                                    <th class="text-end">Precio N1</th>
                                    <th class="text-end">Precio N2</th>
                                    <th class="text-end">Precio N3</th>
                                    <th>Acciones</th>
                                </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('styles')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/rowgroup/1.3.1/css/rowGroup.dataTables.min.css">
@endpush

@push('scripts')
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/rowgroup/1.3.1/js/dataTables.rowGroup.min.js"></script>
    <script src="/js/pages/codes-index.js"></script>
@endpush

