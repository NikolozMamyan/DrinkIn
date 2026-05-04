import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['quantity', 'total', 'favorite', 'tab', 'panel', 'submitButton'];
    static values = {
        unitPrice: Number,
        productId: Number,
        productSlug: String,
    };

    connect() {
        this.updateTotal();
    }

    increment() {
        this.quantityTarget.textContent = String(Number(this.quantityTarget.textContent) + 1);
        this.updateTotal();
    }

    decrement() {
        const next = Math.max(1, Number(this.quantityTarget.textContent) - 1);
        this.quantityTarget.textContent = String(next);
        this.updateTotal();
    }

    switchTab(event) {
        const current = event.params.tab;
        this.tabTargets.forEach((tab) => tab.classList.toggle('active', tab.dataset.tab === current));
        this.panelTargets.forEach((panel) => {
            panel.hidden = panel.dataset.panel !== current;
        });
    }

    async toggleFavorite() {
        const response = await fetch(`/api/cart/favorites/${this.productSlugValue}`, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const payload = await response.json();
        this.favoriteTarget.classList.toggle('is-active', payload.favorite);
        this.favoriteTarget.innerHTML = payload.favorite ? '<i class="fa-solid fa-heart"></i>' : '<i class="fa-regular fa-heart"></i>';
    }

    async addToCart() {
        const quantity = Number(this.quantityTarget.textContent);
        const originalContent = this.submitButtonTarget.innerHTML;
        this.submitButtonTarget.disabled = true;
        const response = await fetch('/api/cart/items', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ productId: this.productIdValue, quantity }),
        });
        const payload = await response.json();

        document.querySelectorAll('.badge').forEach((badge) => {
            badge.textContent = payload.count;
            badge.hidden = payload.count === 0;
        });

        this.submitButtonTarget.classList.add('is-success');
        this.submitButtonTarget.innerHTML = '<i class="fa-solid fa-check"></i> Ajoute';
        this.dispatch('toast', { detail: { message: 'Produit ajoute au panier' }, prefix: 'app' });

        window.setTimeout(() => {
            this.submitButtonTarget.classList.remove('is-success');
            this.submitButtonTarget.innerHTML = originalContent;
            this.submitButtonTarget.disabled = false;
        }, 1200);
    }

    updateTotal() {
        const quantity = Number(this.quantityTarget.textContent);
        const total = (this.unitPriceValue * quantity / 100).toFixed(2).replace('.', ',');
        this.totalTargets.forEach((target) => {
            target.textContent = `${total}EUR`;
        });
    }
}
