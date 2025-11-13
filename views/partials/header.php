<header class="main-header">
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
                            <?php if ($headerAvatarUrl): ?>
                                <img src="<?= htmlspecialchars($headerAvatarUrl, ENT_QUOTES, 'UTF-8'); ?>"
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
                            <a class="dropdown-item" href="extra_profile.html"><i
                                        class="ti-user text-muted me-2"></i> Profile</a>
                            <a class="dropdown-item" href="/auth/logout"><i
                                        class="ti-lock text-muted me-2"></i> Logout</a>
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
                <li class="dropdown notifications-menu">
                    <a href="#" class="waves-effect waves-light dropdown-toggle btn-info-light"
                       data-bs-toggle="dropdown" title="Notifications">
                        <i class="icon-Notification"><span class="path1"></span><span class="path2"></span></i>
                    </a>
                    <ul class="dropdown-menu animated bounceIn">
                        <li class="header">
                            <div class="p-20">
                                <div class="flexbox">
                                    <div>
                                        <h4 class="mb-0 mt-0">Notifications</h4>
                                    </div>
                                    <div>
                                        <a href="#" class="text-danger">Clear All</a>
                                    </div>
                                </div>
                            </div>
                        </li>
                        <li>
                            <!-- inner menu: contains the actual data -->
                            <ul class="menu sm-scrol">
                                <li>
                                    <a href="#">
                                        <i class="fa fa-users text-info"></i> Curabitur id eros quis nunc
                                        suscipit blandit.
                                    </a>
                                </li>
                                <li>
                                    <a href="#">
                                        <i class="fa fa-warning text-warning"></i> Duis malesuada justo eu
                                        sapien elementum, in semper diam posuere.
                                    </a>
                                </li>
                                <li>
                                    <a href="#">
                                        <i class="fa fa-users text-danger"></i> Donec at nisi sit amet tortor
                                        commodo porttitor pretium a erat.
                                    </a>
                                </li>
                                <li>
                                    <a href="#">
                                        <i class="fa fa-shopping-cart text-success"></i> In gravida mauris et
                                        nisi
                                    </a>
                                </li>
                                <li>
                                    <a href="#">
                                        <i class="fa fa-user text-danger"></i> Praesent eu lacus in libero
                                        dictum fermentum.
                                    </a>
                                </li>
                                <li>
                                    <a href="#">
                                        <i class="fa fa-user text-primary"></i> Nunc fringilla lorem
                                    </a>
                                </li>
                                <li>
                                    <a href="#">
                                        <i class="fa fa-user text-success"></i> Nullam euismod dolor ut quam
                                        interdum, at scelerisque ipsum imperdiet.
                                    </a>
                                </li>
                            </ul>
                        </li>
                        <li class="footer">
                            <a href="#">View all</a>
                        </li>
                    </ul>
                </li>
                <!-- Control Sidebar Toggle Button -->
                <li class="btn-group nav-item">
                    <a href="#" data-toggle="control-sidebar" title="Setting"
                       class="waves-effect full-screen waves-light btn-danger-light"
                       data-notification-panel-toggle="true" aria-controls="kanbanNotificationPanel">
                        <i class="icon-Settings1"><span class="path1"></span><span class="path2"></span></i>
                    </a>
                </li>

            </ul>
        </div>
    </nav>
</header>
