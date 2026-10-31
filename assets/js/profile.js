(function () {
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
