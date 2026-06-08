@extends('layouts.medforge')

@php $pageTitle = 'Panel Comercial CRM'; @endphp

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">
@vite('resources/css/crm.css')
@endpush

@section('content')
<div id="crm-root" style="height:100vh;display:flex;flex-direction:column;min-height:0;"></div>
@endsection

@push('scripts')
@vite('resources/js/crm/main.tsx')
@endpush
