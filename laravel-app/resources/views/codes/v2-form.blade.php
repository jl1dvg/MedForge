@extends('layouts.medforge')

@php
    $code = is_array($code ?? null) ? $code : null;
    $types = is_array($types ?? null) ? $types : [];
    $cats = is_array($cats ?? null) ? $cats : [];
    $rels = is_array($rels ?? null) ? $rels : [];
    $priceLevels = is_array($priceLevels ?? null) ? $priceLevels : [];
    $prices = is_array($prices ?? null) ? $prices : [];
    $isEdit = $code !== null;
    $codeId = (int) ($code['id'] ?? 0);
    $action = $isEdit ? '/v2/codes/' . $codeId : '/v2/codes';
    $title = $isEdit ? 'Editar código' : 'Nuevo código';
    $hasAffiliationLevels = false;
    foreach ($priceLevels as $level) {
        if (($level['source'] ?? '') === 'afiliacion_categoria_map') {
            $hasAffiliationLevels = true;
            break;
        }
    }
@endphp

@section('content')
    <div class="content-header">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="me-auto">
                <h3 class="page-title">{{ $title }}</h3>
                <div class="d-inline-block align-items-center">
                    <nav>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/v2/codes">Códigos</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ $isEdit ? 'Editar' : 'Crear' }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="/v2/codes" class="btn btn-secondary btn-sm">← Volver</a>
                @if($isEdit)
                    <form class="d-inline" method="post" action="/v2/codes/{{ $codeId }}/delete" onsubmit="return confirm('¿Eliminar este código?');">
                        @csrf
                        <button class="btn btn-outline-danger btn-sm" type="submit">
                            <i class="mdi mdi-delete-outline"></i> Eliminar
                        </button>
                    </form>
                    <form class="d-inline" method="post" action="/v2/codes/{{ $codeId }}/toggle">
                        @csrf
                        <button class="btn btn-outline-warning btn-sm" type="submit">
                            {{ !empty($code['active']) ? 'Desactivar' : 'Activar' }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <section class="content">
        @if(($status ?? null) === 'created')
            <div class="alert alert-success">Código creado correctamente.</div>
        @elseif(($status ?? null) === 'updated')
            <div class="alert alert-success">Cambios guardados correctamente.</div>
        @elseif(($status ?? null) === 'toggled')
            <div class="alert alert-success">Estado actualizado correctamente.</div>
        @elseif(($status ?? null) === 'relation_added')
            <div class="alert alert-success">Relación agregada.</div>
        @elseif(($status ?? null) === 'relation_removed')
            <div class="alert alert-success">Relación eliminada.</div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                <strong>No se pudo guardar el código.</strong>
                <ul class="mb-0 mt-2">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="row">
            <div class="col-12">
                <form method="post" action="{{ $action }}" class="card card-body">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Código</label>
                            <input name="codigo"
                                   class="form-control form-control-sm"
                                   required
                                   value="{{ old('codigo', (string) ($code['codigo'] ?? '')) }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Modifier</label>
                            <input name="modifier"
                                   class="form-control form-control-sm"
                                   value="{{ old('modifier', (string) ($code['modifier'] ?? '')) }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tipo</label>
                            <select name="code_type" class="form-select form-select-sm">
                                <option value="">— Seleccionar —</option>
                                @foreach($types as $type)
                                    @php $val = (string) ($type['key_name'] ?? ''); @endphp
                                    <option value="{{ $val }}" @selected(old('code_type', (string) ($code['code_type'] ?? '')) === $val)>
                                        {{ (string) ($type['label'] ?? $val) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Categoría</label>
                            <select name="superbill" class="form-select form-select-sm">
                                <option value="">— Seleccionar —</option>
                                @foreach($cats as $cat)
                                    @php $val = (string) ($cat['slug'] ?? ''); @endphp
                                    <option value="{{ $val }}" @selected(old('superbill', (string) ($code['superbill'] ?? '')) === $val)>
                                        {{ (string) ($cat['title'] ?? $val) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Revenue Code</label>
                            <input name="revenue_code"
                                   class="form-control form-control-sm"
                                   value="{{ old('revenue_code', (string) ($code['revenue_code'] ?? '')) }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <input name="descripcion"
                                   class="form-control form-control-sm"
                                   value="{{ old('descripcion', (string) ($code['descripcion'] ?? '')) }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descripción corta</label>
                            <input name="short_description"
                                   class="form-control form-control-sm"
                                   value="{{ old('short_description', (string) ($code['short_description'] ?? '')) }}">
                        </div>
                        <div class="col-md-2">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="code-active" name="active" value="1" @checked((bool) old('active', !empty($code['active'])))>
                                <label class="form-check-label" for="code-active">Activo</label>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="code-reportable" name="reportable" value="1" @checked((bool) old('reportable', !empty($code['reportable'])))>
                                <label class="form-check-label" for="code-reportable">Reportable</label>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="code-finrep" name="financial_reporting" value="1" @checked((bool) old('financial_reporting', !empty($code['financial_reporting'])))>
                                <label class="form-check-label" for="code-finrep">Financiero</label>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="alert alert-light border py-2 mb-2">
                                <strong>Precios base</strong>: Nivel 1, Nivel 2 y Nivel 3 son precios generales del código.
                            </div>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Precio Nivel 1</label>
                            <input name="precio_nivel1" type="number" step="0.0001" class="form-control form-control-sm"
                                   value="{{ old('precio_nivel1', $code['valor_facturar_nivel1'] ?? '') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Precio Nivel 2</label>
                            <input name="precio_nivel2" type="number" step="0.0001" class="form-control form-control-sm"
                                   value="{{ old('precio_nivel2', $code['valor_facturar_nivel2'] ?? '') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Precio Nivel 3</label>
                            <input name="precio_nivel3" type="number" step="0.0001" class="form-control form-control-sm"
                                   value="{{ old('precio_nivel3', $code['valor_facturar_nivel3'] ?? '') }}">
                        </div>

                        @if(!empty($priceLevels))
                            <div class="col-12">
                                <div class="alert alert-info py-2 mb-2">
                                    @if($hasAffiliationLevels)
                                        <strong>Precios por afiliación (pricelevel)</strong>: <code>afiliacion_categoria_map</code> define un precio específico por afiliación.
                                    @else
                                        Además de los precios estándar puedes registrar valores dinámicos por nivel.
                                    @endif
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="table-responsive border rounded" style="max-height: 360px;">
                                    <table class="table table-sm mb-0 align-middle">
                                        <thead class="table-light sticky-top">
                                        <tr>
                                            <th>Price level</th>
                                            <th style="width: 160px;">Categoría</th>
                                            <th style="width: 220px;">Precio</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($priceLevels as $level)
                                            @php
                                                $key = (string) ($level['level_key'] ?? '');
                                                $existing = $prices[$key] ?? '';
                                                $oldPrices = old('prices');
                                                $value = $existing;
                                                if (is_array($oldPrices) && array_key_exists($key, $oldPrices)) {
                                                    $value = $oldPrices[$key];
                                                }
                                                $category = (string) ($level['category'] ?? '');
                                            @endphp
                                            @if($key !== '')
                                                <tr>
                                                    <td>{{ (string) ($level['title'] ?? $key) }}</td>
                                                    <td>
                                                        @if($category !== '')
                                                            <span class="badge bg-light text-dark border">{{ strtoupper($category) }}</span>
                                                        @else
                                                            <span class="text-muted">—</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <input name="prices[{{ $key }}]"
                                                               type="number"
                                                               step="0.0001"
                                                               class="form-control form-control-sm"
                                                               value="{{ $value }}">
                                                    </td>
                                                </tr>
                                            @endif
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        <div class="col-md-2">
                            <label class="form-label">Anestesia N1</label>
                            <input name="anestesia_nivel1" type="number" step="0.0001" class="form-control form-control-sm"
                                   value="{{ old('anestesia_nivel1', $code['anestesia_nivel1'] ?? '') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Anestesia N2</label>
                            <input name="anestesia_nivel2" type="number" step="0.0001" class="form-control form-control-sm"
                                   value="{{ old('anestesia_nivel2', $code['anestesia_nivel2'] ?? '') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Anestesia N3</label>
                            <input name="anestesia_nivel3" type="number" step="0.0001" class="form-control form-control-sm"
                                   value="{{ old('anestesia_nivel3', $code['anestesia_nivel3'] ?? '') }}">
                        </div>

                        <div class="col-12">
                            <button class="btn btn-primary btn-sm" type="submit">
                                {{ $isEdit ? 'Guardar cambios' : 'Crear código' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        @if($isEdit)
            <div class="row mt-3">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <strong>Relacionar códigos</strong>
                        </div>
                        <div class="card-body">
                            <form class="row g-2 align-items-end" method="post" action="/v2/codes/{{ $codeId }}/relate">
                                @csrf
                                <div class="col-md-3">
                                    <label class="form-label mb-0">ID relacionado</label>
                                    <input name="related_id" type="number" class="form-control form-control-sm" required placeholder="ID de tarifario_2014">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label mb-0">Tipo relación</label>
                                    <select name="relation_type" class="form-select form-select-sm">
                                        <option value="maps_to">maps_to</option>
                                        <option value="relates_to">relates_to</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button class="btn btn-outline-primary btn-sm" type="submit">Agregar</button>
                                </div>
                            </form>

                            <div class="table-responsive mt-3">
                                <table class="table table-sm table-bordered align-middle">
                                    <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Código</th>
                                        <th>Descripción</th>
                                        <th>Relación</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @if(empty($rels))
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">Sin relaciones</td>
                                        </tr>
                                    @else
                                        @foreach($rels as $rel)
                                            <tr>
                                                <td>{{ (int) ($rel['related_code_id'] ?? 0) }}</td>
                                                <td>{{ (string) ($rel['codigo'] ?? '') }}</td>
                                                <td>{{ (string) ($rel['descripcion'] ?? '') }}</td>
                                                <td>{{ (string) ($rel['relation_type'] ?? '') }}</td>
                                                <td class="text-end">
                                                    <form class="d-inline"
                                                          method="post"
                                                          action="/v2/codes/{{ $codeId }}/relate/del"
                                                          onsubmit="return confirm('¿Quitar relación?');">
                                                        @csrf
                                                        <input type="hidden" name="related_id" value="{{ (int) ($rel['related_code_id'] ?? 0) }}">
                                                        <button class="btn btn-outline-danger btn-sm" type="submit">Quitar</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </section>
@endsection
