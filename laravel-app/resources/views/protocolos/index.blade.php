@extends('layouts.medforge')

@php
    $disableWelcomeTour = true;
@endphp

@section('content')
    <section class="content">
        <div
            id="protocolos-index-root"
            data-config="{{ json_encode($appConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"
        ></div>
    </section>
@endsection

@push('scripts')
    @vite('resources/js/protocolos/main.jsx')
@endpush
