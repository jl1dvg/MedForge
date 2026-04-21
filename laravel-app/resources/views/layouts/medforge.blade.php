<!DOCTYPE html>
<html lang="es">
<head>
    @php
        $hasMedforgeViteBuild = \App\Modules\Shared\Support\MedforgeAssets::hasViteBuild();
    @endphp
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="icon" href="/images/favicon.ico">

    <title>MedForge{{ isset($pageTitle) && $pageTitle !== '' ? ' - ' . $pageTitle : '' }}</title>

    @if ($hasMedforgeViteBuild)
        @vite('resources/css/medforge.css')
    @else
        <link rel="stylesheet" href="/css/vendors_css.css">
        <link rel="stylesheet" href="/css/horizontal-menu.css">
        <link rel="stylesheet" href="/css/style.css">
        <link rel="stylesheet" href="/css/skin_color.css">
        <link rel="stylesheet" href="/css/pages/medforge-datatables.css">
    @endif
    <link rel="stylesheet" href="/css/feedback-widget.css">

    @stack('styles')
</head>
<body class="hold-transition light-skin sidebar-mini theme-primary fixed">
@php
    $appNavigation = \App\Modules\Shared\Support\MedforgeNavigation::build(request());
@endphp
<div class="wrapper">
    @include('layouts.partials.header')
    @include('layouts.partials.navbar')
    @if (!empty($whatsappNotificationPanelEnabled))
        @include('layouts.partials.notification_panel')
    @endif

    <div class="content-wrapper">
        <div class="container-full">
            @yield('content')
        </div>
    </div>

    @include('layouts.partials.feedback_widget')

    <footer class="main-footer">
        <script>document.write(new Date().getFullYear())</script>
        <a href="https://www.consulmed.me/">Consulmed. Empowering EHR & Digital Medical Support</a>. All Rights Reserved.
    </footer>
</div>

@php
    $skipDefaultVendorScripts = (bool) ($skipDefaultVendorScripts ?? false);
@endphp

@if ($hasMedforgeViteBuild)
    @if (!$skipDefaultVendorScripts)
        <script src="/assets/vendor_components/jquery/jquery.min.js"></script>
        <script src="/assets/vendor_components/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
        @vite('resources/js/medforge.js')
    @endif
@else
    @if (!$skipDefaultVendorScripts)
        <script src="/js/vendors.min.js"></script>
        <script src="/js/template.js"></script>
    @endif
    <script src="/js/pages/global-search.js"></script>
@endif
<script src="/js/pages/feedback-widget.js"></script>

@if (session('post_login_feedback_prompt'))
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modalElement = document.getElementById('feedbackWidgetModal');
            if (!modalElement || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                return;
            }

            let feedbackModal = null;

            if (typeof bootstrap.Modal.getOrCreateInstance === 'function') {
                feedbackModal = bootstrap.Modal.getOrCreateInstance(modalElement);
            } else if (typeof bootstrap.Modal.getInstance === 'function') {
                feedbackModal = bootstrap.Modal.getInstance(modalElement) || new bootstrap.Modal(modalElement);
            } else {
                feedbackModal = new bootstrap.Modal(modalElement);
            }

            feedbackModal.show();
        });
    </script>
@endif

@if (!empty($whatsappNotificationPanelEnabled))
    <script>
        window.MEDF = window.MEDF || {};
        window.MEDF.currentUser = @json($whatsappNotificationCurrentUser ?? ['id' => 0, 'name' => 'Usuario']);
        window.MEDF.defaultNotificationChannels = @json(($realtimeConfig['channels'] ?? ['email' => false, 'sms' => false, 'daily_summary' => false]));
        window.MEDF_PusherConfig = @json($realtimeConfig ?? []);
        window.__WHATSAPP_V2_NOTIFICATIONS__ = @json($whatsappRealtimeRuntime ?? ['currentConversationId' => 0, 'canSupervise' => false, 'scope' => 'general']);
    </script>
    @if(!empty($realtimeConfig['enabled']) && !empty($realtimeConfig['key']))
        <script src="/assets/vendor_components/pusher/pusher.min.js"></script>
    @endif
    <script type="module"
            src="/js/pages/whatsapp/v2-notifications.js?v={{ urlencode((string) ($whatsappAssetVersion ?? '1')) }}"></script>
@endif

@stack('scripts')
</body>
</html>
