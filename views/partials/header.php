<header class="main-header">
    <?php
    $headerQuickLinks = isset($appNavigation['header_quick_links']) && is_array($appNavigation['header_quick_links'])
        ? $appNavigation['header_quick_links']
        : [];
    $userMenuLinks = isset($appNavigation['user_menu_links']) && is_array($appNavigation['user_menu_links'])
        ? $appNavigation['user_menu_links']
        : [];
    ?>
    <div class="d-flex align-items-center logo-box justify-content-start">
        <!-- Logo -->
        <a href="/dashboard" class="logo">
            <!-- logo-->
            <div class="logo-mini w-50">
                <span class="light-logo"><img src="<?= img('logo-light-text.png') ?>" alt="logo"></span>
                <span class="dark-logo"><img src="<?= img('logo-light-text.png') ?>" alt="logo"></span>
            </div>
            <div class="logo-lg">
                        <span class="light-logo"><img src="<?php echo img('logo-light-text.png'); ?>"
                                                      alt="logo"></span>
                <span class="dark-logo"><img src="<?php echo img('logo-light-text.png'); ?>"
                                             alt="logo"></span>
            </div>
        </a>
    </div>
    <!-- Header Navbar -->
    <nav class="navbar navbar-static-top">
        <!-- Sidebar toggle button-->
        <div class="app-menu">
            <ul class="header-megamenu nav">
                <li class="btn-group nav-item">
                    <a href="#" class="waves-effect waves-light nav-link push-btn btn-primary-light"
                       data-toggle="push-menu" role="button">
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
                                        <button class="btn" type="submit" id="global-search-submit" aria-label="Ejecutar búsqueda">
                                            <i class="icon-Search"><span class="path1"></span><span class="path2"></span></i>
                                        </button>
                                    </div>
                                </div>
                            </form>
                            <div class="global-search-panel card shadow-lg" id="global-search-results" role="listbox" aria-live="polite" hidden></div>
                        </div>
                    </div>
                </li>
                <?php if ($headerQuickLinks !== []): ?>
                    <li class="btn-group nav-item d-xl-inline-flex d-none">
                        <a href="#"
                           class="waves-effect waves-light nav-link dropdown-toggle btn-primary-light"
                           data-bs-toggle="dropdown" role="button" aria-expanded="false"
                           title="Accesos rapidos">
                            <i class="mdi mdi-lightning-bolt-outline me-5"></i>
                            <span>Accesos</span>
                        </a>
                        <ul class="dropdown-menu animated flipInX">
                            <?php foreach ($headerQuickLinks as $quickLink): ?>
                                <?php if (!is_array($quickLink)): ?>
                                    <?php continue; ?>
                                <?php endif; ?>
                                <li>
                                    <a class="dropdown-item" href="<?= htmlspecialchars((string) ($quickLink['href'] ?? '#'), ENT_QUOTES, 'UTF-8'); ?>">
                                        <i class="<?= htmlspecialchars((string) ($quickLink['icon'] ?? 'mdi mdi-link-variant'), ENT_QUOTES, 'UTF-8'); ?> text-muted me-2"></i>
                                        <?= htmlspecialchars((string) ($quickLink['label'] ?? 'Acceso'), ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="navbar-custom-menu r-side">
            <ul class="nav navbar-nav">
                <!-- User Account-->
                <li class="dropdown user user-menu">
                    <?php
                    $headerUser = isset($currentUser) && is_array($currentUser) ? $currentUser : [];
                    $headerDisplayName = $headerUser['display_name'] ?? ($username ?? 'Usuario');
                    $headerRole = $headerUser['role_name'] ?? 'Usuario';
                    $headerAvatarUrl = $headerUser['profile_photo_url'] ?? null;
                    $headerAvatarResolvedUrl = $headerAvatarUrl;

                    if (is_string($headerAvatarResolvedUrl) && trim($headerAvatarResolvedUrl) !== '') {
                        $avatarPath = parse_url($headerAvatarResolvedUrl, PHP_URL_PATH);
                        if (is_string($avatarPath) && str_starts_with($avatarPath, '/uploads/')) {
                            $projectRoot = defined('BASE_PATH')
                                ? rtrim((string) BASE_PATH, '/\\')
                                : dirname(__DIR__, 2);
                            $localAvatar = $projectRoot . '/public' . $avatarPath;
                            if (!is_file($localAvatar)) {
                                $headerAvatarResolvedUrl = null;
                            }
                        }
                    }

                    if (!function_exists('medf_initials')) {
                        function medf_initials(string $name): string
                        {
                            $trimmed = trim($name);
                            if ($trimmed === '') {
                                return 'U';
                            }

                            $parts = preg_split('/\s+/u', $trimmed) ?: [];
                            if (count($parts) === 1) {
                                return mb_strtoupper(mb_substr($parts[0], 0, 2));
                            }

                            $first = mb_substr($parts[0], 0, 1);
                            $last = mb_substr($parts[count($parts) - 1], 0, 1);

                            return mb_strtoupper($first . $last);
                        }
                    }

                    $headerInitials = medf_initials((string) $headerDisplayName);
                    ?>
                    <a href="#"
                       class="waves-effect waves-light dropdown-toggle w-auto l-h-12 bg-transparent p-0 no-shadow"
                       data-bs-toggle="dropdown" title="User">
                        <div class="d-flex pt-1">
                            <div class="text-end me-10">
                                <p class="pt-5 fs-14 mb-0 fw-700 text-primary"><?= htmlspecialchars((string) $headerDisplayName, ENT_QUOTES, 'UTF-8'); ?></p>
                                <small class="fs-10 mb-0 text-uppercase text-mute"><?= htmlspecialchars((string) $headerRole, ENT_QUOTES, 'UTF-8'); ?></small>
                            </div>
                            <?php if ($headerAvatarResolvedUrl): ?>
                                <img src="<?= htmlspecialchars((string) $headerAvatarResolvedUrl, ENT_QUOTES, 'UTF-8'); ?>"
                                     class="avatar rounded-10 h-40 w-40"
                                     style="object-fit: cover;"
                                     alt="<?= htmlspecialchars((string) $headerDisplayName, ENT_QUOTES, 'UTF-8'); ?>"/>
                            <?php else: ?>
                                <span class="avatar rounded-10 bg-primary-light h-40 w-40 d-inline-flex align-items-center justify-content-center text-primary fw-bold">
                                    <?= htmlspecialchars($headerInitials, ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </a>
                    <ul class="dropdown-menu animated flipInX">
                        <li class="user-body">
                            <?php foreach ($userMenuLinks as $userMenuLink): ?>
                                <?php if (!is_array($userMenuLink)): ?>
                                    <?php continue; ?>
                                <?php endif; ?>
                                <a class="dropdown-item" href="<?= htmlspecialchars((string) ($userMenuLink['href'] ?? '#'), ENT_QUOTES, 'UTF-8'); ?>">
                                    <i class="<?= htmlspecialchars((string) ($userMenuLink['icon'] ?? 'ti-link'), ENT_QUOTES, 'UTF-8'); ?> text-muted me-2"></i>
                                    <?= htmlspecialchars((string) ($userMenuLink['label'] ?? 'Acceso'), ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            <?php endforeach; ?>
                            <?php if ($userMenuLinks !== []): ?>
                                <div class="dropdown-divider"></div>
                            <?php endif; ?>
                            <a class="dropdown-item" href="/v2/auth/logout"><i
                                        class="ti-lock text-muted me-2"></i> Cerrar sesión</a>
                        </li>
                    </ul>
                </li>
                <li class="btn-group nav-item d-lg-inline-flex d-none">
                    <a href="#" data-provide="fullscreen"
                       class="waves-effect waves-light nav-link full-screen btn-warning-light"
                       title="Full Screen">
                        <i class="icon-Position"></i>
                    </a>
                </li>
                <!-- Notifications -->
                <li class="notifications-menu">
                    <a href="#" class="waves-effect waves-light btn-info-light"
                       role="button" title="Notificaciones"
                       data-notification-panel-toggle="true" aria-controls="kanbanNotificationPanel">
                        <i class="icon-Notification"><span class="path1"></span><span class="path2"></span></i>
                        <span class="badge bg-danger notification-unread-badge d-none" data-notification-unread-badge>0</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>
</header>
