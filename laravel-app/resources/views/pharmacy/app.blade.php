@extends('layouts.medforge')

@php $pageTitle = 'Farmacia Pro'; @endphp

@push('styles')
@vite('resources/css/app.css')
@endpush

@section('content')
<div id="pharmacy-root" style="height:100%;min-height:600px;"></div>
@endsection

@push('scripts')
@vite('resources/js/pharmacy/main.tsx')
@endpush
