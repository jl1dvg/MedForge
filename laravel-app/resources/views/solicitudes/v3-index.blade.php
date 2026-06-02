@extends('layouts.medforge')

@push('styles')
    @vite(['resources/css/solicitudes-v3.css'])
@endpush

@section('content')
<div
    id="solicitudes-v3-root"
    data-config="{{ json_encode($appConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) }}"
></div>
@endsection

@push('scripts')
    @vite(['resources/js/solicitudes-v3/main.tsx'])
@endpush
