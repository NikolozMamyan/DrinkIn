import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'profileModal',
        'addressModal',
        'addresses',
        'firstName',
        'lastName',
        'email',
        'phone',
        'addressId',
        'addressLabel',
        'addressStreet',
        'addressPostalCode',
        'addressCity',
        'addressCountryCode',
        'addressIcon',
        'addressDefault',
        'addressModalTitle',
        'deleteAddressButton',
    ];

    connect() {
        this.applyThemePreference(this.currentDarkModeState());
    }

    openProfileModal() {
        this.profileModalTarget.classList.add('is-open');
        document.body.classList.add('has-overlay');
    }

    closeProfileModal(event) {
        if (!event || event.target === this.profileModalTarget) {
            this.profileModalTarget.classList.remove('is-open');
            this.syncOverlayState();
        }
    }

    async toggle(event) {
        const button = event.currentTarget;
        const key = button.dataset.key;
        const enabled = !button.classList.contains('is-on');
        const previous = button.classList.contains('is-on');

        button.disabled = true;
        button.classList.toggle('is-on', enabled);
        button.setAttribute('aria-pressed', enabled ? 'true' : 'false');

        if (key === 'darkModeEnabled') {
            this.applyThemePreference(enabled);
            this.persistThemeLocally(enabled);
        }

        try {
            await this.ensureBrowserCapability(key, enabled);

            const response = await this.submitPreference(key, enabled);

            if (!response.ok) {
                throw new Error('Impossible de sauvegarder ce parametre.');
            }

            const payload = await response.json();
            if (payload.profile) {
                this.refreshProfile(payload.profile);
            } else if (key === 'darkModeEnabled' && typeof payload.darkModeEnabled === 'boolean') {
                this.applyThemePreference(payload.darkModeEnabled);
            } else if (key === 'darkModeEnabled') {
                this.applyThemePreference(enabled);
            }

            this.toast(this.preferenceMessage(key, enabled));
        } catch (error) {
            button.classList.toggle('is-on', previous);
            button.setAttribute('aria-pressed', previous ? 'true' : 'false');

            if (key === 'darkModeEnabled') {
                this.applyThemePreference(previous);
                this.persistThemeLocally(previous);
            }

            this.toast(error.message || 'Impossible de mettre a jour ce parametre.');
        } finally {
            button.disabled = false;
        }
    }

    openCreateAddressModal() {
        this.resetAddressForm();
        this.addressModalTitleTarget.textContent = 'Ajouter une adresse';
        this.deleteAddressButtonTarget.hidden = true;
        this.addressModalTarget.classList.add('is-open');
        document.body.classList.add('has-overlay');
    }

    openAddressEditor(event) {
        const address = JSON.parse(event.currentTarget.dataset.address);
        this.addressIdTarget.value = address.id ?? '';
        this.addressLabelTarget.value = address.label ?? '';
        this.addressStreetTarget.value = address.street ?? '';
        this.addressPostalCodeTarget.value = address.postalCode ?? '';
        this.addressCityTarget.value = address.city ?? '';
        this.addressCountryCodeTarget.value = address.countryCode ?? 'FR';
        this.addressIconTarget.value = address.icon ?? 'house';
        this.addressDefaultTarget.checked = Boolean(address.isDefault);
        this.addressModalTitleTarget.textContent = 'Modifier une adresse';
        this.deleteAddressButtonTarget.hidden = false;
        this.addressModalTarget.classList.add('is-open');
        document.body.classList.add('has-overlay');
    }

    closeAddressModal(event) {
        if (!event || event.target === this.addressModalTarget) {
            this.addressModalTarget.classList.remove('is-open');
            this.resetAddressForm();
            this.syncOverlayState();
        }
    }

    async submitProfile(event) {
        event.preventDefault();

        const response = await fetch('/api/profile', {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({
                firstName: this.firstNameTarget.value,
                lastName: this.lastNameTarget.value,
                email: this.emailTarget.value,
                phone: this.phoneTarget.value,
            }),
        });

        await this.handleProfileResponse(response, 'Profil mis a jour.');

        if (response.ok) {
            this.closeProfileModal();
        }
    }

    async submitAddress(event) {
        event.preventDefault();

        const addressId = this.addressIdTarget.value;
        const url = addressId ? `/api/profile/addresses/${addressId}` : '/api/profile/addresses';
        const method = addressId ? 'PUT' : 'POST';
        const response = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({
                label: this.addressLabelTarget.value,
                street: this.addressStreetTarget.value,
                postalCode: this.addressPostalCodeTarget.value,
                city: this.addressCityTarget.value,
                countryCode: this.addressCountryCodeTarget.value,
                icon: this.addressIconTarget.value,
                isDefault: this.addressDefaultTarget.checked,
            }),
        });

        await this.handleProfileResponse(response, addressId ? 'Adresse mise a jour.' : 'Adresse ajoutee.');

        if (response.ok) {
            this.closeAddressModal();
        }
    }

    async deleteAddress() {
        const addressId = this.addressIdTarget.value;
        if (!addressId) {
            return;
        }

        const response = await fetch(`/api/profile/addresses/${addressId}`, {
            method: 'DELETE',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });

        await this.handleProfileResponse(response, 'Adresse supprimee.');

        if (response.ok) {
            this.closeAddressModal();
        }
    }

    async handleProfileResponse(response, successMessage) {
        const payload = await response.json();

        if (!response.ok) {
            this.toast(payload.message ?? 'Une erreur est survenue.');

            return;
        }

        if (payload.profile) {
            this.refreshProfile(payload.profile);
        }

        this.toast(successMessage);
    }

    refreshProfile(profile) {
        this.element.querySelector('.profile-hero .headline').textContent = profile.fullName;
        this.element.querySelector('.profile-hero .muted').textContent = [profile.email, profile.phone].filter(Boolean).join(' - ');
        this.element.querySelector('.avatar').textContent = profile.initial;
        this.firstNameTarget.value = profile.firstName ?? '';
        this.lastNameTarget.value = profile.lastName ?? '';
        this.emailTarget.value = profile.email ?? '';
        this.phoneTarget.value = profile.phone ?? '';
        this.addressesTarget.innerHTML = this.addressesMarkup(profile.addresses ?? []);
        this.element.querySelectorAll('.toggle-switch[data-key]').forEach((button) => {
            const key = button.dataset.key;
            const enabled = Boolean(profile.preferences?.[key]);
            button.classList.toggle('is-on', enabled);
            button.setAttribute('aria-pressed', enabled ? 'true' : 'false');
        });
        this.applyThemePreference(Boolean(profile.preferences?.darkModeEnabled));
    }

    addressesMarkup(addresses) {
        if (addresses.length === 0) {
            return `
                <div class="summary-box empty-state empty-state-compact">
                    <p class="card-name">Aucune adresse enregistree</p>
                    <p class="small-copy">Ajoutez une adresse de livraison pour finaliser vos prochaines commandes.</p>
                </div>
            `;
        }

        return addresses.map((address) => {
            const encoded = this.escapeHtml(JSON.stringify(address));
            const badge = address.isDefault ? '<span class="chip">Principale</span>' : '';

            return `
                <div class="address-card ${address.isDefault ? 'address-card-default' : ''}">
                    <div class="a-icon"><i class="fa-solid fa-${this.escapeHtml(address.icon)}"></i></div>
                    <div class="address-content">
                        <p class="card-name">${this.escapeHtml(address.label)}</p>
                        <p class="card-sub">${this.escapeHtml(address.address)}</p>
                    </div>
                    <div class="address-actions">
                        ${badge}
                        <button type="button" class="icon-btn icon-btn-sm" data-address="${encoded}" data-action="click->profile#openAddressEditor">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                    </div>
                </div>
            `;
        }).join('');
    }

    resetAddressForm() {
        this.addressIdTarget.value = '';
        this.addressLabelTarget.value = '';
        this.addressStreetTarget.value = '';
        this.addressPostalCodeTarget.value = '';
        this.addressCityTarget.value = '';
        this.addressCountryCodeTarget.value = 'FR';
        this.addressIconTarget.value = 'house';
        this.addressDefaultTarget.checked = false;
    }

    syncOverlayState() {
        const activeOverlay = this.profileModalTarget.classList.contains('is-open') || this.addressModalTarget.classList.contains('is-open');
        document.body.classList.toggle('has-overlay', activeOverlay);
    }

    submitPreference(key, enabled) {
        if (key === 'darkModeEnabled') {
            return fetch('/api/preferences/theme', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ darkModeEnabled: enabled }),
            });
        }

        return fetch('/api/profile/preferences', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ key, enabled }),
        });
    }

    currentDarkModeState() {
        const button = this.element.querySelector('.toggle-switch[data-key="darkModeEnabled"]');

        return button ? button.classList.contains('is-on') : true;
    }

    applyThemePreference(enabled) {
        document.body.classList.toggle('theme-soft-light', !enabled);
        const themeColor = document.querySelector('meta[name="theme-color"]');
        if (themeColor) {
            themeColor.setAttribute('content', enabled ? '#0d0d1a' : '#f6f1e4');
        }
    }

    persistThemeLocally(enabled) {
        const value = enabled ? 'dark' : 'light';

        try {
            window.localStorage.setItem('drinkin_theme', value);
        } catch {
        }

        document.cookie = `drinkin_theme=${value}; path=/; max-age=${60 * 60 * 24 * 365}; samesite=lax`;
    }

    async ensureBrowserCapability(key, enabled) {
        if (!enabled) {
            return;
        }

        if (key === 'notificationsEnabled' && 'Notification' in window) {
            if (Notification.permission === 'denied') {
                throw new Error('Les notifications sont bloquees dans votre navigateur.');
            }

            if (Notification.permission === 'default') {
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    throw new Error('Notifications refusees.');
                }
            }
        }

        if (key === 'locationEnabled' && 'geolocation' in navigator) {
            await new Promise((resolve, reject) => {
                navigator.geolocation.getCurrentPosition(
                    () => resolve(true),
                    () => reject(new Error('Localisation refusee.')),
                    { enableHighAccuracy: false, timeout: 5000, maximumAge: 60000 },
                );
            });
        }
    }

    preferenceMessage(key, enabled) {
        const labels = {
            notificationsEnabled: 'Notifications push',
            locationEnabled: 'Localisation',
            darkModeEnabled: 'Mode sombre',
        };

        return `${labels[key] ?? 'Parametre'} ${enabled ? 'active' : 'desactive'}.`;
    }

    toast(message) {
        document.dispatchEvent(new CustomEvent('app:toast', { detail: { message } }));
    }

    escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#39;');
    }
}
