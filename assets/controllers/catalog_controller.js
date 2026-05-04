import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['search', 'listView', 'gridView', 'count', 'categorySection', 'sortSheet', 'emptyState'];
    static values = {
        currentCategory: { type: String, default: 'all' },
        currentView: { type: String, default: 'list' },
        currentSort: { type: String, default: 'featured' },
    };

    connect() {
        this.listItems = this.hasListViewTarget
            ? Array.from(this.listViewTarget.querySelectorAll('[data-category][data-name]'))
            : [];
        this.gridItems = this.hasGridViewTarget
            ? Array.from(this.gridViewTarget.querySelectorAll('[data-category][data-name]'))
            : [];

        if (this.listItems.length > 0 || this.gridItems.length > 0) {
            this.filterProducts();
        }
    }

    disconnect() {
        window.clearTimeout(this.searchTimeout);
    }

    queueFilterProducts() {
        window.clearTimeout(this.searchTimeout);
        this.searchTimeout = window.setTimeout(() => this.filterProducts(), 120);
    }

    setView(event) {
        if (!this.hasListViewTarget || !this.hasGridViewTarget) {
            return;
        }

        this.currentViewValue = event.params.view;
        this.listViewTarget.hidden = this.currentViewValue !== 'list';
        this.gridViewTarget.hidden = this.currentViewValue !== 'grid';

        this.element.querySelectorAll('[data-view-btn]').forEach((button) => {
            button.classList.toggle('active', button.dataset.viewBtn === this.currentViewValue);
        });
    }

    setCategory(event) {
        this.currentCategoryValue = event.params.category;
        this.element.querySelectorAll('[data-category-chip]').forEach((chip) => {
            chip.classList.toggle('active', chip.dataset.categoryChip === this.currentCategoryValue);
        });

        if (this.listItems.length > 0 || this.gridItems.length > 0) {
            this.filterProducts();
        }
    }

    setSort(event) {
        if (!this.hasListViewTarget || !this.hasGridViewTarget) {
            return;
        }

        this.currentSortValue = event.params.sort;
        this.sortCollection(this.listItems, this.listViewTarget);
        this.sortCollection(this.gridItems, this.gridViewTarget);
        this.element.querySelectorAll('[data-sort-option]').forEach((button) => {
            button.classList.toggle('active', button.dataset.sortOption === this.currentSortValue);
        });
        this.closeSort();
        this.filterProducts();
    }

    filterProducts() {
        const query = this.hasSearchTarget ? this.searchTarget.value.trim().toLowerCase() : '';
        const visibleSlugs = new Set();

        [...this.listItems, ...this.gridItems].forEach((item) => {
            const matchesCategory = this.currentCategoryValue === 'all' || item.dataset.category === this.currentCategoryValue;
            const matchesQuery = query === '' || item.dataset.name.includes(query);
            const visibleItem = matchesCategory && matchesQuery;

            item.hidden = !visibleItem;
            if (visibleItem) {
                visibleSlugs.add(item.dataset.slug);
            }
        });

        const visible = visibleSlugs.size;

        if (this.hasCountTarget) {
            this.countTarget.textContent = `${visible} produit${visible > 1 ? 's' : ''}`;
        }

        if (this.hasCategorySectionTarget) {
            this.categorySectionTarget.hidden = query !== '';
        }

        if (this.hasEmptyStateTarget) {
            this.emptyStateTarget.hidden = visible > 0;
        }
    }

    clearSearch() {
        if (!this.hasSearchTarget) {
            return;
        }

        this.searchTarget.value = '';
        this.filterProducts();
    }

    openSort() {
        if (this.hasSortSheetTarget) {
            this.sortSheetTarget.classList.add('is-open');
            document.body.classList.add('has-overlay');
        }
    }

    closeSort(event) {
        if (this.hasSortSheetTarget && (!event || event.target === this.sortSheetTarget)) {
            this.sortSheetTarget.classList.remove('is-open');
            document.body.classList.remove('has-overlay');
        }
    }

    async addToCart(event) {
        event.preventDefault();
        event.stopPropagation();

        const button = event.currentTarget;
        const originalContent = button.innerHTML;
        button.disabled = true;

        const response = await fetch('/api/cart/items', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ productId: Number(button.dataset.productId), quantity: 1 }),
        });
        const payload = await response.json();

        button.classList.add('is-success');
        button.innerHTML = '<i class="fa-solid fa-check"></i>';
        document.dispatchEvent(new CustomEvent('app:toast', { detail: { message: `${button.dataset.productName} ajoute au panier` } }));
        this.updateCartBadges(payload.count);

        window.setTimeout(() => {
            button.classList.remove('is-success');
            button.innerHTML = originalContent;
            button.disabled = false;
        }, 900);
    }

    sortCollection(items, container) {
        const sorted = [...items].sort((left, right) => {
            switch (this.currentSortValue) {
                case 'price-asc':
                    return Number(left.dataset.price) - Number(right.dataset.price);
                case 'price-desc':
                    return Number(right.dataset.price) - Number(left.dataset.price);
                case 'rating':
                    return Number(right.dataset.rating) - Number(left.dataset.rating);
                default:
                    return Number(right.dataset.featured) - Number(left.dataset.featured)
                        || left.dataset.name.localeCompare(right.dataset.name);
            }
        });

        sorted.forEach((item) => container.appendChild(item));
    }

    updateCartBadges(count) {
        document.querySelectorAll('.badge').forEach((badge) => {
            badge.textContent = count;
            badge.hidden = count === 0;
        });
    }
}
