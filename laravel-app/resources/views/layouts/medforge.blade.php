<!DOCTYPE html>
<html lang="es">
<head>
    @php
        $hasMedforgeViteBuild = \App\Modules\Shared\Support\MedforgeAssets::hasViteBuild();
    @endphp
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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

    @stack('styles')
</head>
<body class="hold-transition light-skin sidebar-mini theme-primary fixed">
@php
    $appNavigation = \App\Modules\Shared\Support\MedforgeNavigation::build(request());
@endphp
<div class="wrapper">
    @include('layouts.partials.header')
    @include('layouts.partials.navbar')

    <div class="content-wrapper">
        <div class="container-full">
            @yield('content')
        </div>
    </div>

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

@php
    $postLoginNotice = session('post_login_notice');
@endphp

@if (is_array($postLoginNotice) && !empty($postLoginNotice['message']))
    <div class="modal fade" id="postLoginNoticeModal" tabindex="-1" aria-labelledby="postLoginNoticeLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <h5 class="modal-title" id="postLoginNoticeLabel">{{ $postLoginNotice['title'] ?? 'Aviso importante' }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">{{ $postLoginNotice['message'] }}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Entendido</button>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modalElement = document.getElementById('postLoginNoticeModal');
            if (!modalElement || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                return;
            }

            const noticeModal = new bootstrap.Modal(modalElement);
            noticeModal.show();
        });
    </script>
@endif

@stack('scripts')
</body>
</html>
