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

    <link rel="stylesheet" href="/css/vendors_css.css">
    <link rel="stylesheet" href="/css/horizontal-menu.css">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/skin_color.css">
    @if ($hasMedforgeViteBuild)
        @vite('resources/css/medforge.css')
    @else
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

<script src="/js/vendors.min.js"></script>
<script src="/assets/vendor_components/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/icons/feather-icons/feather.min.js"></script>
<script src="/js/pages/shared/datatables-language-es.js"></script>
@if ($hasMedforgeViteBuild)
    @vite('resources/js/medforge.js')
@else
    <script src="/js/pages/chat-popup.js"></script>
    <script src="/js/jquery.smartmenus.js"></script>
    <script src="/js/menus.js"></script>
    <script src="/js/pages/global-search.js"></script>
    <script src="/js/template.js"></script>
@endif

@stack('scripts')
</body>
</html>
