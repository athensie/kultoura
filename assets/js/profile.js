(function () {
    const eyeIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>';
    const eyeOffIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a18.49 18.49 0 0 1 5.06-5.94M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 7 11 7a18.49 18.49 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/></svg>';
    document.querySelectorAll('[data-password-toggle]').forEach(button => {
        button.addEventListener('click', function () {
            const input = document.getElementById(button.dataset.passwordToggle);
            if (!input) return;
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.innerHTML = show ? eyeOffIcon : eyeIcon;
            button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            button.setAttribute('aria-pressed', String(show));
        });
    });

    const passwordInput = document.getElementById('profileNewPassword');
    const confirmInput = document.getElementById('profileConfirmPassword');
    const requirements = document.getElementById('profilePasswordRequirements');
    if (!passwordInput || !confirmInput || !requirements) return;

    const rules = {
        length: password => password.length >= 8,
        uppercase: password => /[A-Z]/.test(password),
        special: password => /[^A-Za-z0-9]/.test(password),
    };

    function updateRequirements() {
        const password = passwordInput.value;
        Object.entries(rules).forEach(([rule, check]) => {
            const item = requirements.querySelector('[data-rule="' + rule + '"]');
            if (item) item.classList.toggle('met', check(password));
        });
    }

    function updateConfirmation() {
        confirmInput.setCustomValidity(
            confirmInput.value && confirmInput.value !== passwordInput.value
                ? 'The new passwords do not match.'
                : ''
        );
    }

    passwordInput.addEventListener('input', function () {
        updateRequirements();
        updateConfirmation();
    });
    confirmInput.addEventListener('input', updateConfirmation);
})();
