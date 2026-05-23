/**
 * user-edit.js — Usuarios v2 form: tabs, inherited permissions, role change,
 *                accordion, delete modal, permission profile apply, full-name
 *                auto-compose, subespecialidad enable/disable.
 */
(function () {
    'use strict';

    /* ── Config from Blade ──────────────────────────────────────────────── */
    var cfg = window.__USUARIOS_V2_EDIT__ || {};
    var permissionProfiles   = cfg.permissionProfiles   || {};
    var rolesWithPermissions = cfg.rolesWithPermissions || {};
    var currentRoleId        = String(cfg.currentRoleId || '');
    // directPerms = permissions the user holds directly (not via role)
    var directPerms = new Set(Array.isArray(cfg.directPermissions) ? cfg.directPermissions : []);

    /* ── Tab switching ──────────────────────────────────────────────────── */
    var tabBtns   = Array.from(document.querySelectorAll('.form-tab-btn'));
    var tabPanels = Array.from(document.querySelectorAll('.form-tab-panel'));

    function activateTab(targetTab) {
        tabBtns.forEach(function (btn) {
            var active = btn.dataset.tab === targetTab;
            btn.classList.toggle('active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        tabPanels.forEach(function (panel) {
            if (panel.dataset.tab === targetTab) {
                panel.removeAttribute('hidden');
            } else {
                panel.setAttribute('hidden', '');
            }
        });
    }

    tabBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            activateTab(btn.dataset.tab);
        });
    });

    /* ── Permission accordion ───────────────────────────────────────────── */
    var groupHeads = Array.from(document.querySelectorAll('.perm-group-head'));

    groupHeads.forEach(function (head) {
        function toggle() {
            var body = head.nextElementSibling;
            if (!body) return;
            var isOpen = !body.hasAttribute('hidden');
            body.toggleAttribute('hidden', isOpen);
            head.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
            var icon = head.querySelector('.perm-chevron');
            if (icon) {
                icon.style.transform = isOpen ? '' : 'rotate(180deg)';
            }
        }

        head.addEventListener('click', toggle);

        // Keyboard accessibility: Enter / Space toggle
        head.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggle();
            }
        });
    });

    /* ── Inherited permissions ──────────────────────────────────────────── */
    var allPermInputs = Array.from(
        document.querySelectorAll('input.perm-check[name="permissions[]"]')
    );

    function getInheritedSet(roleId) {
        var perms = rolesWithPermissions[String(roleId)];
        return new Set(Array.isArray(perms) ? perms : []);
    }

    function applyInheritedState(inheritedSet) {
        allPermInputs.forEach(function (input) {
            // Superuser locked by Blade (data-superuser-locked): don't touch
            if (input.hasAttribute('data-superuser-locked')) {
                return;
            }

            var isInherited = inheritedSet.has(input.value);
            var wrap = input.closest('.form-check');

            if (isInherited) {
                input.checked  = true;
                input.disabled = true;
                input.setAttribute('aria-label', input.value + ' (heredado del rol)');

                // Add inherited tag if not already there
                if (wrap && !wrap.querySelector('.inherited-tag')) {
                    var tag = document.createElement('span');
                    tag.className   = 'inherited-tag';
                    tag.textContent = 'rol';
                    wrap.appendChild(tag);
                }
            } else {
                input.disabled = false;
                input.removeAttribute('aria-label');

                // Restore to direct-permission state
                input.checked = directPerms.has(input.value);

                // Remove inherited tag if present
                if (wrap) {
                    var existingTag = wrap.querySelector('.inherited-tag');
                    if (existingTag) existingTag.remove();
                }
            }
        });
    }

    // Track manual changes to direct permissions
    allPermInputs.forEach(function (input) {
        input.addEventListener('change', function () {
            if (input.disabled) return;
            if (input.checked) {
                directPerms.add(input.value);
            } else {
                directPerms.delete(input.value);
            }
        });
    });

    // Initialize inherited state on page load
    applyInheritedState(getInheritedSet(currentRoleId));

    /* ── Role change → update inherited preview ─────────────────────────── */
    var roleSelect = document.getElementById('form-role-id');
    if (roleSelect) {
        roleSelect.addEventListener('change', function () {
            applyInheritedState(getInheritedSet(roleSelect.value));
        });
    }

    /* ── Permission profile apply ───────────────────────────────────────── */
    var applyButton   = document.getElementById('apply_permission_profile');
    var profileSelect = document.getElementById('permission_profile');

    if (applyButton && profileSelect) {
        applyButton.addEventListener('click', function () {
            var key = profileSelect.value;
            if (!key || !permissionProfiles[key] || !Array.isArray(permissionProfiles[key].permissions)) {
                return;
            }
            var profileSet = new Set(permissionProfiles[key].permissions);
            allPermInputs.forEach(function (input) {
                if (input.disabled) return; // don't touch inherited
                var checked = profileSet.has(input.value);
                input.checked = checked;
                if (checked) {
                    directPerms.add(input.value);
                } else {
                    directPerms.delete(input.value);
                }
            });
        });
    }

    /* ── Full name auto-compose ─────────────────────────────────────────── */
    var fullNameInput = document.getElementById('display_full_name');
    var nameFields    = ['first_name', 'middle_name', 'last_name', 'second_last_name']
        .map(function (n) { return document.querySelector('input[name="' + n + '"]'); })
        .filter(Boolean);

    function updateFullName() {
        if (!fullNameInput) return;
        fullNameInput.value = nameFields
            .map(function (f) { return (f.value || '').trim(); })
            .filter(Boolean)
            .join(' ');
    }

    nameFields.forEach(function (f) { f.addEventListener('input', updateFullName); });

    /* ── Subespecialidad group show/hide ────────────────────────────────── */
    var especialidadSelect        = document.getElementById('especialidad');
    var subespecialidadGroup      = document.getElementById('subespecialidad-group');
    var subespecialidadCheckboxes = Array.from(
        document.querySelectorAll('input[name="subespecialidad[]"]')
    );

    function toggleSubespecialidad() {
        if (!especialidadSelect) return;
        var isOftalmologo = especialidadSelect.value === 'Cirujano Oftalmólogo';

        if (subespecialidadGroup) {
            subespecialidadGroup.toggleAttribute('hidden', !isOftalmologo);
        }

        // Uncheck all when hiding so hidden checkboxes are not submitted
        if (!isOftalmologo) {
            subespecialidadCheckboxes.forEach(function (cb) { cb.checked = false; });
        }
    }

    if (especialidadSelect) {
        especialidadSelect.addEventListener('change', toggleSubespecialidad);
        toggleSubespecialidad(); // apply on page load
    }

    /* ── Delete confirmation modal ──────────────────────────────────────── */
    var deleteBtn   = document.querySelector('.form-delete-btn');
    var deleteModal = document.getElementById('delete-user-modal');
    var cancelBtn   = document.getElementById('delete-modal-cancel');

    function openDeleteModal() {
        if (!deleteModal) return;
        deleteModal.removeAttribute('hidden');
        if (cancelBtn) cancelBtn.focus();
    }

    function closeDeleteModal() {
        if (!deleteModal) return;
        deleteModal.setAttribute('hidden', '');
        if (deleteBtn) deleteBtn.focus();
    }

    if (deleteBtn) {
        deleteBtn.addEventListener('click', openDeleteModal);
    }

    if (cancelBtn) {
        cancelBtn.addEventListener('click', closeDeleteModal);
    }

    if (deleteModal) {
        // Click outside dialog closes modal
        deleteModal.addEventListener('click', function (e) {
            if (e.target === deleteModal) closeDeleteModal();
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && deleteModal && !deleteModal.hasAttribute('hidden')) {
            closeDeleteModal();
        }
    });

}());
