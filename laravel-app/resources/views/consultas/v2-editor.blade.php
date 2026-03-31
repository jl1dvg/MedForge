@extends('layouts.medforge')

@php
    $editorData = is_array($editorData ?? null) ? $editorData : [];
    $context = is_array($editorData['context'] ?? null) ? $editorData['context'] : [];
    $consulta = is_array($editorData['consulta'] ?? null) ? $editorData['consulta'] : [];
    $consultaExists = (bool) ($editorData['consulta_exists'] ?? false);

    $diagnosticos = old('diagnosticos', $editorData['diagnosticos'] ?? []);
    $diagnosticos = is_array($diagnosticos) ? array_values($diagnosticos) : [];
    if ($diagnosticos === []) {
        $diagnosticos[] = ['idDiagnostico' => '', 'ojo' => '', 'evidencia' => '0', 'selector' => ''];
    }

    $examenes = old('examenes', $editorData['examenes'] ?? []);
    $examenes = is_array($examenes) ? array_values($examenes) : [];
    if ($examenes === []) {
        $examenes[] = ['codigo' => '', 'nombre' => '', 'lateralidad' => ''];
    }

    $recetas = old('recetas', $editorData['recetas'] ?? []);
    $recetas = is_array($recetas) ? array_values($recetas) : [];
    if ($recetas === []) {
        $recetas[] = ['producto' => '', 'vias' => '', 'dosis' => '', 'unidad' => '', 'pauta' => '', 'cantidad' => '', 'total_farmacia' => '', 'observaciones' => ''];
    }

    $pio = old('pio', $editorData['pio'] ?? []);
    $pio = is_array($pio) ? array_values($pio) : [];
    if ($pio === []) {
        $pio[] = ['tonometro' => '', 'od' => '', 'oi' => '', 'po_patologico' => '0', 'po_hora' => '', 'hora_fin' => '', 'po_observacion' => ''];
    }

    $formatDate = static function (?string $value): string {
        $value = trim((string) $value);
        if ($value === '') {
            return '-';
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable) {
            return $value;
        }
    };

    $formatTime = static function (?string $value): string {
        $value = trim((string) $value);
        if ($value === '') {
            return '-';
        }

        return strlen($value) >= 5 ? substr($value, 0, 5) : $value;
    };

    $fieldValue = static function (string $name, mixed $default = '') {
        return old($name, $default);
    };

    $hasContext = $context !== [];
@endphp

@push('styles')
<style>
    .consulta-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
    }

    .consulta-summary-card {
        border: 1px solid #edf1f7;
        border-radius: 12px;
        padding: 14px 16px;
        background: #f8fafc;
    }

    .consulta-summary-card .label {
        display: block;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #7a8699;
        margin-bottom: 6px;
    }

    .consulta-summary-card .value {
        color: #1f2937;
        font-weight: 600;
    }

    .consulta-section-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 12px;
    }

    .consulta-table th,
    .consulta-table td {
        vertical-align: middle;
        white-space: nowrap;
    }

    .consulta-table input,
    .consulta-table textarea,
    .consulta-table select {
        min-width: 120px;
    }

    .consulta-table textarea {
        min-width: 220px;
    }

    .consulta-table .wide {
        min-width: 280px;
    }
</style>
@endpush

