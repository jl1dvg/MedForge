(function () {
    'use strict';

    // Populated by v2-index.blade.php via window.__USUARIOS_INDEX__
    const CONFIG = window.__USUARIOS_INDEX__ || {};
    const permissionGroups     = CONFIG.permissionGroups     || {};
    const rolesWithPermissions = CONFIG.rolesWithPermissions || {};
    const currentUserId        = CONFIG.currentUserId        || 0;
    const canManageUsers       = CONFIG.canManageUsers       || false;

    document.addEventListener('DOMContentLoaded', function () {
        initFilters();
        initSort();
        initDrawer();
        initDeleteModal();
    });

    // ─── FILTERS ─────────────────────────────────────────────────────────────

    function initFilters() {
        const buscar       = document.getElementById('uf-buscar');
        const especialidad = document.getElementById('uf-especialidad');
        const rol          = document.getElementById('uf-rol');
        const estado       = document.getElementById('uf-estado');
        const limpiar      = document.getElementById('uf-limpiar');
        const count        = document.getElementById('uf-count');
        const rows         = Array.from(document.querySelectorAll('tbody tr[data-search]'));

        function apply() {
            const q   = buscar       ? buscar.value.trim().toLowerCase()       : '';
            const esp = especialidad ? especialidad.value.trim().toLowerCase() : '';
            const r   = rol          ? rol.value.trim()                        : '';
            const est = estado       ? estado.value.trim()                     : '';

            let shown = 0;
            rows.forEach(function (row) {
                let ok = true;
                if (q   && !(row.dataset.search      || '').includes(q))                      ok = false;
                if (ok && esp && (row.dataset.especialidad || '').toLowerCase() !== esp)       ok = false;
                if (ok && r   && (row.dataset.roleId       || '') !== r)                       ok = false;
                if (ok && est === 'approved' && row.dataset.approved !== '1')                  ok = false;
                if (ok && est === 'pending'  && row.dataset.approved === '1')                  ok = false;

                row.classList.toggle('d-none', !ok);
                if (ok) shown++;
            });

            if (count) count.textContent = shown + ' usuario' + (shown !== 1 ? 's' : '');
        }

        if (buscar)       buscar.addEventListener('input', apply);
        if (especialidad) especialidad.addEventListener('change', apply);
        if (rol)          rol.addEventListener('change', apply);
        if (estado)       estado.addEventListener('change', apply);
        if (limpiar) {
            limpiar.addEventListener('click', function () {
                if (buscar)       buscar.value = '';
                if (especialidad) especialidad.value = '';
                if (rol)          rol.value = '';
                if (estado)       estado.value = '';
                apply();
            });
        }

        apply();
    }

    // ─── SORT ────────────────────────────────────────────────────────────────

    function initSort() {
        const table = document.querySelector('.usuarios-table');
        if (!table) return;

        const headers  = table.querySelectorAll('thead th[data-sort]');
        const collator = new Intl.Collator('es', { sensitivity: 'base' });

        headers.forEach(function (th) {
            th.style.cursor = 'pointer';
            th.setAttribute('aria-sort', 'none');

            th.addEventListener('click', function () {
                const current  = th.getAttribute('aria-sort');
                const dir      = current === 'ascending' ? 'descending' : 'ascending';
                const colIndex = Array.prototype.indexOf.call(th.parentElement.children, th);
                const tbody    = table.querySelector('tbody');
                if (!tbody) return;

                headers.forEach(function (h) { h.setAttribute('aria-sort', 'none'); });
                th.setAttribute('aria-sort', dir);

                const trows = Array.from(tbody.querySelectorAll('tr'));
                trows.sort(function (a, b) {
                    const cellA = a.children[colIndex];
                    const cellB = b.children[colIndex];
                    const va = cellA ? (cellA.dataset.sortValue || cellA.textContent || '').trim() : '';
                    const vb = cellB ? (cellB.dataset.sortValue || cellB.textContent || '').trim() : '';
                    const cmp = collator.compare(va, vb);
                    return dir === 'ascending' ? cmp : -cmp;
                });
                trows.forEach(function (r) { tbody.appendChild(r); });
            });
        });
    }

    // ─── DRAWER ──────────────────────────────────────────────────────────────

    let activeRow = null;

    function initDrawer() {
        const drawer   = document.getElementById('usuarios-drawer');
        const closeBtn = document.getElementById('usuarios-drawer-close');
        if (!drawer) return;

        // Click on any row (except action buttons) opens drawer
        document.querySelectorAll('tbody tr[data-user]').forEach(function (row) {
            row.addEventListener('click', function (e) {
                if (e.target.closest('button, a, form')) return;
                openDrawer(row, drawer);
            });
            const editBtn = row.querySelector('.row-edit-btn');
            if (editBtn) {
                editBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    openDrawer(row, drawer);
                });
            }
        });

        if (closeBtn) {
            closeBtn.addEventListener('click', function () { closeDrawer(drawer); });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !drawer.classList.contains('d-none')) {
                closeDrawer(drawer);
            }
        });

        // Tabs
        drawer.querySelectorAll('.drawer-tab-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const target = btn.dataset.tab;
                drawer.querySelectorAll('.drawer-tab-btn').forEach(function (b) {
                    b.classList.toggle('active', b.dataset.tab === target);
                    b.setAttribute('aria-selected', String(b.dataset.tab === target));
                });
                drawer.querySelectorAll('.drawer-tab-panel').forEach(function (p) {
                    p.classList.toggle('d-none', p.dataset.tab !== target);
                });
            });
        });

        // Role select → update inherited preview
        const roleSelect = document.getElementById('drawer-role-id');
        if (roleSelect) {
            roleSelect.addEventListener('change', function () {
                updateInheritedPreview(drawer, roleSelect.value);
            });
        }

        // Permission profile template
        const profileSelect = document.getElementById('drawer-permission-profile');
        const profileApply  = document.getElementById('drawer-profile-apply');
        if (profileApply && profileSelect) {
            profileApply.addEventListener('click', function () {
                applyPermissionProfile(drawer, profileSelect.value);
            });
        }

        // Accordion groups
        drawer.querySelectorAll('.perm-group-head').forEach(function (head) {
            head.addEventListener('click', function () {
                const body = head.nextElementSibling;
                if (!body) return;
                const isOpen = !body.classList.contains('d-none');
                body.classList.toggle('d-none', isOpen);
                head.setAttribute('aria-expanded', String(!isOpen));
            });
        });
    }

    function openDrawer(row, drawer) {
        var userData;
        try {
            userData = JSON.parse(row.dataset.user || '{}');
        } catch (e) {
            userData = {};
        }

        // Mark active row
        if (activeRow) activeRow.classList.remove('table-active');
        activeRow = row;
        row.classList.add('table-active');

        // Populate header
        const avatarEl  = drawer.querySelector('.drawer-avatar');
        const nameEl    = drawer.querySelector('.drawer-user-name');
        const metaEl    = drawer.querySelector('.drawer-user-meta');
        const linkEl    = drawer.querySelector('.drawer-profile-link');
        const deleteBtn = drawer.querySelector('.drawer-delete-btn');

        if (avatarEl) {
            const initial = String(userData.display_full_name || userData.username || 'U').charAt(0).toUpperCase();
            if (userData.profile_photo_url) {
                avatarEl.innerHTML = '<img src="' + userData.profile_photo_url + '" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:inherit">';
            } else {
                avatarEl.textContent = initial;
            }
        }
        if (nameEl) nameEl.textContent = userData.display_full_name || userData.username || '—';
        if (metaEl) metaEl.textContent = (userData.username || '') + ' · ' + (userData.especialidad || '');
        if (linkEl) linkEl.href = '/usuarios/' + userData.id + '/edit';
        if (deleteBtn) {
            deleteBtn.dataset.userId   = userData.id;
            deleteBtn.dataset.username = userData.username || '';
            deleteBtn.disabled         = (Number(userData.id) === Number(currentUserId));
        }

        // Role select
        const roleSelect = document.getElementById('drawer-role-id');
        if (roleSelect) roleSelect.value = String(userData.role_id || '0');

        // Form action
        const drawerForm = drawer.querySelector('form.drawer-form');
        if (drawerForm) drawerForm.action = '/usuarios/' + userData.id;

        // Permissions
        populatePermissions(drawer, userData);

        // Show drawer
        drawer.classList.remove('d-none');
        drawer.removeAttribute('hidden');

        // Focus close button
        const closeBtn = document.getElementById('usuarios-drawer-close');
        if (closeBtn) closeBtn.focus();
    }

    function closeDrawer(drawer) {
        drawer.classList.add('d-none');
        drawer.setAttribute('hidden', '');
        if (activeRow) {
            activeRow.classList.remove('table-active');
            activeRow = null;
        }
    }

    function populatePermissions(drawer, userData) {
        const directPerms = new Set(Array.isArray(userData.permisos_lista) ? userData.permisos_lista : []);
        const roleId      = String(userData.role_id || '0');
        const rolePerms   = new Set(Array.isArray(rolesWithPermissions[roleId]) ? rolesWithPermissions[roleId] : []);

        drawer.querySelectorAll('input[name="permissions[]"]').forEach(function (cb) {
            const key      = cb.value;
            const isDirect = directPerms.has(key);
            const inherited = !isDirect && rolePerms.has(key);

            cb.checked  = isDirect || inherited;
            cb.disabled = inherited;
            cb.dataset.direct = isDirect ? '1' : '0';

            const inheritedTag = cb.closest('.perm-check') ? cb.closest('.perm-check').querySelector('.inherited-tag') : null;
            if (inheritedTag) inheritedTag.classList.toggle('d-none', !inherited);
        });
    }

    function updateInheritedPreview(drawer, newRoleId) {
        const rolePerms = new Set(Array.isArray(rolesWithPermissions[String(newRoleId)]) ? rolesWithPermissions[String(newRoleId)] : []);

        drawer.querySelectorAll('input[name="permissions[]"]').forEach(function (cb) {
            if (cb.dataset.direct === '1') return; // never change direct perms

            const inherited = rolePerms.has(cb.value);
            cb.checked  = inherited;
            cb.disabled = inherited;

            const inheritedTag = cb.closest('.perm-check') ? cb.closest('.perm-check').querySelector('.inherited-tag') : null;
            if (inheritedTag) inheritedTag.classList.toggle('d-none', !inherited);
        });
    }

    function applyPermissionProfile(drawer, profileKey) {
        const profiles = (window.__USUARIOS_INDEX__ || {}).permissionProfiles || {};
        const profile  = profiles[profileKey];
        if (!profile || !Array.isArray(profile.permissions)) return;

        const selected = new Set(profile.permissions);
        drawer.querySelectorAll('input[name="permissions[]"]').forEach(function (cb) {
            if (!cb.disabled) {
                cb.checked = selected.has(cb.value);
            }
        });
    }

    // ─── DELETE MODAL ────────────────────────────────────────────────────────

    function initDeleteModal() {
        const modal       = document.getElementById('delete-user-modal');
        const cancelBtn   = document.getElementById('delete-modal-cancel');
        const confirmForm = document.getElementById('delete-modal-form');
        const userLabel   = document.getElementById('delete-modal-username');
        if (!modal) return;

        document.addEventListener('click', function (e) {
            const deleteBtn = e.target.closest('.drawer-delete-btn');
            if (!deleteBtn || deleteBtn.disabled) return;

            const userId   = deleteBtn.dataset.userId;
            const username = deleteBtn.dataset.username || 'este usuario';

            if (userLabel)   userLabel.textContent = username;
            if (confirmForm) confirmForm.action    = '/usuarios/' + userId + '/delete';

            modal.classList.remove('d-none');
            modal.removeAttribute('hidden');
            if (cancelBtn) cancelBtn.focus();
        });

        function closeModal() {
            modal.classList.add('d-none');
            modal.setAttribute('hidden', '');
        }

        if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !modal.classList.contains('d-none')) closeModal();
        });
    }

})();
