@extends('layouts.medforge')

@section('content')
<div class="content-header">
    <div class="d-flex align-items-center justify-content-between">
        <div>
            <h3 class="page-title mb-2">Documento de consentimiento / atención</h3>
            <p class="text-muted mb-0">Resumen del último check-in biométrico del paciente.</p>
        </div>
        <div>
            <a href="/pacientes/certificaciones" class="btn btn-secondary"><i class="mdi mdi-arrow-left"></i> Volver</a>
            <button type="button" class="btn btn-primary ms-2" onclick="window.print()"><i class="mdi mdi-printer"></i> Imprimir</button>
        </div>
    </div>
</div>

<section class="content">
    <div class="box">
        <div class="box-body">
            @if(!$canRenderConsent)
                <div class="alert alert-danger">
                    <strong>Verificación rechazada.</strong> Este registro no puede generar un consentimiento automático. Proceda con la validación manual según el protocolo interno.
                </div>
            @endif

            @if($checkin)
            <div class="row mb-4">
                <div class="col-md-6">
                    <h5 class="mb-1">Datos del paciente</h5>
                    <p class="mb-0"><strong>Nombre:</strong> {{ $checkin['full_name'] ?? 'Sin registro' }}</p>
                    <p class="mb-0"><strong>Historia clínica:</strong> {{ $checkin['patient_id'] ?? '' }}</p>
                    @if(!empty($checkin['cedula']))
                        <p class="mb-0"><strong>Cédula registrada:</strong> {{ $checkin['cedula'] }}</p>
                    @endif
                    @if(!empty($checkin['afiliacion']))
                        <p class="mb-0"><strong>Afiliación:</strong> {{ $checkin['afiliacion'] }}</p>
                    @endif
                </div>
                <div class="col-md-6">
                    <h5 class="mb-1">Detalle del check-in</h5>
                    <p class="mb-0"><strong>Fecha y hora:</strong> {{ $checkin['created_at'] ?? '' }}</p>
                    <p class="mb-0"><strong>Resultado:</strong> {{ ucfirst($checkin['verification_result'] ?? '') }}</p>
                    @if(isset($checkin['verified_face_score']))
                        <p class="mb-0"><strong>Puntaje facial:</strong> {{ number_format((float) $checkin['verified_face_score'], 2) }}</p>
                    @endif
                    @if(isset($checkin['created_by']))
                        <p class="mb-0"><strong>Usuario que registró:</strong> {{ $checkin['created_by'] }}</p>
                    @endif
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-6">
                    <h5 class="mb-2">Documento de identidad</h5>
                    <p class="mb-1"><strong>Número:</strong> {{ $checkin['document_number'] ?? '' }}</p>
                    <p class="mb-3"><strong>Tipo:</strong> {{ strtoupper($checkin['document_type'] ?? '') }}</p>
                    <div class="d-flex gap-3 flex-wrap">
                        @if(!empty($checkin['document_front_path']))
                            <div>
                                <small class="text-muted d-block">Anverso</small>
                                <img src="/{{ ltrim($checkin['document_front_path'], '/') }}" alt="Documento anverso" class="img-thumbnail" style="max-width: 240px;">
                            </div>
                        @endif
                        @if(!empty($checkin['document_back_path']))
                            <div>
                                <small class="text-muted d-block">Reverso</small>
                                <img src="/{{ ltrim($checkin['document_back_path'], '/') }}" alt="Documento reverso" class="img-thumbnail" style="max-width: 240px;">
                            </div>
                        @endif
                    </div>
                </div>
                <div class="col-md-6">
                    <h5 class="mb-2">Firma del consentimiento</h5>
                    @if(!empty($checkin['signature_path']))
                        <div class="border rounded p-3 bg-white">
                            <img src="/{{ ltrim($checkin['signature_path'], '/') }}" alt="Firma del paciente" class="img-fluid">
                        </div>
                    @else
                        <p class="text-muted">No se registró firma manuscrita en la certificación.</p>
                    @endif
                </div>
            </div>

            @if(!empty($checkin['metadata']['face_capture']))
                <div class="mb-4">
                    <h5 class="mb-2">Captura facial del check-in</h5>
                    <img src="/{{ ltrim($checkin['metadata']['face_capture'], '/') }}" alt="Rostro capturado" class="img-thumbnail" style="max-width: 280px;">
                </div>
            @endif
            @endif

            <div class="alert alert-info">
                <p class="mb-1"><strong>Declaración:</strong> El paciente confirma la veracidad de los datos presentados y autoriza la atención según los protocolos vigentes.</p>
                <p class="mb-0"><small>Documento generado automáticamente por el módulo de certificación biométrica de MedForge.</small></p>
            </div>
        </div>
    </div>
</section>
@endsection
