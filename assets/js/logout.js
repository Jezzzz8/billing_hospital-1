document.addEventListener('click', async (event) => {
    const button = event.target.closest('#logoutBtn');
    if (!button) {
        return;
    }

    event.preventDefault();
    button.disabled = true;

    const baseUrl = document.body?.dataset?.baseUrl || '';
    const roleLoginPages = {
        1: 'admin-login.html',
        2: 'doctor-login.html',
        3: 'nurse-login.html',
        4: 'cashier-login.html',
        5: 'department-login.html',
    };
    const roleId = Number(document.body?.dataset?.userRole || 0);
    let redirectUrl = button.dataset.logoutRedirect || `${baseUrl}/${roleLoginPages[roleId] || 'index.php'}`;

    try {
        const response = await axios.post(`${baseUrl}/api/logout.php`, {}, {
            headers: { 'Content-Type': 'application/json' },
            withCredentials: true,
        });
        if (!response.data?.success) {
            throw new Error('The logout endpoint did not confirm sign-out.');
        }
        if (typeof response.data.redirect === 'string' && response.data.redirect !== '') {
            redirectUrl = response.data.redirect;
        }
    } catch (error) {
        console.error('[logout] Sign-out request failed.', error);
    }
    window.location.href = redirectUrl;
});