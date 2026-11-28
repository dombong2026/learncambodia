const form = document.querySelector('[data-admin-user-form]');

if (form) {
    const role = form.querySelector('[data-user-role]');
    const permissions = form.querySelector('[data-admin-permissions]');
    const updatePermissions = () => {
        const isAdmin = role.value === 'admin';
        permissions.hidden = !isAdmin;
        permissions.disabled = !isAdmin;
    };

    role.addEventListener('change', updatePermissions);
    updatePermissions();
}
