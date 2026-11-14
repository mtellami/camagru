/** Attach password visibility controls and account-form validation feedback. */
export function initAuth() {
    document.addEventListener('click', function (event) {
        const toggle = event.target.closest('.password-visibility');
        if (!toggle) {
            return;
        }
        const input = toggle.parentElement.querySelector('input');
        if (!input) {
            return;
        }
        const showing = input.type === 'password';
        input.type = showing ? 'text' : 'password';
        toggle.setAttribute('aria-label', showing ? 'Hide password' : 'Show password');
    });
    const authRules = document.querySelector('.auth-password-rules');
    if (authRules) {
        const authForm = authRules.closest('form');
        const authPassword = authForm.elements.password;
        const authConfirmation = authForm.elements.confirmation;
        /** Update rule feedback and allow submission only when both passwords are valid. */
        function checkAuthPassword() {
            const value = authPassword.value;
            const length = Array.from(value).length;
            const rules = {
                length: length >= 15 && length <= 128,
                letter: /[A-Za-z]/.test(value),
                number: /[0-9]/.test(value),
            };
            authRules.querySelectorAll('[data-rule]').forEach(function (rule) {
                rule.classList.toggle('is-valid', rules[rule.getAttribute('data-rule')]);
            });
            authConfirmation.setCustomValidity(
                authConfirmation.value && authConfirmation.value !== value
                    ? 'Passwords must match.'
                    : '',
            );
            authForm.querySelector('button[type="submit"]').disabled = !(
                rules.length &&
                rules.letter &&
                rules.number &&
                value === authConfirmation.value &&
                authForm.checkValidity()
            );
        }
        authForm.addEventListener('input', checkAuthPassword);
        authForm.addEventListener('change', checkAuthPassword);
        window.addEventListener('pageshow', checkAuthPassword);
        checkAuthPassword();
    }
}
