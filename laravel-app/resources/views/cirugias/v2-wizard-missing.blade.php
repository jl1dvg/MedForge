@extends('layouts.medforge')

@section('content')
    <div class="content-header">
        <div class="alert alert-danger w-100">
            <strong>Protocolo no disponible.</strong>
            @if(!empty($formId) && !empty($hcNumber))
                No se encontró información para el protocolo <code>{{ (string) $formId }}</code> del paciente <code>{{ (string) $hcNumber }}</code>.
            @else
                Faltan parámetros para cargar el protocolo solicitado.
            @endif
        </div>
    </div>
@endsection
