@extends('layouts.medforge')

@php
    $rules = is_array($rules ?? null) ? $rules : [];
    $tipoOptions = is_array($tipoOptions ?? null) ? $tipoOptions : [];
    $categoriaOptions = is_array($categoriaOptions ?? null) ? $categoriaOptions : [];
    $categoriaOptions = array_values(array_filter($categoriaOptions, static fn($option) => (string) ($option['value'] ?? '') !== ''));
    array_unshift($categoriaOptions, ['value' => '*', 'label' => 'Todas las categorías']);
@endphp

@push('styles')
    <style>
        .honorarios-settings-table .form-select,
        .honorarios-settings-table .form-control {
            min-width: 150px;
        }

        .honorarios-settings-table .honorarios-percent {
            max-width: 120px;
        }
    </style>
@endpush

@section('content')
    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h3 class="page-title">Settings de honorarios</h3>
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                        <li class="breadcrumb-item"><a href="/v2/billing">Billing</a></li>
                        <li class="breadcrumb-item"><a href="/v2/billing/honorarios">Honorarios</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Settings</li>
                    </ol>
                </nav>
            </div>
            <div class="ms-auto">
                <a class="btn btn-outline-primary" href="/v2/billing/honorarios">
                    <i class="mdi mdi-arrow-left"></i> Volver
                </a>
            </div>
        </div>
    </div>

    <section class="content">
        @if(session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <form method="POST" action="/v2/billing/honorarios/settings" class="box">
            @csrf
            <div class="box-header with-border d-flex align-items-center">
                <h4 class="box-title mb-0">Reglas de pago por tipo de atención y categoría</h4>
                <button type="button" class="btn btn-sm btn-outline-primary ms-auto" id="honorarios-add-rule">
                    <i class="mdi mdi-plus"></i> Agregar regla
                </button>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-striped align-middle honorarios-settings-table">
                    <thead class="bg-primary-light">
                    <tr>
                        <th>Tipo de atención</th>
                        <th>Categoría afiliación</th>
                        <th>Modo de pago</th>
                        <th>Porcentaje</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                    </thead>
                    <tbody id="honorarios-rules-body">
                    @foreach($rules as $index => $rule)
                        <tr>
                            <td>
                                <select class="form-select" name="rules[{{ $index }}][tipo_atencion]">
                                    @foreach($tipoOptions as $option)
                                        @php $value = (string) ($option['value'] ?? ''); @endphp
                                        <option value="{{ $value }}" @selected((string) ($rule['tipo_atencion'] ?? '') === $value)>{{ (string) ($option['label'] ?? $value) }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select class="form-select" name="rules[{{ $index }}][categoria_afiliacion]">
                                    @foreach($categoriaOptions as $option)
                                        @php $value = (string) ($option['value'] ?? ''); @endphp
                                        <option value="{{ $value }}" @selected((string) ($rule['categoria_afiliacion'] ?? '') === $value)>{{ (string) ($option['label'] ?? $value) }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select class="form-select honorarios-mode" name="rules[{{ $index }}][modo]">
                                    <option value="porcentaje" @selected((string) ($rule['modo'] ?? '') === 'porcentaje')>Porcentaje</option>
                                    <option value="honorario_codigo" @selected((string) ($rule['modo'] ?? '') === 'honorario_codigo')>Honorario del código</option>
                                </select>
                            </td>
                            <td>
                                <input type="number" class="form-control honorarios-percent" name="rules[{{ $index }}][porcentaje]" value="{{ (string) ($rule['porcentaje'] ?? '') }}" min="0" max="100" step="0.01">
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-danger honorarios-remove-rule" title="Eliminar">
                                    <i class="mdi mdi-delete-outline"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="box-footer text-end">
                <button type="submit" class="btn btn-primary">
                    <i class="mdi mdi-content-save-outline"></i> Guardar settings
                </button>
            </div>
        </form>
    </section>
@endsection

@push('scripts')
    <script>
        (() => {
            const body = document.getElementById('honorarios-rules-body');
            const addButton = document.getElementById('honorarios-add-rule');
            const tipoOptions = @json($tipoOptions);
            const categoriaOptions = @json($categoriaOptions);
            const escapeHtml = value => String(value || '').replace(/[&<>"']/g, char => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
            }[char]));

            const optionsHtml = (options, selected) => options.map(option => {
                const value = String(option.value || '');
                const label = String(option.label || value);
                return '<option value="' + escapeHtml(value) + '"' + (value === selected ? ' selected' : '') + '>' + escapeHtml(label) + '</option>';
            }).join('');

            const syncRow = row => {
                const mode = row.querySelector('.honorarios-mode');
                const percent = row.querySelector('.honorarios-percent');
                if (!mode || !percent) {
                    return;
                }
                const usesPercent = mode.value === 'porcentaje';
                percent.disabled = !usesPercent;
                if (!usesPercent) {
                    percent.value = '';
                }
            };

            const reindex = () => {
                Array.from(body.querySelectorAll('tr')).forEach((row, index) => {
                    row.querySelectorAll('[name]').forEach(input => {
                        input.name = input.name.replace(/rules\[\d+]/, 'rules[' + index + ']');
                    });
                });
            };

            const addRow = () => {
                const index = body.querySelectorAll('tr').length;
                const row = document.createElement('tr');
                row.innerHTML =
                    '<td><select class="form-select" name="rules[' + index + '][tipo_atencion]">' + optionsHtml(tipoOptions, '*') + '</select></td>' +
                    '<td><select class="form-select" name="rules[' + index + '][categoria_afiliacion]">' + optionsHtml(categoriaOptions, '*') + '</select></td>' +
                    '<td><select class="form-select honorarios-mode" name="rules[' + index + '][modo]"><option value="porcentaje">Porcentaje</option><option value="honorario_codigo">Honorario del código</option></select></td>' +
                    '<td><input type="number" class="form-control honorarios-percent" name="rules[' + index + '][porcentaje]" value="30" min="0" max="100" step="0.01"></td>' +
                    '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger honorarios-remove-rule" title="Eliminar"><i class="mdi mdi-delete-outline"></i></button></td>';
                body.appendChild(row);
                syncRow(row);
            };

            body.addEventListener('change', event => {
                if (event.target.classList.contains('honorarios-mode')) {
                    syncRow(event.target.closest('tr'));
                }
            });
            body.addEventListener('click', event => {
                const button = event.target.closest('.honorarios-remove-rule');
                if (!button) {
                    return;
                }
                button.closest('tr').remove();
                reindex();
            });
            addButton.addEventListener('click', addRow);
            body.querySelectorAll('tr').forEach(syncRow);
        })();
    </script>
@endpush
