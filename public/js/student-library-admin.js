const form = document.querySelector('[data-library-form]');
const access = form.querySelector('[data-library-access]');
const loanFields = form.querySelectorAll('[data-loan-option]');
const download = form.querySelector('[data-download-option]');

function syncAccess() {
    const borrowable = access.value === 'loan';
    for (const field of loanFields) {
        field.hidden = !borrowable;
        field.querySelector('input').required = borrowable;
    }
    download.hidden = borrowable;
    download.querySelector('input').disabled = borrowable;
}

access.addEventListener('change', syncAccess);
syncAccess();
