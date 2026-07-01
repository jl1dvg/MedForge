@extends('layouts.medforge')

@php
    $disableWelcomeTour = true;
    $appConfig = [
        'endpoints' => [
            'cirugias' => '/v2/reportes/api/cirugias',
            'imagenes' => '/v2/reportes/api/imagenes',
        ],
        'sedeOptions' => $sedeOptions ?? [],
    ];
@endphp

@section('content')
    <section class="content">
        <div
            id="reportes-unified-root"
            data-config="{{ json_encode($appConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"
        ></div>
    </section>
@endsection

@push('scripts')
    @vite('resources/js/reportes/main.jsx')
@endpush
