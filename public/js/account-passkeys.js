(() => {
    const credentialType = globalThis.PublicKeyCredential;
    if (!isSecureContext || !credentialType?.parseCreationOptionsFromJSON
        || !credentialType.parseRequestOptionsFromJSON || !credentialType.prototype.toJSON) {
        return;
    }

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const headers = { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf };
    async function request(url, data) {
        const options = data ? { method: 'POST', headers, body: JSON.stringify(data) } : { headers };
        const response = await fetch(url, options);
        if (response.status === 423) throw new Error('Confirm your password again before managing passkeys.');
        if (!response.ok) throw new Error('The passkey request failed. Try again or use your password.');
        return response.json();
    }

    for (const form of document.querySelectorAll('[data-passkey-login], [data-passkey-registration]')) {
        const registration = form.hasAttribute('data-passkey-registration');
        const message = form.querySelector('[data-passkey-message]');
        const button = form.querySelector('button');
        form.hidden = false;
        const unsupported = form.nextElementSibling;
        if (unsupported?.hasAttribute('data-passkey-unsupported')) unsupported.hidden = true;

        form.addEventListener('submit', async event => {
            event.preventDefault();
            button.disabled = true;
            message.textContent = '';
            try {
                const { options } = await request(form.dataset.options);
                const credential = registration
                    ? await navigator.credentials.create({ publicKey: credentialType.parseCreationOptionsFromJSON(options) })
                    : await navigator.credentials.get({ publicKey: credentialType.parseRequestOptionsFromJSON(options) });
                if (!credential) throw new Error('No passkey was selected.');
                const data = { credential: credential.toJSON() };
                if (registration) data.name = new FormData(form).get('name');
                const result = await request(form.dataset.submit, data);
                if (registration) {
                    location.reload();
                } else {
                    const destination = new URL(result.redirect, location.href);
                    if (destination.origin !== location.origin) throw new Error('Invalid sign-in destination.');
                    location.assign(destination);
                }
            } catch (error) {
                message.textContent = error.name === 'NotAllowedError'
                    ? 'The passkey prompt was dismissed or no matching credential was found.'
                    : error.message;
            } finally {
                button.disabled = false;
            }
        });
    }
})();
