@extends('layouts.medforge')

@section('content')
    <div id="pac-root"></div>
@endsection

@push('scripts')
    @vite('resources/js/pacientes/main.tsx')
@endpush
