@extends('layouts.medforge')

@php
    $disableWelcomeTour = true;

    $afiliacionOptions = is_array($afiliacionOptions ?? null) ? $afiliacionOptions : [];
    $afiliacionCategoriaOptions = is_array($afiliacionCategoriaOptions ?? null) ? $afiliacionCategoriaOptions : [];
    $sedeOptions = is_array($sedeOptions ?? null) ? $sedeOptions : [];
    $fechaInicioDefaultValue = (string) ($fechaInicioDefault ?? '');
    $fechaFinDefaultValue = (string) ($fechaFinDefault ?? '');

    $currentUser = $currentUser ?? null;

    $appConfig = [
        'afiliacionOptions'         => $afiliacionOptions,
        'afiliacionCategoriaOptions' => $afiliacionCategoriaOptions,
        'sedeOptions'               => $sedeOptions,
        'fechaInicioDefault'        => $fechaInicioDefaultValue,
        'fechaFinDefault'           => $fechaFinDefaultValue,
        'currentUser'               => $currentUser ? [
            'id'   => $currentUser->id ?? null,
            'name' => $currentUser->name ?? '',
        ] : null,
        'endpoints' => [
            'datatable'       => '/v2/cirugias/datatable',
            'protocolo'       => '/v2/cirugias/protocolo',
            'staffOptions'    => '/v2/cirugias/staff-options',
            'wizard'          => '/v2/cirugias/wizard',
            'printed'         => '/v2/cirugias/protocolo/printed',
            'status'          => '/v2/cirugias/protocolo/status',
            'autosave'        => '/v2/cirugias/wizard/autosave',
            'guardar'         => '/v2/cirugias/wizard/guardar',
            'scrapeDerivacion' => '/v2/cirugias/wizard/scrape-derivacion',
        ],
    ];
@endphp

@section('content')
    <section class="content">
        <div
            id="cirugias-index-root"
            data-config="{{ json_encode($appConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"
        ></div>
    </section>
@endsection

@push('scripts')
    @vite('resources/js/cirugias/main.jsx')
@endpush
