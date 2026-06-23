@php
    $bannerConfig = config('medforge-banner', []);
@endphp
@if (!empty($bannerConfig['enabled']) && !empty($bannerConfig['message']))
    <div id="medforge-global-banner"
         class="medforge-global-banner medforge-global-banner--{{ $bannerConfig['variant'] ?? 'warning' }}"
         role="status">
        <i class="mdi {{ $bannerConfig['icon'] ?? 'mdi-alert-outline' }} medforge-global-banner__icon" aria-hidden="true"></i>
        <span class="medforge-global-banner__text">
            @if (!empty($bannerConfig['title']))
                <span class="medforge-global-banner__title">{{ $bannerConfig['title'] }}:</span>
            @endif
            {{ $bannerConfig['message'] }}
        </span>
    </div>
    <script>
        (function () {
            var banner = document.getElementById('medforge-global-banner');
            if (!banner) {
                return;
            }
            function syncBannerHeight() {
                document.documentElement.style.setProperty('--medforge-banner-height', banner.offsetHeight + 'px');
            }
            syncBannerHeight();
            window.addEventListener('resize', syncBannerHeight);
        })();
    </script>
@endif
