const loadedScripts = new Map();

const normalizeAssetPath = (path) => {
    if (typeof window === 'undefined') {
        return path;
    }

    return new URL(path, window.location.origin).toString();
};

export const loadLegacyScript = (path) => {
    const assetUrl = normalizeAssetPath(path);

    if (loadedScripts.has(assetUrl)) {
        return loadedScripts.get(assetUrl);
    }

    const promise = new Promise((resolve, reject) => {
        const existingScript = document.querySelector(`script[src="${assetUrl}"], script[src="${path}"]`);

        const handleLoad = () => {
            resolve();
        };

        const handleError = () => {
            reject(new Error(`Unable to load legacy asset: ${path}`));
        };

        if (existingScript) {
            if (existingScript.dataset.medforgeLoaded === 'true') {
                resolve();
                return;
            }

            existingScript.addEventListener('load', handleLoad, { once: true });
            existingScript.addEventListener('error', handleError, { once: true });
            return;
        }

        const script = document.createElement('script');
        script.src = assetUrl;
        script.async = false;
        script.dataset.medforgeAsset = path;
        script.addEventListener('load', () => {
            script.dataset.medforgeLoaded = 'true';
            resolve();
        }, { once: true });
        script.addEventListener('error', handleError, { once: true });
        document.head.appendChild(script);
    });

    loadedScripts.set(assetUrl, promise);

    return promise;
};

export const loadLegacyModuleScript = (path) => {
    const assetUrl = normalizeAssetPath(path);
    const cacheKey = `module:${assetUrl}`;

    if (loadedScripts.has(cacheKey)) {
        return loadedScripts.get(cacheKey);
    }

    const promise = new Promise((resolve, reject) => {
        const existingScript = document.querySelector(`script[type="module"][src="${assetUrl}"], script[type="module"][src="${path}"]`);

        const handleLoad = () => {
            resolve();
        };

        const handleError = () => {
            reject(new Error(`Unable to load legacy module asset: ${path}`));
        };

        if (existingScript) {
            if (existingScript.dataset.medforgeLoaded === 'true') {
                resolve();
                return;
            }

            existingScript.addEventListener('load', handleLoad, { once: true });
            existingScript.addEventListener('error', handleError, { once: true });
            return;
        }

        const script = document.createElement('script');
        script.src = assetUrl;
        script.type = 'module';
        script.dataset.medforgeAsset = path;
        script.addEventListener('load', () => {
            script.dataset.medforgeLoaded = 'true';
            resolve();
        }, { once: true });
        script.addEventListener('error', handleError, { once: true });
        document.head.appendChild(script);
    });

    loadedScripts.set(cacheKey, promise);

    return promise;
};

export const ensureJQuery = async () => {
    if (window.jQuery && window.$) {
        _applyJQuerySetup(window.jQuery);
        return window.jQuery;
    }

    await loadLegacyScript('/assets/vendor_components/jquery-ui/external/jquery/jquery.js');

    const jQuery = window.jQuery || window.$;

    if (!jQuery) {
        throw new Error('MedForge could not initialize jQuery for the Vite page bundle.');
    }

    window.jQuery = jQuery;
    window.$ = jQuery;
    _applyJQuerySetup(jQuery);

    return jQuery;
};

/** Applied once per page load: CSRF header for all $.ajax POST requests. */
function _applyJQuerySetup(jQuery) {
    if (jQuery.__medfSetupApplied) {
        return;
    }
    jQuery.__medfSetupApplied = true;

    // CSRF token — ensures every jQuery POST (DataTables included) passes Laravel's VerifyCsrfToken
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? (csrfMeta.getAttribute('content') || '') : '';
    if (csrfToken) {
        jQuery.ajaxSetup({
            headers: { 'X-CSRF-TOKEN': csrfToken },
        });
    }
}

export const ensureMoment = async () => {
    if (window.moment) {
        return window.moment;
    }

    await loadLegacyScript('/assets/vendor_components/moment/moment.js');

    if (!window.moment) {
        throw new Error('MedForge could not initialize Moment.js for the Vite page bundle.');
    }

    return window.moment;
};

export const ensureApexCharts = async () => {
    if (window.ApexCharts) {
        return window.ApexCharts;
    }

    await loadLegacyScript('/assets/vendor_components/apexcharts-bundle/dist/apexcharts.js');

    if (!window.ApexCharts) {
        throw new Error('MedForge could not initialize ApexCharts for the Vite page bundle.');
    }

    return window.ApexCharts;
};

export const ensureSweetAlert = async () => {
    if (window.Swal && typeof window.Swal.fire === 'function') {
        return window.Swal;
    }

    await loadLegacyScript('/assets/vendor_components/sweetalert2/sweetalert2.all.min.js');

    if (!window.Swal || typeof window.Swal.fire !== 'function') {
        throw new Error('MedForge could not initialize SweetAlert2 for the Vite page bundle.');
    }

    return window.Swal;
};

