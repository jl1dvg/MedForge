@extends('layouts.medforge')

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.7.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip-utils/0.1.0/jszip-utils.min.js"></script>
@endpush

@section('content')
    @php
        $legacyViewPath = base_path('../modules/examenes/views/examenes.php');
        $frontendMode = $frontendMode ?? 'legacy';
    @endphp

    @if ($frontendMode !== 'native')
        <section class="content">
            <div class="alert alert-warning" style="margin-bottom: 12px;">
                Exámenes v2 está en modo <strong>{{ strtoupper($frontendMode) }}</strong>: se renderiza frontend legacy dentro del layout Laravel.
            </div>
        </section>
    @endif

    @if (is_file($legacyViewPath))
        @php include $legacyViewPath; @endphp
    @else
        <section class="content">
            <div class="alert alert-danger">
                No se encontró la vista legacy de exámenes en <code>{{ $legacyViewPath }}</code>.
            </div>
        </section>
    @endif
@endsection
