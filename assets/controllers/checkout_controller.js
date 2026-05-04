import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['cardInner', 'numberDisplay', 'nameDisplay', 'expiryDisplay', 'cvvDisplay', 'modal', 'processing', 'otp', 'main', 'success', 'successNumber', 'guestFirstName', 'guestLastName', 'guestEmail', 'guestPhone', 'guestStreet', 'guestPostalCode', 'guestCity', 'guestCountryCode', 'guestPassword', 'guestAccountPanel', 'payButton'];
    static values = {
        checkoutUrl: String,
        guestAccountUrl: String,
        authenticated: Boolean,
        deliveryMode: String,
        hasSavedAddress: Boolean,
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
        if (!this.canCheckout()) {
            return;
        }

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
        const requestInit = {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        };

        if (!this.authenticatedValue) {
            requestInit.headers['Content-Type'] = 'application/json';
            requestInit.body = JSON.stringify({
                firstName: this.guestFirstNameTarget.value,
                lastName: this.guestLastNameTarget.value,
                email: this.guestEmailTarget.value,
                phone: this.guestPhoneTarget.value,
                street: this.hasGuestStreetTarget ? this.guestStreetTarget.value : '',
                postalCode: this.hasGuestPostalCodeTarget ? this.guestPostalCodeTarget.value : '',
                city: this.hasGuestCityTarget ? this.guestCityTarget.value : '',
                countryCode: this.hasGuestCountryCodeTarget ? (this.guestCountryCodeTarget.value || 'FR') : 'FR',
            });
        }

        const response = await fetch(this.checkoutUrlValue, requestInit);
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
        if (!this.authenticatedValue && payload.guestCheckout && this.hasGuestAccountPanelTarget) {
            this.guestAccountPanelTarget.hidden = false;
        }
        document.querySelectorAll('.badge').forEach((badge) => {
            badge.textContent = '0';
            badge.hidden = true;
        });
    }

    async createGuestAccount() {
        const response = await fetch(this.guestAccountUrlValue, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                orderNumber: this.successNumberTarget.textContent.trim(),
                password: this.guestPasswordTarget.value,
            }),
        });
        const payload = await response.json();

        if (!response.ok || !payload.ok) {
            this.dispatch('toast', { detail: { message: payload.message || 'Impossible de creer le compte' }, prefix: 'app' });
            return;
        }

        window.location.assign(payload.redirectUrl || '/commandes');
    }

    canCheckout() {
        if (this.requiresAddress() && this.authenticatedValue && !this.hasSavedAddressValue) {
            this.dispatch('toast', { detail: { message: 'Ajoutez une adresse de livraison avant de payer.' }, prefix: 'app' });
            return false;
        }

        if (!this.authenticatedValue && !this.validateGuestForm()) {
            return false;
        }

        return true;
    }

    requiresAddress() {
        return this.deliveryModeValue !== 'pickup';
    }

    validateGuestForm() {
        const requiredFields = [
            this.guestFirstNameTarget,
            this.guestLastNameTarget,
            this.guestEmailTarget,
        ];

        if (this.requiresAddress()) {
            if (this.hasGuestStreetTarget) {
                requiredFields.push(this.guestStreetTarget);
            }
            if (this.hasGuestPostalCodeTarget) {
                requiredFields.push(this.guestPostalCodeTarget);
            }
            if (this.hasGuestCityTarget) {
                requiredFields.push(this.guestCityTarget);
            }
        }

        const invalidField = requiredFields.find((field) => !field.value.trim());
        if (invalidField) {
            invalidField.focus();
            this.dispatch('toast', {
                detail: {
                    message: this.requiresAddress()
                        ? 'Renseignez vos informations et votre adresse avant de payer.'
                        : 'Renseignez vos informations avant de payer.',
                },
                prefix: 'app',
            });

            return false;
        }

        return true;
    }
}
