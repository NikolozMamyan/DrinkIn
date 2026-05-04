import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.boundShow = this.show.bind(this);
        document.addEventListener('app:toast', this.boundShow);
    }

    disconnect() {
        document.removeEventListener('app:toast', this.boundShow);
    }

    show(event) {
        const { message, type = 'success' } = event.detail;
        const icons = {
            success: 'fa-circle-check',
            error: 'fa-circle-xmark',
            info: 'fa-circle-info',
        };

        this.element.replaceChildren();
        const icon = document.createElement('i');
        icon.className = `fa-solid ${icons[type] ?? icons.info}`;
        this.element.append(icon, ' ', message);
        this.element.dataset.type = type;

        this.element.classList.remove('toast-hidden');
        window.clearTimeout(this.timeout);
        this.timeout = window.setTimeout(() => {
            this.element.classList.add('toast-hidden');
        }, 2400);
    }
}
