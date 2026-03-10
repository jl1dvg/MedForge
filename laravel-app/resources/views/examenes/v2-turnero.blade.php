@extends('layouts.medforge')

@section('content')
    @php
        $legacyViewPath = base_path('../modules/examenes/views/turnero.php');
        $frontendMode = $frontendMode ?? 'legacy';
    @endphp

    @if ($frontendMode !== 'native')
        <section class="content">
            <div class="alert alert-warning" style="margin-bottom: 12px;">
                Turnero v2 está en modo <strong>{{ strtoupper($frontendMode) }}</strong>: se renderiza frontend legacy dentro del layout Laravel.
            </div>
        </section>
    @endif

    @if (is_file($legacyViewPath))
        @php include $legacyViewPath; @endphp
    @else
        <section class="content">
            <div class="alert alert-danger">
                No se encontró la vista legacy del turnero de exámenes en <code>{{ $legacyViewPath }}</code>.
            </div>
        </section>
    @endif
@endsection
