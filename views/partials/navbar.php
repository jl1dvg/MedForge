<?php
if (!function_exists('navCurrentPath')) {
    function navCurrentPath(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
        $normalized = trim((string) $path);

        if ($normalized === '') {
            return '/';
        }

        $normalized = '/' . ltrim($normalized, '/');

        return rtrim($normalized, '/') ?: '/';
    }
}

if (!function_exists('navNormalizePath')) {
    function navNormalizePath(string $path): string
    {
        $normalized = trim($path);
        if ($normalized === '') {
            return '/';
        }

        $normalized = '/' . ltrim($normalized, '/');

        return rtrim($normalized, '/') ?: '/';
    }
}

if (!function_exists('navPathStartsWith')) {
    function navPathStartsWith(string $currentPath, string $prefix): bool
    {
        $current = navNormalizePath($currentPath);
        $expected = navNormalizePath($prefix);

        if ($expected === '/') {
            return true;
        }

        return $current === $expected || str_starts_with($current . '/', $expected . '/');
    }
}

if (!function_exists('navRuleMatches')) {
    function navRuleMatches(array $rules): bool
    {
        $current = navCurrentPath();

        foreach ((array) ($rules['exclude_exact'] ?? []) as $path) {
            if ($current === navNormalizePath((string) $path)) {
                return false;
            }
        }

        foreach ((array) ($rules['exclude_prefix'] ?? []) as $prefix) {
            if (navPathStartsWith($current, (string) $prefix)) {
                return false;
            }
        }

        foreach ((array) ($rules['exact'] ?? []) as $path) {
            if ($current === navNormalizePath((string) $path)) {
                return true;
            }
        }

        foreach ((array) ($rules['prefix'] ?? []) as $prefix) {
            if (navPathStartsWith($current, (string) $prefix)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('navItemIsActive')) {
    function navItemIsActive(array $item): bool
    {
        $type = (string) ($item['type'] ?? 'item');

        if ($type === 'group') {
            foreach ((array) ($item['children'] ?? []) as $child) {
                if (is_array($child) && navItemIsActive($child)) {
                    return true;
                }
            }

            return false;
        }

        if ($type === 'label') {
            return false;
        }

        return navRuleMatches((array) ($item['active'] ?? []));
    }
}

if (!function_exists('renderSidebarItem')) {
    function renderSidebarItem(array $item): string
    {
        $type = (string) ($item['type'] ?? 'item');

        if ($type === 'label') {
            $label = htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8');

            return '<li class="header">' . $label . '</li>';
        }

        if ($type === 'group') {
            $label = htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8');
            $icon = htmlspecialchars((string) ($item['icon'] ?? 'mdi mdi-folder-outline'), ENT_QUOTES, 'UTF-8');
            $openClass = navItemIsActive($item) ? ' menu-open' : '';

            $childrenHtml = '';
            foreach ((array) ($item['children'] ?? []) as $child) {
                if (!is_array($child)) {
                    continue;
                }

                $childrenHtml .= renderSidebarItem($child);
            }

            return <<<HTML
<li class="treeview{$openClass}">
    <a href="#">
        <i class="{$icon}"></i>
        <span>{$label}</span>
        <span class="pull-right-container"><i class="fa fa-angle-right pull-right"></i></span>
    </a>
    <ul class="treeview-menu">
        {$childrenHtml}
    </ul>
</li>
HTML;
        }

        $label = htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8');
        $href = htmlspecialchars((string) ($item['href'] ?? '#'), ENT_QUOTES, 'UTF-8');
        $icon = htmlspecialchars((string) ($item['icon'] ?? 'mdi mdi-circle-outline'), ENT_QUOTES, 'UTF-8');
        $classAttr = navItemIsActive($item) ? ' class="is-active"' : '';

        return <<<HTML
<li{$classAttr}>
    <a href="{$href}">
        <i class="{$icon}"></i>
        <span>{$label}</span>
    </a>
</li>
HTML;
    }
}

$sidebarItems = isset($appNavigation['sidebar']) && is_array($appNavigation['sidebar'])
    ? $appNavigation['sidebar']
    : [];
?>
<aside class="main-sidebar">
    <section class="sidebar position-relative">
        <div class="multinav">
            <div class="multinav-scroll" style="height: 100%;">
                <ul class="sidebar-menu" data-widget="tree">
                    <?php foreach ($sidebarItems as $sidebarItem): ?>
                        <?php if (is_array($sidebarItem)): ?>
                            <?= renderSidebarItem($sidebarItem); ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </section>
</aside>
