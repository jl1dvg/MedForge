@extends('layouts.medforge')

@php
    $opcionesMedicamentosSafe = is_array($opcionesMedicamentos ?? null) ? $opcionesMedicamentos : [];
    $viasDisponiblesSafe = is_array($viasDisponibles ?? null) ? $viasDisponibles : [];
    $responsablesMedicamentosSafe = is_array($responsablesMedicamentos ?? null) ? $responsablesMedicamentos : [];

    $medicamentoOptionsHTML = implode('', array_map(static function (array $m): string {
        $id = htmlspecialchars((string) ($m['id'] ?? ''), ENT_QUOTES, 'UTF-8');
        $nombre = htmlspecialchars((string) ($m['medicamento'] ?? ''), ENT_QUOTES, 'UTF-8');
        return "<option value='{$id}'>{$nombre}</option>";
    }, $opcionesMedicamentosSafe));

    $viaOptionsHTML = implode('', array_map(static function (string $v): string {
        $value = htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        return "<option value='{$value}'>{$value}</option>";
    }, $viasDisponiblesSafe));

    $responsableOptionsHTML = implode('', array_map(static function (string $r): string {
        $value = htmlspecialchars($r, ENT_QUOTES, 'UTF-8');
        return "<option value='{$value}'>{$value}</option>";
    }, $responsablesMedicamentosSafe));
@endphp

@section('content')
    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h3 class="page-title">Modificar Información del Procedimiento</h3>
                <div class="d-inline-block align-items-center">
                    <nav>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                            <li class="breadcrumb-item"><a href="/v2/cirugias">Reporte de Cirugías</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Editar protocolo</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="box">
            <div class="box-header with-border">
                <h4 class="box-title">Modificar información del procedimiento</h4>
            </div>
            <div class="box-body wizard-content">
                <form action="/v2/cirugias/wizard/guardar" method="POST" class="tab-wizard vertical wizard-circle">
                    <input type="hidden" name="form_id" value="{{ (string) ($cirugia->form_id ?? '') }}">
                    <input type="hidden" name="hc_number" value="{{ (string) ($cirugia->hc_number ?? '') }}">

                    @include('cirugias.partials.paciente')
                    @include('cirugias.partials.procedimiento')
                    @include('cirugias.partials.staff')
                    @include('cirugias.partials.anestesia')
                    @include('cirugias.partials.operatorio')
                    @include('cirugias.partials.insumos')
                    @include('cirugias.partials.medicamentos')
                    @include('cirugias.partials.resumen')
                </form>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="/assets/vendor_components/datatable/datatables.min.js"></script>
    <script src="/assets/vendor_components/tiny-editable/mindmup-editabletable.js"></script>
    <script src="/assets/vendor_components/tiny-editable/numeric-input-example.js"></script>
    <script src="/assets/vendor_components/jquery-steps-master/build/jquery.steps.js"></script>
    <script src="/assets/vendor_components/jquery-validation-1.17.0/dist/jquery.validate.min.js"></script>
    <script src="/js/pages/steps.js"></script>
    <script>
        window.cirugiasEndpoints = {
            datatable: '/v2/cirugias/datatable',
            wizard: '/v2/cirugias/wizard',
            protocolo: '/v2/cirugias/protocolo',
            printed: '/v2/cirugias/protocolo/printed',
            status: '/v2/cirugias/protocolo/status',
            autosave: '/v2/cirugias/wizard/autosave',
            scrapeDerivacion: '/v2/cirugias/wizard/scrape-derivacion'
        };
        const afiliacionCirugia = @json(strtolower((string) ($cirugia->afiliacion ?? '')));
        const insumosDisponiblesJSON = @json($insumosDisponibles ?? []);
        const categoriasInsumos = @json($categoriasInsumos ?? []);
        const categorias = categoriasInsumos.map(cat => ({
            value: cat,
            label: String(cat).replaceAll('_', ' ').replace(/\b\w/g, c => c.toUpperCase())
        }));
        const categoriaOptionsHTML = categorias.map(opt => `<option value="${opt.value}">${opt.label}</option>`).join('');
        const medicamentoOptionsHTML = @json($medicamentoOptionsHTML);
        const viaOptionsHTML = @json($viaOptionsHTML);
        const responsableOptionsHTML = @json($responsableOptionsHTML);
    </script>
    <script src="/js/modules/cirugias_wizard.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@endpush
