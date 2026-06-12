import { computed, ref, watch } from 'vue';

export type WishlistItem = {
    product_id: number;
    slug: string;
    name: string;
    code: string;
    price: number;
    image: string | null;
};

// ─── Persistencia ─────────────────────────────────────────────────────────────

/** Versión de la clave — incrementar si cambia la forma del dato guardado. */
const STORAGE_KEY = 'catalog_wishlist_v1';

const loadFromStorage = (): WishlistItem[] => {
    if (typeof window === 'undefined') {
        return [];
    }

    try {
        const raw = localStorage.getItem(STORAGE_KEY);

        if (!raw) {
            return [];
        }

        const items = JSON.parse(raw);

        return Array.isArray(items) ? items : [];
    } catch {
        return [];
    }
};

const saveToStorage = (items: WishlistItem[]) => {
    if (typeof window === 'undefined') {
        return;
    }

    if (items.length === 0) {
        localStorage.removeItem(STORAGE_KEY);

        return;
    }

    localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
};

// ─── Estado global ────────────────────────────────────────────────────────────

const items = ref<WishlistItem[]>(loadFromStorage());

watch(items, (v) => saveToStorage(v), { deep: true });

// Sincronizar entre pestañas del mismo origen
if (typeof window !== 'undefined') {
    window.addEventListener('storage', (e) => {
        if (e.key === STORAGE_KEY) {
            items.value = loadFromStorage();
        }
    });
}

// ─── Composable ───────────────────────────────────────────────────────────────

export function useWishlist() {
    const count = computed(() => items.value.length);

    const has = (productId: number) =>
        items.value.some((i) => i.product_id === productId);

    const remove = (productId: number) => {
        items.value = items.value.filter((i) => i.product_id !== productId);
    };

    /** Agrega o quita el producto; devuelve true si quedó agregado. */
    const toggle = (item: WishlistItem): boolean => {
        if (has(item.product_id)) {
            remove(item.product_id);

            return false;
        }

        items.value.push(item);

        return true;
    };

    const clear = () => {
        items.value = [];
    };

    return { items, count, has, toggle, remove, clear };
}
