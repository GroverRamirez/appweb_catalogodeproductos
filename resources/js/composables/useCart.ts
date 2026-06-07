import { computed, ref, watch } from 'vue';

export type CartItem = {
    product_id: number;
    slug: string;
    name: string;
    code: string;
    price: number;
    image: string | null;
    quantity: number;
};

// ─── Persistencia con TTL ──────────────────────────────────────────────────────

/** Versión de la clave — incrementar si cambia la forma del dato guardado. */
const STORAGE_KEY = 'catalog_cart_v2';

/** 7 días en milisegundos. Se renueva en cada modificación del carrito. */
const CART_TTL_MS = 7 * 24 * 60 * 60 * 1000;

type StoredCart = {
    items: CartItem[];
    /** timestamp Unix (ms) en que expira el carrito */
    expiresAt: number;
};

const loadFromStorage = (): CartItem[] => {
    if (typeof window === 'undefined') return [];

    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return [];

        const stored: StoredCart = JSON.parse(raw);

        // Expirado → limpiar y devolver vacío
        if (!stored.expiresAt || Date.now() > stored.expiresAt) {
            localStorage.removeItem(STORAGE_KEY);
            return [];
        }

        return Array.isArray(stored.items) ? stored.items : [];
    } catch {
        return [];
    }
};

const saveToStorage = (items: CartItem[]) => {
    if (typeof window === 'undefined') return;

    if (items.length === 0) {
        // Sin items no tiene sentido mantener la entrada
        localStorage.removeItem(STORAGE_KEY);
        return;
    }

    const payload: StoredCart = {
        items,
        expiresAt: Date.now() + CART_TTL_MS, // renovar TTL en cada cambio
    };
    localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
};

// ─── Estado global ─────────────────────────────────────────────────────────────

const items = ref<CartItem[]>(loadFromStorage());
const drawerOpen = ref(false);

/** IDs de productos que la última validación marcó como inactivos/eliminados. */
const removedByValidation = ref<number[]>([]);

/** true mientras se ejecuta la llamada al endpoint de validación. */
const validating = ref(false);

/** Timestamp de la última validación exitosa (solo en memoria, se resetea al recargar). */
let lastValidatedAt = 0;
const VALIDATE_COOLDOWN_MS = 5 * 60 * 1000; // 5 minutos

// ─── Persistencia reactiva ────────────────────────────────────────────────────

watch(items, (v) => saveToStorage(v), { deep: true });

// Sincronizar entre pestañas del mismo origen
if (typeof window !== 'undefined') {
    window.addEventListener('storage', (e) => {
        if (e.key === STORAGE_KEY) {
            items.value = loadFromStorage();
        }
    });
}

// ─── Helpers internos ─────────────────────────────────────────────────────────

const getCsrf = (): string =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

// ─── Composable ───────────────────────────────────────────────────────────────

export function useCart() {
    const count = computed(() =>
        items.value.reduce((sum, i) => sum + i.quantity, 0),
    );

    const subtotal = computed(() =>
        items.value.reduce((sum, i) => sum + i.price * i.quantity, 0),
    );

    const add = (item: Omit<CartItem, 'quantity'>, qty = 1) => {
        const existing = items.value.find((i) => i.product_id === item.product_id);

        if (existing) {
            existing.quantity += qty;
        } else {
            items.value.push({ ...item, quantity: qty });
        }

        drawerOpen.value = true;
    };

    const setQuantity = (productId: number, qty: number) => {
        const item = items.value.find((i) => i.product_id === productId);
        if (!item) return;

        if (qty <= 0) {
            remove(productId);
        } else {
            item.quantity = qty;
        }
    };

    const remove = (productId: number) => {
        items.value = items.value.filter((i) => i.product_id !== productId);
    };

    const clear = () => {
        items.value = [];
    };

    /**
     * Llama a POST /carrito/validar con los IDs actuales.
     * Elimina del carrito los productos que el servidor marque como inactivos.
     *
     * @param force - ignorar el cooldown de 5 minutos (útil en Cart.vue al montar)
     * @returns número de items removidos (0 si nada cambió o el carrito estaba vacío)
     */
    const validate = async (force = false): Promise<number> => {
        if (!items.value.length) return 0;

        const now = Date.now();
        if (!force && now - lastValidatedAt < VALIDATE_COOLDOWN_MS) return 0;

        validating.value = true;
        removedByValidation.value = [];

        try {
            const ids = items.value.map((i) => i.product_id);

            const res = await fetch('/carrito/validar', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ ids }),
            });

            if (!res.ok) return 0;

            const { removed }: { removed: number[] } = await res.json();

            lastValidatedAt = Date.now();

            if (removed.length > 0) {
                removedByValidation.value = removed;
                items.value = items.value.filter(
                    (i) => !removed.includes(i.product_id),
                );
            }

            return removed.length;
        } catch {
            // Red caída u otro error: no modificar el carrito
            return 0;
        } finally {
            validating.value = false;
        }
    };

    return {
        items,
        count,
        subtotal,
        drawerOpen,
        validating,
        removedByValidation,
        add,
        setQuantity,
        remove,
        clear,
        validate,
    };
}
