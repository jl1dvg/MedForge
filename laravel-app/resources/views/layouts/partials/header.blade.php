@php
    $user = is_array($currentUser ?? null) ? $currentUser : [];
    $displayName = trim((string) ($user['display_name'] ?? 'Usuario'));
    $roleName = trim((string) ($user['role_name'] ?? 'Usuario'));
    $profilePhoto = trim((string) ($user['profile_photo_url'] ?? ''));
    $headerQuickLinks = isset($appNavigation['header_quick_links']) && is_array($appNavigation['header_quick_links'])
        ? $appNavigation['header_quick_links']
        : [];
    $userMenuLinks = isset($appNavigation['user_menu_links']) && is_array($appNavigation['user_menu_links'])
        ? $appNavigation['user_menu_links']
        : [];
    $sidebarItems = isset($appNavigation['sidebar']) && is_array($appNavigation['sidebar'])
        ? $appNavigation['sidebar']
        : [];

    $homeLink = '/v2/dashboard';
    foreach ($sidebarItems as $sidebarItem) {
        if (!is_array($sidebarItem)) {
            continue;
        }

        if (($sidebarItem['type'] ?? 'item') !== 'item') {
            continue;
        }

        $candidateHref = trim((string) ($sidebarItem['href'] ?? ''));
        $candidateLabel = trim((string) ($sidebarItem['label'] ?? ''));
        if ($candidateHref === '' || $candidateLabel === 'Cerrar sesion') {
            continue;
        }

        $homeLink = $candidateHref;
        break;
    }

    $initials = 'U';
    if ($displayName !== '') {
        $parts = preg_split('/\s+/u', $displayName) ?: [];
        if (count($parts) === 1) {
            $initials = mb_strtoupper(mb_substr($parts[0], 0, 2));
        } elseif (count($parts) > 1) {
            $initials = mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[count($parts) - 1], 0, 1));
        }
    }
@endphp
<header class="main-header">
    <div class="d-flex align-items-center logo-box justify-content-start">
        <a href="{{ $homeLink }}" class="logo">
            <div class="logo-mini w-50">
                <span class="light-logo"><img src="/images/logo-light-text.png" alt="logo"></span>
                <span class="dark-logo"><img src="/images/logo-light-text.png" alt="logo"></span>
            </div>
            <div class="logo-lg">
                <span class="light-logo"><img src="/images/logo-light-text.png" alt="logo"></span>
                <span class="dark-logo"><img src="/images/logo-light-text.png" alt="logo"></span>
            </div>
        </a>
    </div>

    <nav class="navbar navbar-static-top">
        <div class="app-menu">
            <ul class="header-megamenu nav">
                <li class="btn-group nav-item">
                    <a href="#" class="waves-effect waves-light nav-link push-btn btn-primary-light" data-toggle="push-menu" role="button">
                        <i class="icon-Menu"><span class="path1"></span><span class="path2"></span></i>
                    </a>
                </li>
                <li class="btn-group d-lg-inline-flex d-none">
                    <div class="app-menu">
                        <div class="search-bx mx-5 position-relative" id="app-global-search">
                            <form class="global-search-form" autocomplete="off" novalidate>
                                <div class="input-group">
                                    <input
                                        type="search"
                                        class="form-control"
                                        id="global-search-input"
                                        name="q"
                                        placeholder="Buscar en el sistema..."
                                        aria-label="Buscar en el sistema"
                                        aria-controls="global-search-results"
                                        aria-expanded="false"
                                        autocomplete="off"
                                    >
                                    <div class="input-group-append">
                                        <button class="btn" type="submit" id="global-search-submit" aria-label="Ejecutar busqueda">
                                            <i class="icon-Search"><span class="path1"></span><span class="path2"></span></i>
                                        </button>
                                    </div>
                                </div>
                            </form>
                            <div class="global-search-panel card shadow-lg" id="global-search-results" role="listbox" aria-live="polite" hidden></div>
                        </div>
                    </div>
                </li>
                @if($headerQuickLinks !== [])
                    <li class="btn-group nav-item d-xl-inline-flex d-none">
                        <a href="#"
                           class="waves-effect waves-light nav-link dropdown-toggle btn-primary-light"
                           data-bs-toggle="dropdown" role="button" aria-expanded="false"
                           title="Accesos rapidos">
                            <i class="mdi mdi-lightning-bolt-outline me-5"></i>
                            <span>Accesos</span>
                        </a>
                        <ul class="dropdown-menu">
                            @foreach($headerQuickLinks as $quickLink)
                                @continue(!is_array($quickLink))
                                <li>
                                    <a class="dropdown-item" href="{{ $quickLink['href'] ?? '#' }}">
                                        <i class="{{ $quickLink['icon'] ?? 'mdi mdi-link-variant' }} text-muted me-2"></i>
                                        {{ $quickLink['label'] ?? 'Acceso' }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endif
            </ul>
        </div>

        <div class="navbar-custom-menu r-side">
            <ul class="nav navbar-nav">
                <li class="dropdown user user-menu">
                    <a href="#" class="waves-effect waves-light dropdown-toggle w-auto l-h-12 bg-transparent p-0 no-shadow" data-bs-toggle="dropdown" title="User">
                        <div class="d-flex pt-1">
                            <div class="text-end me-10">
                                <p class="pt-5 fs-14 mb-0 fw-700 text-primary">{{ $displayName !== '' ? $displayName : 'Usuario' }}</p>
                                <small class="fs-10 mb-0 text-uppercase text-mute">{{ $roleName !== '' ? $roleName : 'Usuario' }}</small>
                            </div>
                            @if($profilePhoto !== '')
                                <img src="{{ $profilePhoto }}" class="avatar rounded-10 h-40 w-40" style="object-fit: cover;" alt="{{ $displayName }}">
                            @else
                                <span class="avatar rounded-10 bg-primary-light h-40 w-40 d-inline-flex align-items-center justify-content-center text-primary fw-bold">{{ $initials }}</span>
                            @endif
                        </div>
                    </a>
                    <ul class="dropdown-menu">
                        <li class="user-body">
                            @foreach($userMenuLinks as $userMenuLink)
                                @continue(!is_array($userMenuLink))
                                <a class="dropdown-item" href="{{ $userMenuLink['href'] ?? '#' }}">
                                    <i class="{{ $userMenuLink['icon'] ?? 'mdi mdi-link-variant' }} text-muted me-2"></i>
                                    {{ $userMenuLink['label'] ?? 'Acceso' }}
                                </a>
                            @endforeach
                            @if($userMenuLinks !== [])
                                <div class="dropdown-divider"></div>
                            @endif
                            <a class="dropdown-item" href="/v2/auth/logout"><i class="mdi mdi-lock-outline text-muted me-2"></i> Cerrar sesion</a>
                        </li>
                    </ul>
                </li>
                <li class="notifications-menu">
                    <a
                        href="#"
                        class="waves-effect waves-light btn-info-light"
                        role="button"
                        title="Notificaciones"
                        data-notification-panel-toggle="true"
                        aria-controls="kanbanNotificationPanel"
                    >
                        <i class="icon-Notification"><span class="path1"></span><span class="path2"></span></i>
                        <span class="badge bg-danger notification-unread-badge d-none" data-notification-unread-badge>0</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>
</header>
