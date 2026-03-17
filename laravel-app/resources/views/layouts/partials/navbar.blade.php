@php
    $sidebarItems = isset($appNavigation['sidebar']) && is_array($appNavigation['sidebar'])
        ? $appNavigation['sidebar']
        : [];

    $normalizePath = static function (string $path): string {
        $normalized = trim($path);
        if ($normalized === '') {
            return '/';
        }

        $normalized = '/' . ltrim($normalized, '/');

        return rtrim($normalized, '/') ?: '/';
    };

    $currentPath = $normalizePath((string) request()->getPathInfo());

    $pathStartsWith = static function (string $current, string $prefix) use ($normalizePath): bool {
        $resolvedCurrent = $normalizePath($current);
        $expected = $normalizePath($prefix);

        if ($expected === '/') {
            return true;
        }

        return $resolvedCurrent === $expected || str_starts_with($resolvedCurrent . '/', $expected . '/');
    };

    $ruleMatches = static function (array $rules) use ($currentPath, $normalizePath, $pathStartsWith): bool {
        foreach ((array) ($rules['exclude_exact'] ?? []) as $path) {
            if ($currentPath === $normalizePath((string) $path)) {
                return false;
            }
        }

        foreach ((array) ($rules['exclude_prefix'] ?? []) as $prefix) {
            if ($pathStartsWith($currentPath, (string) $prefix)) {
                return false;
            }
        }

        foreach ((array) ($rules['exact'] ?? []) as $path) {
            if ($currentPath === $normalizePath((string) $path)) {
                return true;
            }
        }

        foreach ((array) ($rules['prefix'] ?? []) as $prefix) {
            if ($pathStartsWith($currentPath, (string) $prefix)) {
                return true;
            }
        }

        return false;
    };

    $itemIsActive = null;
    $itemIsActive = static function (array $item) use (&$itemIsActive, $ruleMatches): bool {
        $type = (string) ($item['type'] ?? 'item');

        if ($type === 'group') {
            foreach ((array) ($item['children'] ?? []) as $child) {
                if (is_array($child) && $itemIsActive($child)) {
                    return true;
                }
            }

            return false;
        }

        if ($type === 'label') {
            return false;
        }

        return $ruleMatches((array) ($item['active'] ?? []));
    };

    $renderSidebarItem = null;
    $renderSidebarItem = static function (array $item) use (&$renderSidebarItem, $itemIsActive): string {
        $type = (string) ($item['type'] ?? 'item');

        if ($type === 'label') {
            return '<li class="header">' . e((string) ($item['label'] ?? '')) . '</li>';
        }

        if ($type === 'group') {
            $childrenHtml = '';
            foreach ((array) ($item['children'] ?? []) as $child) {
                if (!is_array($child)) {
                    continue;
                }

                $childrenHtml .= $renderSidebarItem($child);
            }

            $openClass = $itemIsActive($item) ? ' menu-open' : '';
            $icon = e((string) ($item['icon'] ?? 'mdi mdi-folder-outline'));
            $label = e((string) ($item['label'] ?? ''));

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

        $classAttr = $itemIsActive($item) ? ' class="is-active"' : '';
        $href = e((string) ($item['href'] ?? '#'));
        $icon = e((string) ($item['icon'] ?? 'mdi mdi-circle-outline'));
        $label = e((string) ($item['label'] ?? ''));

        return <<<HTML
<li{$classAttr}>
    <a href="{$href}">
        <i class="{$icon}"></i>
        <span>{$label}</span>
    </a>
</li>
HTML;
    };
@endphp
<aside class="main-sidebar">
    <section class="sidebar position-relative">
        <div class="multinav">
            <div class="multinav-scroll" style="height: 100%;">
                <ul class="sidebar-menu" data-widget="tree">
                    @foreach($sidebarItems as $sidebarItem)
                        @if(is_array($sidebarItem))
                            {!! $renderSidebarItem($sidebarItem) !!}
                        @endif
                    @endforeach
                </ul>
            </div>
        </div>
    </section>
</aside>
