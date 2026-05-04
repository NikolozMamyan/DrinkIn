import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static SUPPRESSION_KEY = 'drinkin.install.prompt';
    static DISMISS_DAYS = 14;
    static REFUSAL_DAYS = 30;

    connect() {
        if (this.isInstalled()) {
            this.element.hidden = true;

            return;
        }

        if (this.isSuppressed()) {
            this.element.hidden = true;
        }

        this.beforeInstallPromptHandler = (event) => {
            event.preventDefault();
            this.deferredPrompt = event;
            if (!this.isSuppressed() && !this.isInstalled()) {
                this.element.hidden = false;
            }
        };

        this.appInstalledHandler = () => {
            this.deferredPrompt = null;
            this.clearSuppression();
            this.element.hidden = true;
        };

        window.addEventListener('beforeinstallprompt', this.beforeInstallPromptHandler);
        window.addEventListener('appinstalled', this.appInstalledHandler);
    }

    disconnect() {
        window.removeEventListener('beforeinstallprompt', this.beforeInstallPromptHandler);
        window.removeEventListener('appinstalled', this.appInstalledHandler);
    }

    async prompt() {
        if (!this.deferredPrompt) {
            return;
        }

        this.deferredPrompt.prompt();
        const choice = await this.deferredPrompt.userChoice;
        if (choice.outcome !== 'accepted') {
            this.setSuppression(this.constructor.REFUSAL_DAYS);
        } else {
            this.clearSuppression();
        }

        this.deferredPrompt = null;
        this.element.hidden = true;
    }

    dismiss() {
        this.setSuppression(this.constructor.DISMISS_DAYS);
        this.element.hidden = true;
    }

    isInstalled() {
        return window.matchMedia('(display-mode: standalone)').matches
            || window.navigator.standalone === true
            || document.referrer.startsWith('android-app://');
    }

    isSuppressed() {
        try {
            const raw = window.localStorage.getItem(this.constructor.SUPPRESSION_KEY);
            if (!raw) {
                return false;
            }

            const payload = JSON.parse(raw);
            if (typeof payload.until !== 'number' || payload.until <= Date.now()) {
                window.localStorage.removeItem(this.constructor.SUPPRESSION_KEY);

                return false;
            }

            return true;
        } catch {
            return false;
        }
    }

    setSuppression(days) {
        try {
            window.localStorage.setItem(this.constructor.SUPPRESSION_KEY, JSON.stringify({
                until: Date.now() + (days * 24 * 60 * 60 * 1000),
            }));
        } catch {
        }
    }

    clearSuppression() {
        try {
            window.localStorage.removeItem(this.constructor.SUPPRESSION_KEY);
        } catch {
        }
    }
}
