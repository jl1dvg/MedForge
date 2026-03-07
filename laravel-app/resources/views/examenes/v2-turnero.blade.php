@extends('layouts.medforge')

@section('content')
    @php
        $legacyViewPath = base_path('../modules/examenes/views/turnero.php');
    @endphp

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
