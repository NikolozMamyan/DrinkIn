import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'subtotal',
        'deliveryFee',
        'discount',
        'total',
        'promoCard',
        'promoCode',
        'promoInput',
        'checkoutLabel',
        'itemsContainer',
        'emptyState',
        'filledState',
        'clearButton',
        'headerCount',
        'note',
    ];

    disconnect() {
        window.clearTimeout(this.noteTimeout);
    }

    async updateQuantity(event) {
        const button = event.currentTarget;
        const quantityElement = button.parentElement.querySelector('[data-quantity]');
        const productId = Number(button.dataset.productId);
        const current = Number(quantityElement.textContent);
        const delta = Number(button.dataset.delta);
        const next = Math.max(0, current + delta);

        button.disabled = true;

        const response = await fetch(`/api/cart/items/${productId}`, {
            method: next === 0 ? 'DELETE' : 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: next === 0 ? null : JSON.stringify({ quantity: next }),
        });
        const payload = await response.json();

        if (next === 0) {
            button.closest('[data-cart-item]').remove();
        } else {
            quantityElement.textContent = String(next);
        }

        button.disabled = false;
        this.renderSummary(payload);
    }

    async selectDelivery(event) {
        const option = event.currentTarget;
        option.disabled = true;

        const response = await fetch('/api/cart/delivery', {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ mode: option.dataset.mode }),
        });
        const payload = await response.json();

        this.element.querySelectorAll('[data-delivery-option]').forEach((item) => {
            item.classList.toggle('is-selected', item === option);
            item.disabled = false;
        });

        this.renderSummary(payload);
    }

    async applyPromo() {
        const response = await fetch('/api/cart/promo', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ code: this.promoInputTarget.value }),
        });
        const payload = await response.json();

        this.renderSummary(payload);
        this.toast(
            payload.promoApplied ? 'Code promo applique !' : 'Code promo invalide.',
            payload.promoApplied ? 'success' : 'error'
        );
    }

    async removePromo() {
        const response = await fetch('/api/cart/promo', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ code: '' }),
        });
        const payload = await response.json();

        this.renderSummary(payload);
        this.toast('Code promo retire.');
    }

    queueNoteUpdate() {
        window.clearTimeout(this.noteTimeout);
        this.noteTimeout = window.setTimeout(() => {
            this.saveNote();
        }, 350);
    }

    async clearCart() {
        const response = await fetch('/api/cart', {
            method: 'DELETE',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const payload = await response.json();

        if (this.hasItemsContainerTarget) {
            this.itemsContainerTarget.innerHTML = '';
        }

        this.renderSummary(payload);
        this.toast('Panier vide.');
    }

    async saveNote() {
        if (!this.hasNoteTarget) {
            return;
        }

        const response = await fetch('/api/cart/note', {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ note: this.noteTarget.value }),
        });

        if (response.ok) {
            this.noteTarget.classList.add('is-saved');
            window.setTimeout(() => this.noteTarget.classList.remove('is-saved'), 500);
        }
    }

    renderSummary(payload) {
        this.subtotalTarget.textContent = payload.subtotal;
        this.deliveryFeeTarget.textContent = payload.deliveryFee;
        this.discountTarget.textContent = `-${payload.discount}`;
        this.totalTarget.textContent = payload.total;
        this.checkoutLabelTarget.textContent = `Payer ${payload.total}`;
        this.promoCardTarget.hidden = !payload.promo;
        this.promoCodeTarget.textContent = payload.promo || '';

        if (!payload.promo) {
            this.promoInputTarget.value = '';
        }

        if (this.hasHeaderCountTarget) {
            this.headerCountTarget.textContent = `${payload.count} article${payload.count > 1 ? 's' : ''}`;
        }

        if (this.hasClearButtonTarget) {
            this.clearButtonTarget.hidden = payload.isEmpty;
        }

        if (this.hasEmptyStateTarget) {
            this.emptyStateTarget.hidden = !payload.isEmpty;
        }

        if (this.hasFilledStateTarget) {
            this.filledStateTarget.hidden = payload.isEmpty;
        }

        document.querySelectorAll('.badge').forEach((badge) => {
            badge.textContent = payload.count;
            badge.hidden = payload.count === 0;
        });
    }

    toast(message, type = 'info') {
        document.dispatchEvent(new CustomEvent('app:toast', { detail: { message, type } }));
    }
}
