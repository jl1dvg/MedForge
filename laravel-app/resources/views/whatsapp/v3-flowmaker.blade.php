@extends('layouts.medforge')

@section('title', $pageTitle ?? 'WhatsApp V3 - Flowmaker')

@section('content')
    <script>
        window.__FLOWMAKER_V3__ = {
            csrfToken: @json(csrf_token()),
            routes: {
                fallbackV2: @json('/v2/whatsapp/flowmaker'),
                contract: @json('/v2/whatsapp/api/flowmaker/contract'),
                publish: @json('/v2/whatsapp/api/flowmaker/publish'),
                simulate: @json('/v2/whatsapp/api/flowmaker/simulate'),
                compare: @json('/v2/whatsapp/api/flowmaker/compare'),
                readiness: @json('/v2/whatsapp/api/flowmaker/readiness'),
                shadowRuns: @json('/v2/whatsapp/api/flowmaker/shadow-runs'),
                shadowSummary: @json('/v2/whatsapp/api/flowmaker/shadow-summary'),
                templates: @json('/v2/whatsapp/api/templates'),
                knowledgeBase: @json('/v2/whatsapp/api/knowledge-base'),
                mediaUpload: @json('/v2/whatsapp/api/media/upload'),
            },
        };
    </script>

    <div id="flowmaker-v3-root"></div>

    @vite('resources/js/whatsapp/flowmaker-v3/main.jsx')
@endsection
