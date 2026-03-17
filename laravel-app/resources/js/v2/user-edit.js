(function () {
    const profiles = window.__USUARIOS_V2_EDIT__ && window.__USUARIOS_V2_EDIT__.permissionProfiles
        ? window.__USUARIOS_V2_EDIT__.permissionProfiles
        : {};
    const applyButton = document.getElementById('apply_permission_profile');
    const profileSelect = document.getElementById('permission_profile');

    if (!applyButton || !profileSelect) {
        return;
    }

    const permissionInputs = Array.from(document.querySelectorAll('input[name="permissions[]"]'));

    applyButton.addEventListener('click', function () {
        const key = profileSelect.value;
        if (!key || !profiles[key] || !Array.isArray(profiles[key].permissions)) {
            return;
        }

        const selected = new Set(profiles[key].permissions);
        permissionInputs.forEach(function (input) {
            input.checked = selected.has(input.value);
        });
    });
})();
