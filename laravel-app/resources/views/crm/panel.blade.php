@extends('layouts.medforge')

@php $pageTitle = 'Panel Comercial CRM'; @endphp

@push('styles')
@vite('resources/css/app.css')
@endpush

@section('content')
<div id="crm-root"></div>
@endsection

@push('scripts')
@vite('resources/js/crm/main.tsx')
@endpush
