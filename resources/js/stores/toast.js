import { defineStore } from 'pinia';

export const useToastStore = defineStore('toast', {
    state: () => ({ items: [] }),
    actions: {
        show(message, kind = 'success') {
            const id = Date.now() + Math.random();
            this.items.push({ id, message, kind });
            window.setTimeout(() => this.dismiss(id), 4200);
        },
        dismiss(id) {
            this.items = this.items.filter((item) => item.id !== id);
        },
    },
});
