// AGRIGUARD login interactions
// - Toggle password visibility without relying on Lucide's DOM replacement.

document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.querySelector('[data-password-toggle]');
    const passwordInput = document.querySelector('[data-password-input]');
    if (!toggleBtn || !passwordInput) return;

    const eyeIconWrap = toggleBtn.querySelector('[data-eye-icon]');
    const eyeOffIconWrap = toggleBtn.querySelector('[data-eye-off-icon]');

    const sync = () => {
        const isPasswordHidden = passwordInput.type === 'password';

        if (eyeIconWrap) eyeIconWrap.classList.toggle('hidden', !isPasswordHidden);
        if (eyeOffIconWrap) eyeOffIconWrap.classList.toggle('hidden', isPasswordHidden);

        toggleBtn.setAttribute('aria-label', isPasswordHidden ? 'Show password' : 'Hide password');
    };

    toggleBtn.addEventListener('click', () => {
        passwordInput.type = passwordInput.type === 'password' ? 'text' : 'password';
        sync();
    });

    sync();
});

