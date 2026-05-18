/**
 * Settings v2 - interactividad del módulo de configuración
 */
(() => {
    'use strict';

    // --- Navegación SPA-like entre secciones ---
    function initSectionNav() {
        document.querySelectorAll('.settings-sidenav .nav-link[data-section]').forEach(link => {
            link.addEventListener('click', e => {
                e.preventDefault();
                const section = link.dataset.section;
                if (!section) return;

                const url = new URL(window.location.href);
                url.searchParams.set('section', section);
                history.replaceState(null, '', url.toString());
                window.location.href = url.toString();
            });
        });
    }

    // --- Preview de imágenes al seleccionar archivo ---
    function initFileUpload() {
        document.querySelectorAll('.settings-file-input').forEach(input => {
            input.addEventListener('change', () => {
                const wrap = input.closest('.settings-file-wrap');
                if (!wrap) return;

                const previewWrap = wrap.querySelector('.settings-file-new-preview-wrap');
                const previewImg = wrap.querySelector('.settings-file-new-preview');
                const previewName = wrap.querySelector('.settings-file-new-name');

                const file = input.files?.[0];
                if (!file) {
                    previewWrap?.classList.add('d-none');
                    return;
                }

                const allowed = ['image/png', 'image/jpeg', 'image/webp', 'image/gif', 'image/svg+xml'];
                if (!allowed.includes(file.type)) {
                    showToast('Formato no permitido. Use PNG, JPG, WEBP, GIF o SVG.', 'error');
                    input.value = '';
                    return;
                }

                if (file.size > 3 * 1024 * 1024) {
                    showToast('El archivo no puede superar 3MB.', 'error');
                    input.value = '';
                    return;
                }

                if (previewImg && previewWrap && previewName) {
                    const reader = new FileReader();
                    reader.onload = e => {
                        previewImg.src = e.target?.result ?? '';
                        previewName.textContent = file.name;
                        previewWrap.classList.remove('d-none');
                    };
                    reader.readAsDataURL(file);
                }
            });
        });
    }

    // --- Toggle show/hide en campos password ---
    function initPasswordToggle() {
        document.querySelectorAll('.settings-password-wrap').forEach(wrap => {
            const input = wrap.querySelector('input[type="password"]');
            const btn = wrap.querySelector('.btn-pw-toggle');
            const icon = btn?.querySelector('i');
            if (!input || !btn) return;

            btn.addEventListener('click', () => {
                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                if (icon) {
                    icon.className = isPassword ? 'mdi mdi-eye-off-outline' : 'mdi mdi-eye-outline';
                }
                btn.setAttribute('aria-label', isPassword ? 'Ocultar contraseña' : 'Mostrar contraseña');
            });
        });
    }

    // --- Preview en tiempo real de color inputs ---
    function initColorPreview() {
        document.querySelectorAll('input[type="color"]').forEach(input => {
            const parent = input.closest('.d-flex');
            if (!parent) return;
            const swatch = parent.querySelector('.settings-color-swatch');
            const hex = parent.querySelector('.settings-color-hex');

            function update() {
                const value = input.value;
                if (swatch) swatch.style.background = value;
                if (hex) hex.textContent = value;
            }

            input.addEventListener('input', update);
            input.addEventListener('change', update);
        });
    }

    // --- Dirty tracking: detecta cambios para mostrar aviso ---
    function initDirtyTracking() {
        document.querySelectorAll('[data-settings-form]').forEach(form => {
            const msg = form.querySelector('.settings-dirty-msg');
            if (!msg) return;

            let dirty = false;

            form.querySelectorAll('input, textarea, select').forEach(el => {
                el.addEventListener('change', () => {
                    if (!dirty) {
                        dirty = true;
                        msg.classList.remove('d-none');
                    }
                });
                el.addEventListener('input', () => {
                    if (!dirty) {
                        dirty = true;
                        msg.classList.remove('d-none');
                    }
                });
            });

            form.addEventListener('submit', () => {
                dirty = false;
                msg.classList.add('d-none');
            });
        });
    }

    // --- AJAX save: intercepta el submit del form ---
    function initAjaxSave() {
        document.querySelectorAll('[data-settings-form]').forEach(form => {
            const apiUrl = form.dataset.apiUrl;
            if (!apiUrl) return;

            form.addEventListener('submit', async e => {
                e.preventDefault();

                const btn = form.querySelector('.settings-save-btn');
                if (btn) {
                    btn.classList.add('loading');
                    btn.disabled = true;
                }

                try {
                    const formData = new FormData(form);
                    const response = await fetch(apiUrl, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        },
                        body: formData,
                    });

                    const data = await response.json().catch(() => ({}));

                    if (response.ok && data.success) {
                        showToast(data.message ?? 'Configuración guardada.', 'success');
                        const msg = form.querySelector('.settings-dirty-msg');
                        if (msg) msg.classList.add('d-none');

                        // Actualizar file previews si hubo uploads
                        updateFilePreviews(form);
                    } else {
                        const errorText = data.error ?? 'Error al guardar la configuración.';
                        showToast(errorText, 'error');
                    }
                } catch {
                    showToast('Error de red. Intenta nuevamente.', 'error');
                } finally {
                    if (btn) {
                        btn.classList.remove('loading');
                        btn.disabled = false;
                    }
                }
            });
        });
    }

    function updateFilePreviews(form) {
        form.querySelectorAll('.settings-file-input').forEach(input => {
            const wrap = input.closest('.settings-file-wrap');
            const newPreviewWrap = wrap?.querySelector('.settings-file-new-preview-wrap');
            const newPreviewImg = wrap?.querySelector('.settings-file-new-preview');
            const currentPreview = wrap?.querySelector('.settings-file-preview');

            if (!newPreviewWrap || newPreviewWrap.classList.contains('d-none')) return;

            // Si había un archivo nuevo seleccionado, actualizar el preview "actual"
            if (currentPreview && newPreviewImg?.src) {
                const img = currentPreview.querySelector('img');
                if (img) img.src = newPreviewImg.src;
            }

            newPreviewWrap.classList.add('d-none');
            input.value = '';
        });
    }

    // --- Notificaciones con SweetAlert2 ---
    function showToast(message, type = 'success') {
        if (typeof window.Swal !== 'undefined') {
            window.Swal.fire({
                toast: true,
                position: 'top-end',
                icon: type,
                title: message,
                showConfirmButton: false,
                timer: type === 'success' ? 3000 : 5000,
                timerProgressBar: true,
            });
            return;
        }

        // Fallback si SweetAlert2 no está cargado aún
        const div = document.createElement('div');
        div.className = `alert alert-${type === 'success' ? 'success' : 'danger'} position-fixed top-0 end-0 m-3`;
        div.style.zIndex = '9999';
        div.textContent = message;
        document.body.appendChild(div);
        setTimeout(() => div.remove(), 4000);
    }

    // --- Inicialización ---
    document.addEventListener('DOMContentLoaded', () => {
        initSectionNav();
        initFileUpload();
        initPasswordToggle();
        initColorPreview();
        initDirtyTracking();
        initAjaxSave();
    });
})();