@section('content')
    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h3 class="page-title">Consulta</h3>
                <div class="d-inline-block align-items-center">
                    <nav>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                            <li class="breadcrumb-item"><a href="/v2/agenda">Agenda</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Historia clínica</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="ms-auto">
                <a href="/v2/agenda" class="btn btn-light">
                    <i class="fa fa-arrow-left me-5"></i>Volver a agenda
                </a>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="row">
            <div class="col-12">
                @if(session('consultas_status'))
                    <div class="alert alert-success">{{ session('consultas_status') }}</div>
                @endif

                @if(session('consultas_error'))
                    <div class="alert alert-danger">{{ session('consultas_error') }}</div>
                @endif

                @if(!empty($loadError))
                    <div class="alert alert-warning">{{ $loadError }}</div>
                @endif

                @if($hasContext && !$consultaExists)
                    <div class="alert alert-info">
                        Esta cita todavía no tiene registro en <code>consulta_data</code>. Al guardar esta pantalla se creará la historia clínica.
                    </div>
                @endif
            </div>

            @if($hasContext)
                <div class="col-12">
                    <div class="box">
                        <div class="box-body">
                            <div class="consulta-summary">
                                <div class="consulta-summary-card">
                                    <span class="label">Paciente</span>
                                    <span class="value">{{ (string) ($context['paciente'] ?? '-') }}</span>
                                </div>
                                <div class="consulta-summary-card">
                                    <span class="label">HC</span>
                                    <span class="value">{{ (string) ($context['hc_number'] ?? '-') }}</span>
                                </div>
                                <div class="consulta-summary-card">
                                    <span class="label">Form ID</span>
                                    <span class="value">{{ (string) ($context['form_id'] ?? '-') }}</span>
                                </div>
                                <div class="consulta-summary-card">
                                    <span class="label">Procedimiento</span>
                                    <span class="value">{{ (string) ($context['procedimiento'] ?? '-') }}</span>
                                </div>
                                <div class="consulta-summary-card">
                                    <span class="label">Doctor</span>
                                    <span class="value">{{ (string) ($context['doctor'] ?? '-') }}</span>
                                </div>
                                <div class="consulta-summary-card">
                                    <span class="label">Fecha agenda</span>
                                    <span class="value">{{ $formatDate((string) ($context['fecha_agenda'] ?? $context['fecha'] ?? '')) }}</span>
                                </div>
                                <div class="consulta-summary-card">
                                    <span class="label">Hora</span>
                                    <span class="value">{{ $formatTime((string) ($context['hora'] ?? $context['hora_llegada'] ?? '')) }}</span>
                                </div>
                                <div class="consulta-summary-card">
                                    <span class="label">Visita</span>
                                    <span class="value">{{ (string) ($context['visita_id'] ?? '-') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <form method="post" action="/v2/consultas">
                        @csrf

                        <input type="hidden" name="form_id" value="{{ (string) ($context['form_id'] ?? '') }}">
                        <input type="hidden" name="hc_number" value="{{ (string) ($context['hc_number'] ?? '') }}">
                        <input type="hidden" name="doctor" value="{{ (string) ($context['doctor'] ?? '') }}">

                        <div class="box">
                            <div class="box-header">
                                <h4 class="box-title">Datos del paciente</h4>
                            </div>
                            <div class="box-body">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label for="fechaActual" class="form-label">Fecha de consulta</label>
                                        <input type="date" id="fechaActual" name="fechaActual" class="form-control" value="{{ $fieldValue('fechaActual', $consulta['fechaActual'] ?? date('Y-m-d')) }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="fechaNacimiento" class="form-label">Fecha nacimiento</label>
                                        <input type="date" id="fechaNacimiento" name="fechaNacimiento" class="form-control" value="{{ $fieldValue('fechaNacimiento', $context['fecha_nacimiento'] ?? '') }}">
                                    </div>
                                    <div class="col-md-2">
                                        <label for="sexo" class="form-label">Sexo</label>
                                        <input type="text" id="sexo" name="sexo" class="form-control" value="{{ $fieldValue('sexo', $context['sexo'] ?? '') }}">
                                    </div>
                                    <div class="col-md-2">
                                        <label for="celular" class="form-label">Celular</label>
                                        <input type="text" id="celular" name="celular" class="form-control" value="{{ $fieldValue('celular', $context['celular'] ?? '') }}">
                                    </div>
                                    <div class="col-md-2">
                                        <label for="ciudad" class="form-label">Ciudad</label>
                                        <input type="text" id="ciudad" name="ciudad" class="form-control" value="{{ $fieldValue('ciudad', $context['ciudad'] ?? '') }}">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="box">
                            <div class="box-header">
                                <h4 class="box-title">Historia clínica</h4>
                            </div>
                            <div class="box-body">
                                <div class="row g-3">
                                    <div class="col-lg-6">
                                        <label for="motivoConsulta" class="form-label">Motivo de consulta</label>
                                        <textarea id="motivoConsulta" name="motivoConsulta" rows="4" class="form-control">{{ $fieldValue('motivoConsulta', $consulta['motivoConsulta'] ?? '') }}</textarea>
                                    </div>
                                    <div class="col-lg-6">
                                        <label for="enfermedadActual" class="form-label">Enfermedad actual</label>
                                        <textarea id="enfermedadActual" name="enfermedadActual" rows="4" class="form-control">{{ $fieldValue('enfermedadActual', $consulta['enfermedadActual'] ?? '') }}</textarea>
                                    </div>
                                    <div class="col-lg-6">
                                        <label for="examenFisico" class="form-label">Examen físico</label>
                                        <textarea id="examenFisico" name="examenFisico" rows="8" class="form-control">{{ $fieldValue('examenFisico', $consulta['examenFisico'] ?? '') }}</textarea>
                                    </div>
                                    <div class="col-lg-6">
                                        <label for="plan" class="form-label">Plan</label>
                                        <textarea id="plan" name="plan" rows="8" class="form-control">{{ $fieldValue('plan', $consulta['plan'] ?? '') }}</textarea>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="estadoEnfermedad" class="form-label">Estado enfermedad</label>
                                        <input type="text" id="estadoEnfermedad" name="estadoEnfermedad" class="form-control" value="{{ $fieldValue('estadoEnfermedad', $consulta['estadoEnfermedad'] ?? '') }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="vigenciaReceta" class="form-label">Vigencia receta</label>
                                        <input type="date" id="vigenciaReceta" name="vigenciaReceta" class="form-control" value="{{ $fieldValue('vigenciaReceta', $consulta['vigenciaReceta'] ?? '') }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="antecedente_alergico" class="form-label">Antecedentes / alergias</label>
                                        <textarea id="antecedente_alergico" name="antecedente_alergico" rows="3" class="form-control">{{ $fieldValue('antecedente_alergico', $consulta['antecedente_alergico'] ?? '') }}</textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="signos_alarma" class="form-label">Signos de alarma</label>
                                        <textarea id="signos_alarma" name="signos_alarma" rows="3" class="form-control">{{ $fieldValue('signos_alarma', $consulta['signos_alarma'] ?? '') }}</textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="recomen_no_farmaco" class="form-label">Recomendaciones no farmacológicas</label>
                                        <textarea id="recomen_no_farmaco" name="recomen_no_farmaco" rows="3" class="form-control">{{ $fieldValue('recomen_no_farmaco', $consulta['recomen_no_farmaco'] ?? '') }}</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="box">
                            <div class="box-header">
                                <div class="consulta-section-title w-100">
                                    <h4 class="box-title mb-0">Diagnósticos</h4>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-repeat-add="diagnosticos">Agregar diagnóstico</button>
                                </div>
                            </div>
                            <div class="box-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered consulta-table">
                                        <thead>
                                        <tr>
                                            <th>Código - descripción</th>
                                            <th>Ojo</th>
                                            <th>Definitivo</th>
                                            <th>Selector</th>
                                            <th></th>
                                        </tr>
                                        </thead>
                                        <tbody data-repeat-list="diagnosticos" data-next-index="{{ count($diagnosticos) }}">
                                        @foreach($diagnosticos as $index => $item)
                                            <tr data-repeat-row>
                                                <td><input type="text" name="diagnosticos[{{ $index }}][idDiagnostico]" class="form-control wide" value="{{ $item['idDiagnostico'] ?? '' }}" placeholder="H25.0 - Catarata senil"></td>
                                                <td><input type="text" name="diagnosticos[{{ $index }}][ojo]" class="form-control" value="{{ $item['ojo'] ?? '' }}" placeholder="OD/OI/AO"></td>
                                                <td class="text-center">
                                                    <input type="checkbox" name="diagnosticos[{{ $index }}][evidencia]" value="1" @checked((string) ($item['evidencia'] ?? '0') === '1')>
                                                </td>
                                                <td><input type="text" name="diagnosticos[{{ $index }}][selector]" class="form-control" value="{{ $item['selector'] ?? '' }}"></td>
                                                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" data-repeat-remove>Quitar</button></td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="box">
                            <div class="box-header">
                                <div class="consulta-section-title w-100">
                                    <h4 class="box-title mb-0">Exámenes</h4>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-repeat-add="examenes">Agregar examen</button>
                                </div>
                            </div>
                            <div class="box-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered consulta-table">
                                        <thead>
                                        <tr>
                                            <th>Código</th>
                                            <th>Nombre</th>
                                            <th>Lateralidad</th>
                                            <th></th>
                                        </tr>
                                        </thead>
                                        <tbody data-repeat-list="examenes" data-next-index="{{ count($examenes) }}">
                                        @foreach($examenes as $index => $item)
                                            <tr data-repeat-row>
                                                <td><input type="text" name="examenes[{{ $index }}][codigo]" class="form-control" value="{{ $item['codigo'] ?? '' }}"></td>
                                                <td><input type="text" name="examenes[{{ $index }}][nombre]" class="form-control wide" value="{{ $item['nombre'] ?? '' }}"></td>
                                                <td><input type="text" name="examenes[{{ $index }}][lateralidad]" class="form-control" value="{{ $item['lateralidad'] ?? '' }}" placeholder="OD/OI/AO"></td>
                                                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" data-repeat-remove>Quitar</button></td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="box">
                            <div class="box-header">
                                <div class="consulta-section-title w-100">
                                    <h4 class="box-title mb-0">Recetas</h4>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-repeat-add="recetas">Agregar receta</button>
                                </div>
                            </div>
                            <div class="box-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered consulta-table">
                                        <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th>Vía</th>
                                            <th>Dosis</th>
                                            <th>Unidad</th>
                                            <th>Pauta</th>
                                            <th>Cantidad</th>
                                            <th>Total farmacia</th>
                                            <th>Observaciones</th>
                                            <th></th>
                                        </tr>
                                        </thead>
                                        <tbody data-repeat-list="recetas" data-next-index="{{ count($recetas) }}">
                                        @foreach($recetas as $index => $item)
                                            <tr data-repeat-row>
                                                <td><input type="text" name="recetas[{{ $index }}][producto]" class="form-control wide" value="{{ $item['producto'] ?? '' }}"></td>
                                                <td><input type="text" name="recetas[{{ $index }}][vias]" class="form-control" value="{{ $item['vias'] ?? '' }}"></td>
                                                <td><input type="text" name="recetas[{{ $index }}][dosis]" class="form-control" value="{{ $item['dosis'] ?? '' }}"></td>
                                                <td><input type="text" name="recetas[{{ $index }}][unidad]" class="form-control" value="{{ $item['unidad'] ?? '' }}"></td>
                                                <td><input type="text" name="recetas[{{ $index }}][pauta]" class="form-control" value="{{ $item['pauta'] ?? '' }}"></td>
                                                <td><input type="number" name="recetas[{{ $index }}][cantidad]" class="form-control" value="{{ $item['cantidad'] ?? '' }}"></td>
                                                <td><input type="number" name="recetas[{{ $index }}][total_farmacia]" class="form-control" value="{{ $item['total_farmacia'] ?? '' }}"></td>
                                                <td><textarea name="recetas[{{ $index }}][observaciones]" rows="2" class="form-control">{{ $item['observaciones'] ?? '' }}</textarea></td>
                                                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" data-repeat-remove>Quitar</button></td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="box">
                            <div class="box-header">
                                <div class="consulta-section-title w-100">
                                    <h4 class="box-title mb-0">PIO</h4>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-repeat-add="pio">Agregar medición</button>
                                </div>
                            </div>
                            <div class="box-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered consulta-table">
                                        <thead>
                                        <tr>
                                            <th>Tonómetro</th>
                                            <th>OD</th>
                                            <th>OI</th>
                                            <th>Patológico</th>
                                            <th>Hora</th>
                                            <th>Hora fin</th>
                                            <th>Observación</th>
                                            <th></th>
                                        </tr>
                                        </thead>
                                        <tbody data-repeat-list="pio" data-next-index="{{ count($pio) }}">
                                        @foreach($pio as $index => $item)
                                            <tr data-repeat-row>
                                                <td><input type="text" name="pio[{{ $index }}][tonometro]" class="form-control" value="{{ $item['tonometro'] ?? '' }}"></td>
                                                <td><input type="text" name="pio[{{ $index }}][od]" class="form-control" value="{{ $item['od'] ?? '' }}"></td>
                                                <td><input type="text" name="pio[{{ $index }}][oi]" class="form-control" value="{{ $item['oi'] ?? '' }}"></td>
                                                <td class="text-center">
                                                    <input type="checkbox" name="pio[{{ $index }}][po_patologico]" value="1" @checked((string) ($item['po_patologico'] ?? '0') === '1')>
                                                </td>
                                                <td><input type="time" name="pio[{{ $index }}][po_hora]" class="form-control" value="{{ $item['po_hora'] ?? '' }}"></td>
                                                <td><input type="time" name="pio[{{ $index }}][hora_fin]" class="form-control" value="{{ $item['hora_fin'] ?? '' }}"></td>
                                                <td><textarea name="pio[{{ $index }}][po_observacion]" rows="2" class="form-control">{{ $item['po_observacion'] ?? '' }}</textarea></td>
                                                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" data-repeat-remove>Quitar</button></td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mb-30">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fa fa-save me-5"></i>Guardar consulta
                            </button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </section>

    <template id="diagnosticos-row-template">
        <tr data-repeat-row>
            <td><input type="text" name="diagnosticos[__INDEX__][idDiagnostico]" class="form-control wide" placeholder="H25.0 - Catarata senil"></td>
            <td><input type="text" name="diagnosticos[__INDEX__][ojo]" class="form-control" placeholder="OD/OI/AO"></td>
            <td class="text-center"><input type="checkbox" name="diagnosticos[__INDEX__][evidencia]" value="1"></td>
            <td><input type="text" name="diagnosticos[__INDEX__][selector]" class="form-control"></td>
            <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" data-repeat-remove>Quitar</button></td>
        </tr>
    </template>

    <template id="examenes-row-template">
        <tr data-repeat-row>
            <td><input type="text" name="examenes[__INDEX__][codigo]" class="form-control"></td>
            <td><input type="text" name="examenes[__INDEX__][nombre]" class="form-control wide"></td>
            <td><input type="text" name="examenes[__INDEX__][lateralidad]" class="form-control" placeholder="OD/OI/AO"></td>
            <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" data-repeat-remove>Quitar</button></td>
        </tr>
    </template>

    <template id="recetas-row-template">
        <tr data-repeat-row>
            <td><input type="text" name="recetas[__INDEX__][producto]" class="form-control wide"></td>
            <td><input type="text" name="recetas[__INDEX__][vias]" class="form-control"></td>
            <td><input type="text" name="recetas[__INDEX__][dosis]" class="form-control"></td>
            <td><input type="text" name="recetas[__INDEX__][unidad]" class="form-control"></td>
            <td><input type="text" name="recetas[__INDEX__][pauta]" class="form-control"></td>
            <td><input type="number" name="recetas[__INDEX__][cantidad]" class="form-control"></td>
            <td><input type="number" name="recetas[__INDEX__][total_farmacia]" class="form-control"></td>
            <td><textarea name="recetas[__INDEX__][observaciones]" rows="2" class="form-control"></textarea></td>
            <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" data-repeat-remove>Quitar</button></td>
        </tr>
    </template>

    <template id="pio-row-template">
        <tr data-repeat-row>
            <td><input type="text" name="pio[__INDEX__][tonometro]" class="form-control"></td>
            <td><input type="text" name="pio[__INDEX__][od]" class="form-control"></td>
            <td><input type="text" name="pio[__INDEX__][oi]" class="form-control"></td>
            <td class="text-center"><input type="checkbox" name="pio[__INDEX__][po_patologico]" value="1"></td>
            <td><input type="time" name="pio[__INDEX__][po_hora]" class="form-control"></td>
            <td><input type="time" name="pio[__INDEX__][hora_fin]" class="form-control"></td>
            <td><textarea name="pio[__INDEX__][po_observacion]" rows="2" class="form-control"></textarea></td>
            <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger" data-repeat-remove>Quitar</button></td>
        </tr>
    </template>
@endsection

@push('scripts')
    <script src="/js/pages/consultas-editor.js"></script>
@endpush
