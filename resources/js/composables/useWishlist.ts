import { usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { xsrfToken } from '@/lib/xsrf';

export type WishlistItem = {
    product_id: number;
    slug: string;
    name: string;
    code: string;
    price: number;
    image: string | null;
    stock?: number;
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
let isSyncing = false;
let hasSynced = false;

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
    const page = usePage();
    const isAuthenticated = computed(() => !!page.props.auth?.user);

    const count = computed(() => items.value.length);

    const has = (productId: number) =>
        items.value.some((i) => i.product_id === productId);

    /**
     * Sincroniza los favoritos locales con la base de datos para usuarios autenticados.
     */
    const sync = async (force: boolean = false) => {
        if (!isAuthenticated.value || isSyncing || (hasSynced && !force)) {
            return;
        }

        isSyncing = true;

        try {
            const productIds = items.value.map((i) => i.product_id);

            const res = await fetch('/favoritos/sincronizar', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ product_ids: productIds }),
            });

            if (res.ok) {
                const data = await res.json();

                if (Array.isArray(data.items)) {
                    items.value = data.items;
                    hasSynced = true;
                }
            }
        } catch {
            // Error de red silencioso: mantiene los favoritos locales
        } finally {
            isSyncing = false;
        }
    };

    const remove = async (productId: number) => {
        const wasPresent = has(productId);
        items.value = items.value.filter((i) => i.product_id !== productId);

        if (wasPresent && isAuthenticated.value) {
            try {
                await fetch(`/favoritos/toggle/${productId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': xsrfToken(),
                    },
                    credentials: 'same-origin',
                });
            } catch {
                // Silencioso
            }
        }
    };

    /** Agrega o quita el producto; devuelve true si quedó agregado. */
    const toggle = (item: WishlistItem): boolean => {
        const wasAdded = !has(item.product_id);

        if (!wasAdded) {
            remove(item.product_id);

            return false;
        }

        items.value.push(item);

        if (isAuthenticated.value) {
            fetch(`/favoritos/toggle/${item.product_id}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                credentials: 'same-origin',
            }).catch(() => {});
        }

        return true;
    };

    const clear = () => {
        items.value = [];
    };

    return { items, count, has, toggle, remove, clear, sync };
}
