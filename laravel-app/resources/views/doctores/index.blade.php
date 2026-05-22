@extends('layouts.medforge')

@section('content')
<div class="container-full">
    {{-- Content Header --}}
    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h4 class="page-title">Doctores</h4>
                <div class="d-inline-block align-items-center">
                    <nav>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/"><i class="mdi mdi-home-outline"></i></a></li>
                            <li class="breadcrumb-item active" aria-current="page">Doctores</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    {{-- Main content --}}
    <section class="content">
        <div class="row mb-20 align-items-center">
            <div class="col-12 col-xl-8">
                <h4 class="mb-5 fw-600">Directorio de doctores</h4>
                <p class="text-fade mb-0">Se muestran los usuarios con especialidad o rol asignado como doctor.</p>
            </div>
            <div class="col-12 col-xl-4 text-xl-end text-start mt-10 mt-xl-0">
                <span class="badge badge-primary-light px-20 py-10 fs-16">
                    {{ $totalDoctors }}
                    {{ $totalDoctors === 1 ? 'doctor registrado' : 'doctores registrados' }}
                </span>
            </div>
        </div>

        <div class="row">
            @if (empty($doctors))
                <div class="col-12">
                    <div class="box">
                        <div class="box-body text-center py-60">
                            <div class="avatar avatar-xxl bg-primary-light d-inline-flex align-items-center justify-content-center mb-20">
                                <i class="mdi mdi-stethoscope fs-40 text-primary"></i>
                            </div>
                            <h4 class="mb-10">Aún no hay doctores para mostrar</h4>
                            <p class="text-fade mb-0">Los usuarios con una especialidad o rol de doctor aparecerán automáticamente en esta lista.</p>
                        </div>
                    </div>
                </div>
            @else
                @foreach ($doctors as $index => $doctor)
                    @php
                        $profilePhoto = $doctor['profile_photo'] ?? null;
                        $coverUrl = \App\Modules\Doctores\Services\DoctoresService::resolveProfilePhotoUrl($profilePhoto);
                        if (!$coverUrl) {
                            $coverUrl = asset(sprintf('images/avatar/375x200/%d.jpg', ($index % 8) + 1));
                        }
                        $avatarUrl = \App\Modules\Doctores\Services\DoctoresService::resolveProfilePhotoUrl($profilePhoto);
                        if (!$avatarUrl) {
                            $avatarUrl = asset(sprintf('images/avatar/%d.jpg', ($index % 9) + 1));
                        }
                        $statusVariant = $doctor['status_variant'] ?? null;
                        $statusLabel = $doctor['status'] ?? null;
                    @endphp
                    <div class="col-12 col-lg-4">
                        <div class="box {{ $statusVariant ? 'ribbon-box' : '' }}">
                            @if ($statusVariant && $statusLabel)
                                <div class="ribbon-two ribbon-two-{{ $statusVariant }}">
                                    <span>{{ $statusLabel }}</span>
                                </div>
                            @endif
                            <div class="box-header no-border p-0">
                                <a href="{{ $doctor['detail_url'] }}">
                                    <img class="img-fluid" src="{{ $coverUrl }}" alt="{{ $doctor['display_name'] }}">
                                </a>
                            </div>
                            <div class="box-body">
                                <div class="text-center">
                                    <img src="{{ $avatarUrl }}"
                                         class="avatar avatar-xl rounded-circle border-3 border-white shadow mb-15"
                                         alt="{{ $doctor['display_name'] }}">

                                    <div class="user-contact list-inline text-center mb-10">
                                        @if (!empty($doctor['email']))
                                            <a href="mailto:{{ $doctor['email'] }}"
                                               class="btn btn-circle mb-5 btn-warning" title="Enviar correo">
                                                <i class="fa fa-envelope"></i>
                                            </a>
                                        @endif
                                        <a href="{{ $doctor['detail_url'] }}"
                                           class="btn btn-circle mb-5 btn-primary-light" title="Ver detalles">
                                            <i class="fa fa-id-card-o"></i>
                                        </a>
                                    </div>

                                    <h3 class="my-10">
                                        <a href="{{ $doctor['detail_url'] }}">
                                            {{ $doctor['display_name'] }}
                                        </a>
                                    </h3>
                                    <h6 class="user-info mt-0 mb-10 text-fade">
                                        {{ $doctor['especialidad'] ?? 'Especialidad no registrada' }}
                                    </h6>
                                    @php
                                        $performanceSummary = is_array($doctor['performance_summary'] ?? null) ? $doctor['performance_summary'] : [];
                                    @endphp
                                    @if (!empty($performanceSummary))
                                        <div class="mb-10">
                                            <div class="d-flex align-items-center justify-content-center gap-10 flex-wrap">
                                                <div>
                                                    @php
                                                        $stars = (float) ($performanceSummary['stars'] ?? 0);
                                                    @endphp
                                                    @for ($i = 1; $i <= 5; $i++)
                                                        @if ($stars >= $i)
                                                            <i class="fa fa-star text-warning"></i>
                                                        @elseif ($stars >= ($i - 0.5))
                                                            <i class="fa fa-star-half-o text-warning"></i>
                                                        @else
                                                            <i class="fa fa-star-o text-muted"></i>
                                                        @endif
                                                    @endfor
                                                </div>
                                                <span class="badge badge-warning-light text-warning px-10 py-5">
                                                    {{ $performanceSummary['label'] ?? 'Sin score' }}
                                                </span>
                                            </div>
                                            <p class="mb-0 text-fade fs-12 mt-5">
                                                {{ $performanceSummary['summary'] ?? '' }}
                                            </p>
                                        </div>
                                    @endif
                                    @if (!empty($doctor['subespecialidad']))
                                        <p class="mb-5 text-fade">{{ $doctor['subespecialidad'] }}</p>
                                    @endif
                                    @if (!empty($doctor['sede']))
                                        <p class="mb-0 text-fade">
                                            <i class="mdi mdi-hospital-building me-5"></i>
                                            {{ $doctor['sede'] }}
                                        </p>
                                    @endif
                                    @php
                                        $quickStats = is_array($doctor['performance_quick_stats'] ?? null) ? $doctor['performance_quick_stats'] : [];
                                    @endphp
                                    @if (!empty($quickStats))
                                        <div class="d-flex justify-content-center gap-5 mt-10 flex-wrap">
                                            @foreach ($quickStats as $stat)
                                                <span class="badge badge-light px-10 py-5">
                                                    {{ $stat['label'] ?? '' }}:
                                                    {{ $stat['value'] ?? '' }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif

                                    <div class="d-flex justify-content-center gap-10 mt-15 flex-wrap">
                                        <a href="{{ $doctor['detail_url'] }}"
                                           class="btn btn-sm btn-primary">
                                            <i class="fa fa-eye me-5"></i> Ver detalles
                                        </a>
                                        @if (!empty($doctor['email']))
                                            <a href="mailto:{{ $doctor['email'] }}"
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fa fa-paper-plane me-5"></i> Contactar
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </section>
</div>
@endsection