export const ensureDataTables = async () => {
    const jQuery = await ensureJQuery();

    // jQuery 4 removed several methods used by DataTables 1.x — polyfill them all
    if (typeof jQuery.isArray !== 'function')    { jQuery.isArray    = Array.isArray; }
    if (typeof jQuery.trim    !== 'function')    { jQuery.trim       = (s) => (s == null ? '' : String.prototype.trim.call(s)); }
    if (typeof jQuery.isFunction !== 'function') { jQuery.isFunction = (fn) => typeof fn === 'function'; }
    if (typeof jQuery.type !== 'function') {
        jQuery.type = (obj) => {
            if (obj == null) return String(obj);
            const map = {
                '[object Boolean]': 'boolean', '[object Number]': 'number',
                '[object String]': 'string',   '[object Function]': 'function',
                '[object Array]': 'array',     '[object Date]': 'date',
                '[object RegExp]': 'regexp',   '[object Object]': 'object',
                '[object Error]': 'error',
            };
            return map[Object.prototype.toString.call(obj)] || 'object';
        };
    }
    if (typeof jQuery.now !== 'function') { jQuery.now = Date.now; }

    if (jQuery.fn && typeof jQuery.fn.DataTable === 'function') {
        return;
    }

    await loadLegacyScript('/assets/vendor_components/datatable/datatables.min.js');

    if (!jQuery.fn || typeof jQuery.fn.DataTable !== 'function') {
        throw new Error('MedForge could not initialize DataTables for the Vite page bundle.');
    }
};

export const ensureDataTableLanguage = async () => {
    if (typeof window.medforgeDataTableLanguageEs === 'function') {
        return;
    }

    await loadLegacyScript('/js/pages/shared/datatables-language-es.js');
};

export const ensurePeity = async () => {
    const jQuery = await ensureJQuery();

    if (jQuery.fn && typeof jQuery.fn.peity === 'function') {
        return;
    }

    await loadLegacyScript('/assets/vendor_components/jquery.peity/jquery.peity.js');

    if (!jQuery.fn || typeof jQuery.fn.peity !== 'function') {
        throw new Error('MedForge could not initialize jQuery Peity for the Vite page bundle.');
    }
};

export const ensureOwlCarousel = async () => {
    const jQuery = await ensureJQuery();

    if (jQuery.fn && typeof jQuery.fn.owlCarousel === 'function') {
        return;
    }

    await loadLegacyScript('/assets/vendor_components/OwlCarousel2/dist/owl.carousel.js');

    if (!jQuery.fn || typeof jQuery.fn.owlCarousel !== 'function') {
        throw new Error('MedForge could not initialize OwlCarousel for the Vite page bundle.');
    }
};

export const ensureHorizontalTimeline = async () => {
    await ensureJQuery();
    await loadLegacyScript('/assets/vendor_components/horizontal-timeline/js/horizontal-timeline.js');
};

export const ensureDaterangepicker = async () => {
    const jQuery = await ensureJQuery();
    await ensureMoment();

    if (jQuery.fn && typeof jQuery.fn.daterangepicker === 'function') {
        return;
    }

    await loadLegacyScript('/assets/vendor_components/bootstrap-daterangepicker/daterangepicker.js');

    if (!jQuery.fn || typeof jQuery.fn.daterangepicker !== 'function') {
        throw new Error('MedForge could not initialize DateRangePicker for the Vite page bundle.');
    }
};

export const ensureSortable = async () => {
    if (window.Sortable) {
        return window.Sortable;
    }

    await loadLegacyScript('/assets/vendor_components/sortablejs/Sortable.min.js');

    if (!window.Sortable) {
        throw new Error('MedForge could not initialize SortableJS for the Vite page bundle.');
    }

    return window.Sortable;
};

export const ensurePusher = async () => {
    if (window.Pusher) {
        return window.Pusher;
    }

    await loadLegacyScript('/assets/vendor_components/pusher/pusher.min.js');

    if (!window.Pusher) {
        throw new Error('MedForge could not initialize Pusher for the Vite page bundle.');
    }

    return window.Pusher;
};

export const ensureBootstrapDatepicker = async () => {
    const jQuery = await ensureJQuery();

    if (jQuery.fn && typeof jQuery.fn.datepicker === 'function' && jQuery.fn.datepicker.dates?.es) {
        return;
    }

    await loadLegacyScript('/assets/vendor_components/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js');
    await loadLegacyScript('/assets/vendor_components/bootstrap-datepicker/dist/locales/bootstrap-datepicker.es.min.js');

    if (!jQuery.fn || typeof jQuery.fn.datepicker !== 'function') {
        throw new Error('MedForge could not initialize Bootstrap Datepicker for the Vite page bundle.');
    }
};

export const ensureCkeditor = async () => {
    if (window.CKEDITOR) {
        return window.CKEDITOR;
    }

    await loadLegacyScript('/assets/vendor_components/ckeditor/ckeditor.js');

    if (!window.CKEDITOR) {
        throw new Error('MedForge could not initialize CKEditor for the Vite page bundle.');
    }

    return window.CKEDITOR;
};
