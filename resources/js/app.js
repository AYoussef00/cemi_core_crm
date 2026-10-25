import { initCustomerLocationMap } from './customer-location-map';
import { initAlertRoom } from './alert-room';

function initAuthPasswordToggle() {
    document.querySelectorAll('[data-auth-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const field = button.closest('.auth-field');
            const input = field?.querySelector('#password');
            if (! input || (input.type !== 'password' && input.type !== 'text')) {
                return;
            }

            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-pressed', show ? 'true' : 'false');
            button.setAttribute('aria-label', show ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور');

            const eyeShow = button.querySelector('.auth-eye-show');
            const eyeHide = button.querySelector('.auth-eye-hide');
            if (eyeShow) {
                eyeShow.hidden = show;
            }
            if (eyeHide) {
                eyeHide.hidden = ! show;
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initCustomerLocationMap();
    initAlertRoom();
    initAuthPasswordToggle();
});
