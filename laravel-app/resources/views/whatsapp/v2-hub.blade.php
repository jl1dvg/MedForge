@extends('layouts.medforge')

@php
    $sectionKey = (string) ($section ?? 'dashboard');
    $sectionMeta = is_array($sectionMeta ?? null) ? $sectionMeta : ['title' => 'Dashboard', 'goal' => '', 'scope' => []];
    $statusCards = is_array($statusCards ?? null) ? $statusCards : [];
    $phases = is_array($phases ?? null) ? $phases : [];
    $links = [
        'chat legacy' => '/whatsapp/chat',
        'campaigns' => '/v2/whatsapp/campaigns',
        'templates' => '/v2/whatsapp/templates',
        'dashboard' => '/v2/whatsapp/dashboard',
        'flowmaker' => '/v2/whatsapp/flowmaker',
    ];
@endphp

@push('styles')
<style>
    .wa-v2-nav .btn {
        border-radius: 999px;
    }

    .wa-v2-card {
        border: 1px solid rgba(15, 23, 42, .08);
        border-radius: 16px;
        background: #fff;
        height: 100%;
    }

    .wa-v2-card__title {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: #64748b;
    }

    .wa-v2-card__value {
        font-size: 1.1rem;
        font-weight: 700;
        color: #0f172a;
    }

    .wa-v2-list li + li {
        margin-top: .45rem;
    }
</style>
@endpush

@section('content')
<section class="content">
    <div class="row g-3">
        <div class="col-12">
            <div class="box mb-15">
                <div class="box-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-15">
                        <div>
                            <span class="badge bg-light text-primary mb-10">WHATSAPP V2</span>
                            <h2 class="mb-5">{{ $sectionMeta['title'] }}</h2>
                            <p class="text-muted mb-0">{{ $sectionMeta['goal'] }}</p>
                        </div>
                        <div class="text-md-end">
                            <a href="{{ $planDocPath }}" class="btn btn-outline-primary btn-sm">
                                Ver plan de migración
                            </a>
                        </div>
                    </div>

                    <div class="wa-v2-nav d-flex flex-wrap gap-10 mt-20">
                        @foreach($links as $key => $href)
                            @php
                                $normalizedKey = str_replace(' ', '_', strtolower($key));
                                $isLegacyChat = $normalizedKey === 'chat_legacy';
                                $isActive = !$isLegacyChat && $sectionKey === $normalizedKey;
                            @endphp
                            <a href="{{ $href }}" class="btn {{ $isActive ? 'btn-primary' : 'btn-light' }}">
                                {{ ucfirst($key) }}
                                @if($isLegacyChat)
                                    <span class="badge bg-warning-light text-warning ms-5">legacy</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        @foreach($statusCards as $card)
            <div class="col-xl-4 col-md-6 col-12">
                <div class="wa-v2-card p-20">
                    <div class="d-flex justify-content-between align-items-start gap-10">
                        <div>
                            <div class="wa-v2-card__title">{{ $card['label'] }}</div>
                            <div class="wa-v2-card__value mt-5">{{ $card['state'] }}</div>
                        </div>
                        <span class="badge bg-{{ $card['tone'] }}-light text-{{ $card['tone'] }}">{{ $card['state'] }}</span>
                    </div>
                    <p class="text-muted mb-0 mt-15">{{ $card['detail'] }}</p>
                </div>
            </div>
        @endforeach

        <div class="col-xl-7 col-12">
            <div class="box mb-15">
                <div class="box-header with-border">
                    <h4 class="box-title mb-0">Alcance inmediato de esta sección</h4>
                </div>
                <div class="box-body">
                    <ul class="wa-v2-list mb-0">
                        @foreach(($sectionMeta['scope'] ?? []) as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-xl-5 col-12">
            <div class="box mb-15">
                <div class="box-header with-border">
                    <h4 class="box-title mb-0">Fases activas del roadmap</h4>
                </div>
                <div class="box-body">
                    <ol class="mb-0 ps-20">
                        @foreach($phases as $phase)
                            <li class="mb-10">{{ $phase }}</li>
                        @endforeach
                    </ol>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
