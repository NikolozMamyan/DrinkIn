import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    async logout(event) {
        event.preventDefault();

        const trigger = event.currentTarget;
        if (trigger instanceof HTMLButtonElement) {
            trigger.disabled = true;
        }

        try {
            const response = await fetch('/api/logout', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Logout failed');
            }

            window.location.replace('/');
        } catch {
            window.location.assign('/deconnexion');
        } finally {
            if (trigger instanceof HTMLButtonElement) {
                trigger.disabled = false;
            }
        }
    }
}
