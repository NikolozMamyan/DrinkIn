import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['sheet', 'sheetOrderNumber', 'sheetStatus', 'sheetItems', 'timeline', 'star', 'card', 'filterTab'];
    static values = {
        activeFilter: { type: String, default: 'all' },
    };

    connect() {
        this.applyFilter();
    }

    async open(event) {
        const number = event.params.number;
        const response = await fetch(`/api/orders/${number}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const payload = await response.json();

        if (!response.ok || !payload.ok) {
            this.dispatch('toast', { detail: { message: 'Impossible de charger cette commande.' }, prefix: 'app' });

            return;
        }

        const { order } = payload;
        this.sheetOrderNumberTarget.textContent = `#${order.number}`;
        this.sheetItemsTarget.textContent = order.itemsLabel;
        this.sheetStatusTarget.textContent = order.statusLabel;
        this.sheetStatusTarget.className = `status-pill ${order.status}`;
        this.timelineTarget.innerHTML = this.timelineMarkup(order.timeline);
        this.renderRating(order.rating ?? 0);
        this.sheetTarget.classList.add('is-open');
        document.body.classList.add('has-overlay');
    }

    close(event) {
        if (!event || event.target === this.sheetTarget) {
            this.sheetTarget.classList.remove('is-open');
            document.body.classList.remove('has-overlay');
        }
    }

    async rate(event) {
        const rating = Number(event.params.rating);
        const orderNumber = this.sheetOrderNumberTarget.textContent.replace('#', '');

        await fetch(`/api/orders/${orderNumber}/rating`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ rating }),
        });

        this.renderRating(rating);
        this.dispatch('toast', { detail: { message: 'Merci pour votre avis' }, prefix: 'app' });
        window.setTimeout(() => this.close(), 800);
    }

    setFilter(event) {
        this.activeFilterValue = event.params.filter;
        this.applyFilter();
    }

    applyFilter() {
        this.filterTabTargets.forEach((tab) => {
            tab.classList.toggle('active', tab.dataset.filter === this.activeFilterValue);
        });

        this.cardTargets.forEach((card) => {
            const status = card.dataset.status;
            const visible = this.activeFilterValue === 'all'
                || (this.activeFilterValue === 'pending' && status === 'pending')
                || (this.activeFilterValue === 'delivered' && status === 'delivered')
                || (this.activeFilterValue === 'cancelled' && status === 'cancelled');

            card.hidden = !visible;
        });
    }

    renderRating(rating) {
        this.starTargets.forEach((star, index) => {
            star.innerHTML = index < rating ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star"></i>';
        });
    }

    timelineMarkup(steps) {
        return steps.map((step) => `
            <div class="timeline-item ${step.done ? 'done' : ''} ${step.active ? 'active' : ''}">
                <div class="timeline-dot"><i class="fa-solid fa-${step.icon}"></i></div>
                <div>
                    <p class="card-name">${step.label}</p>
                    <p class="card-sub">${step.time}</p>
                </div>
            </div>
        `).join('');
    }
}
