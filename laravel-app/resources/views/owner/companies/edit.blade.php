@extends('layouts.medforge')

@section('content')
    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h3 class="page-title">Panel de Propietario</h3>
                <div class="d-inline-block align-items-center">
                    <nav>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                            <li class="breadcrumb-item"><a href="/owner/companies">Empresas</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ $company->name }}</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="/owner/companies/{{ $company->id }}">
            @csrf
            @method('PUT')

            <div class="row">
                <div class="col-lg-7">
                    <div class="box">
                        <div class="box-header with-border">
                            <h4 class="box-title mb-0">
                                <i class="mdi mdi-office-building me-1"></i>
                                {{ $company->name }}
                            </h4>
                        </div>
                        <div class="box-body">

                            {{-- Servicio activo --}}
                            <div class="mb-4">
                                <label class="form-label fw-600">Estado del servicio</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="is_active"
                                           name="is_active" value="1"
                                           @checked($company->is_active)>
                                    <label class="form-check-label" for="is_active">
                                        Servicio activo
                                        <small class="text-muted d-block">Si se desactiva, la empresa queda en modo solo lectura completo independientemente de las demás opciones.</small>
                                    </label>
                                </div>
                            </div>

                            <hr>

                            {{-- Modo solo lectura --}}
                            <div class="mb-3">
                                <label class="form-label fw-600">Modo solo lectura</label>

                                <div class="form-check mb-2">
                                    <input class="form-check-input mode-radio" type="radio"
                                           name="service_mode" id="mode_off" value="off"
                                           @checked($company->service_mode === 'off')>
                                    <label class="form-check-label" for="mode_off">
                                        <strong>OFF</strong> — Forzar modo normal (acepta escrituras siempre, ignora las fechas)
                                    </label>
                                </div>

                                <div class="form-check mb-2">
                                    <input class="form-check-input mode-radio" type="radio"
                                           name="service_mode" id="mode_auto" value="auto"
                                           @checked($company->service_mode === 'auto')>
                                    <label class="form-check-label" for="mode_auto">
                                        <strong>AUTO</strong> — Solo lectura automático dentro de la ventana de fechas configurada
                                    </label>
                                </div>

                                <div class="form-check mb-2">
                                    <input class="form-check-input mode-radio" type="radio"
                                           name="service_mode" id="mode_on" value="on"
                                           @checked($company->service_mode === 'on')>
                                    <label class="form-check-label" for="mode_on">
                                        <strong>ON</strong> — Forzar solo lectura ahora (bloquea escrituras independientemente de fechas)
                                    </label>
                                </div>
                            </div>

                            {{-- Ventana de fechas (solo visible en modo auto) --}}
                            <div id="date-window" class="ps-3 border-start border-2 border-info mt-3"
                                 style="{{ $company->service_mode !== 'auto' ? 'display:none' : '' }}">
                                <div class="row g-3 mb-3">
                                    <div class="col-sm-6">
                                        <label for="readonly_start" class="form-label">Inicio del período</label>
                                        <input type="datetime-local" class="form-control" id="readonly_start"
                                               name="readonly_start"
                                               value="{{ $company->readonly_start?->format('Y-m-d\TH:i') }}">
                                    </div>
                                    <div class="col-sm-6">
                                        <label for="readonly_end" class="form-label">Fin del período</label>
                                        <input type="datetime-local" class="form-control" id="readonly_end"
                                               name="readonly_end"
                                               value="{{ $company->readonly_end?->format('Y-m-d\TH:i') }}">
                                    </div>
                                </div>
                            </div>

                            <hr>

                            {{-- Mensaje --}}
                            <div class="mb-3">
                                <label for="readonly_message" class="form-label fw-600">Mensaje de aviso (solo lectura)</label>
                                <textarea class="form-control" id="readonly_message" name="readonly_message"
                                          rows="3" maxlength="500"
                                          placeholder="Sistema en modo solo lectura. No se pueden guardar cambios en este momento.">{{ old('readonly_message', $company->readonly_message) }}</textarea>
                                <div class="form-text">Se muestra al usuario cuando intenta guardar durante el período de solo lectura.</div>
                            </div>

                        </div>
                        <div class="box-footer d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="mdi mdi-content-save me-1"></i>Guardar cambios
                            </button>
                            <a href="/owner/companies" class="btn btn-outline-secondary">Cancelar</a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="box box-outline-info">
                        <div class="box-header with-border">
                            <h5 class="box-title mb-0"><i class="mdi mdi-information-outline me-1"></i>Referencia rápida</h5>
                        </div>
                        <div class="box-body" style="font-size:13px">
                            <p><strong>OFF</strong>: la empresa puede escribir sin restricción, aunque hoy sea 15 de julio.</p>
                            <p><strong>AUTO</strong>: el sistema bloquea escrituras automáticamente dentro de la ventana de fechas. Fuera de ella, todo funciona normal.</p>
                            <p><strong>ON</strong>: bloqueo inmediato. Útil para activar manualmente sin tocar fechas.</p>
                            <hr>
                            <p class="mb-0 text-muted">Los bots, crons y la integración de SigCenter nunca se ven afectados — solo las acciones humanas desde el navegador.</p>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </section>

    @push('scripts')
    <script>
        document.querySelectorAll('.mode-radio').forEach(function (radio) {
            radio.addEventListener('change', function () {
                document.getElementById('date-window').style.display =
                    this.value === 'auto' ? '' : 'none';
            });
        });
    </script>
    @endpush
@endsection
