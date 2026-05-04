import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['cardInner', 'numberDisplay', 'nameDisplay', 'expiryDisplay', 'cvvDisplay', 'modal', 'processing', 'otp', 'main', 'success', 'successNumber'];
    static values = {
        checkoutUrl: String,
    };

    formatNumber(event) {
        const value = event.currentTarget.value.replace(/\D/g, '').slice(0, 16);
        event.currentTarget.value = value.replace(/(.{4})/g, '$1 ').trim();
        this.numberDisplayTarget.textContent = event.currentTarget.value || '•••• •••• •••• ••••';
    }

    updateName(event) {
        this.nameDisplayTarget.textContent = event.currentTarget.value.toUpperCase() || 'VOTRE NOM';
    }

    formatExpiry(event) {
        let value = event.currentTarget.value.replace(/\D/g, '').slice(0, 4);
        if (value.length > 2) {
            value = `${value.slice(0, 2)}/${value.slice(2)}`;
        }
        event.currentTarget.value = value;
        this.expiryDisplayTarget.textContent = value || 'MM/AA';
    }

    flip() {
        this.cardInnerTarget.classList.add('is-flipped');
    }

    unflip() {
        this.cardInnerTarget.classList.remove('is-flipped');
    }

    updateCvv(event) {
        this.cvvDisplayTarget.textContent = event.currentTarget.value || '•••';
    }

    openModal() {
        this.modalTarget.classList.add('is-open');
        document.body.classList.add('has-overlay');
        this.processingTarget.hidden = false;
        this.otpTarget.hidden = true;

        window.setTimeout(() => {
            this.processingTarget.hidden = true;
            this.otpTarget.hidden = false;
        }, 1500);
    }

    async confirm() {
        const response = await fetch(this.checkoutUrlValue, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const payload = await response.json();

        if (!response.ok || !payload.ok) {
            this.dispatch('toast', { detail: { message: payload.message || 'Impossible de confirmer la commande' }, prefix: 'app' });
            return;
        }

        this.successNumberTarget.textContent = payload.orderNumber;
        this.modalTarget.classList.remove('is-open');
        document.body.classList.remove('has-overlay');
        this.mainTarget.hidden = true;
        this.successTarget.hidden = false;
        document.querySelectorAll('.badge').forEach((badge) => {
            badge.textContent = '0';
            badge.hidden = true;
        });
    }
}
