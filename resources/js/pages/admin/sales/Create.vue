<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowLeft,
    Barcode,
    CheckCircle2,
    Loader2,
    Plus,
    ScanBarcode,
    Search,
    Trash2,
    UserCheck,
    UserPlus,
    Volume2,
    VolumeX,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import ProductCombobox from '@/components/admin/ProductCombobox.vue';
import type { ProductOption } from '@/components/admin/ProductCombobox.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatPrice } from '@/lib/catalog';
import type { StoreSettings } from '@/lib/catalog';
import { PAYMENT_METHOD_LABELS } from '@/lib/sales';
import { clientLookup } from '@/routes/admin/sales';

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Ventas', href: '/admin/sales' },
            { title: 'Nueva venta', href: '/admin/sales/create' },
        ],
    }),
});

/** El producto de venta trae además los dos precios para sugerir el de venta. */
type SaleProductOption = ProductOption & {
    price: string;
    sale_price: string | null;
};

const props = defineProps<{
    products: SaleProductOption[];
    paymentMethods: string[];
}>();

const page = usePage();
const store = computed(() => page.props.store as StoreSettings);
const cur = (n: number) => formatPrice(n, store.value.currency_symbol);

const form = useForm({
    customer_name: '',
    customer_phone: '',
    id_card: '',
    payment_method: 'efectivo',
    discount_amount: '' as number | string,
    notes: '',
    // unit_price arranca vacio a proposito: si arrancara en 0, olvidarse de
    // llenarlo registraria la venta regalando el producto en silencio.
    items: [
        {
            product_id: null as number | null,
            quantity: 1,
            unit_price: '' as number | string,
        },
    ],
});

/** Ficha que devuelve la búsqueda por CI o flexible. */
type FoundClient = {
    id: number;
    name: string;
    phone: string | null;
    id_card: string | null;
    sales_count: number;
};

// Estado de la búsqueda por CI: 'found' rellena los datos, 'new' avisa que
// la ficha se crea al registrar la venta.
const ciLookup = ref<'idle' | 'loading' | 'found' | 'new'>('idle');
const foundClient = ref<FoundClient | null>(null);

// Búsqueda flexible de clientes (por Nombre, CI o Teléfono)
const clientSearchQuery = ref('');
const clientSearchResults = ref<FoundClient[]>([]);
const isSearchingClients = ref(false);
const showClientDropdown = ref(false);
const clientSearchContainerRef = ref<HTMLElement | null>(null);
let clientSearchTimer: ReturnType<typeof setTimeout> | undefined;

// Lo último que autocompletamos. Sirve para saber si el campo todavía tiene
// el dato de la ficha (se puede reemplazar) o si alguien lo escribió a mano
// (no se toca).
const autofilled = { name: '', phone: '' };
let ciTimer: ReturnType<typeof setTimeout> | undefined;
let lastCi = '';

/** Escribe el dato en el campo solo si está vacío o sigue siendo el autocompletado. */
const fill = (field: 'customer_name' | 'customer_phone', value: string) => {
    const key = field === 'customer_name' ? 'name' : 'phone';

    if (form[field] === '' || form[field] === autofilled[key]) {
        form[field] = value;
        autofilled[key] = value;
    }
};

const lookupClient = async () => {
    const ci = form.id_card.trim();

    if (ci === lastCi) {
        return;
    }

    lastCi = ci;

    // Con menos de 4 caracteres no hay carnet posible: no se busca.
    if (ci.length < 4) {
        ciLookup.value = 'idle';
        foundClient.value = null;

        return;
    }

    ciLookup.value = 'loading';

    try {
        const res = await fetch(clientLookup.url({ query: { ci } }), {
            headers: { Accept: 'application/json' },
        });

        // Si mientras tanto se siguió escribiendo, esta respuesta ya es vieja.
        if (ci !== form.id_card.trim()) {
            return;
        }

        if (!res.ok) {
            ciLookup.value = 'idle';

            return;
        }

        const data = (await res.json()) as { client: FoundClient | null };
        foundClient.value = data.client;

        if (data.client) {
            ciLookup.value = 'found';
            fill('customer_name', data.client.name);
            fill('customer_phone', data.client.phone ?? '');
        } else {
            ciLookup.value = 'new';
            // Los datos de la ficha anterior eran de otra persona.
            fill('customer_name', '');
            fill('customer_phone', '');
        }
    } catch {
        // Sin red no se autocompleta nada: es una ayuda, no una validacion.
        ciLookup.value = 'idle';
    }
};

watch(
    () => form.id_card,
    () => {
        clearTimeout(ciTimer);
        ciTimer = setTimeout(lookupClient, 400);
    },
);

/** Al salir del campo se busca sin esperar el debounce. */
const onCiBlur = () => {
    clearTimeout(ciTimer);
    lookupClient();
};

/** Búsqueda flexible de clientes en tiempo real */
const searchClients = async () => {
    const q = clientSearchQuery.value.trim();

    if (q.length < 2) {
        clientSearchResults.value = [];
        showClientDropdown.value = false;

        return;
    }

    isSearchingClients.value = true;

    try {
        const res = await fetch(clientLookup.url({ query: { q } }), {
            headers: { Accept: 'application/json' },
        });

        if (res.ok) {
            const data = (await res.json()) as { clients?: FoundClient[] };
            clientSearchResults.value = data.clients ?? [];
            showClientDropdown.value = (data.clients ?? []).length > 0;
        }
    } catch {
        // Fallo de red ignorado en búsqueda en vivo
    } finally {
        isSearchingClients.value = false;
    }
};

const onClientSearchInput = () => {
    clearTimeout(clientSearchTimer);
    clientSearchTimer = setTimeout(searchClients, 300);
};

const selectClient = (client: FoundClient) => {
    foundClient.value = client;
    ciLookup.value = 'found';
    form.id_card = client.id_card ?? '';
    lastCi = client.id_card ?? '';
    form.customer_name = client.name;
    form.customer_phone = client.phone ?? '';
    autofilled.name = client.name;
    autofilled.phone = client.phone ?? '';
    clientSearchQuery.value = '';
    showClientDropdown.value = false;
};

const setConsumidorFinal = () => {
    foundClient.value = null;
    ciLookup.value = 'idle';
    form.id_card = '';
    lastCi = '';
    form.customer_name = 'Consumidor final';
    form.customer_phone = '';
    autofilled.name = 'Consumidor final';
    autofilled.phone = '';
    clientSearchQuery.value = '';
    showClientDropdown.value = false;
};

const clearSelectedClient = () => {
    foundClient.value = null;
    ciLookup.value = 'idle';
    form.id_card = '';
    lastCi = '';
    form.customer_name = '';
    form.customer_phone = '';
    autofilled.name = '';
    autofilled.phone = '';
    clientSearchQuery.value = '';
    showClientDropdown.value = false;
};

// ==========================================
// MODO LECTOR DE CÓDIGO DE BARRAS / SKU (POS)
// ==========================================
const barcodeQuery = ref('');
const barcodeInputRef = ref<HTMLInputElement | null>(null);
const scannerMode = ref(false);
const soundEnabled = ref(true);

const scanFeedback = ref<{
    type: 'success' | 'error';
    message: string;
} | null>(null);
let feedbackTimer: ReturnType<typeof setTimeout> | undefined;

/** Reproduce un tono de confirmación o advertencia usando Web Audio API nativo */
const playBeep = (success = true) => {
    if (!soundEnabled.value || typeof window === 'undefined') {
        return;
    }

    try {
        const AudioContextClass =
            window.AudioContext ||
            (window as unknown as { webkitAudioContext: typeof AudioContext })
                .webkitAudioContext;

        if (!AudioContextClass) {
            return;
        }

        const ctx = new AudioContextClass();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();

        osc.connect(gain);
        gain.connect(ctx.destination);

        if (success) {
            osc.frequency.setValueAtTime(880, ctx.currentTime); // 880Hz (A5)
            gain.gain.setValueAtTime(0.12, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(
                0.001,
                ctx.currentTime + 0.08,
            );
            osc.start();
            osc.stop(ctx.currentTime + 0.08);
        } else {
            osc.frequency.setValueAtTime(260, ctx.currentTime); // 260Hz grave
            gain.gain.setValueAtTime(0.18, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(
                0.001,
                ctx.currentTime + 0.18,
            );
            osc.start();
            osc.stop(ctx.currentTime + 0.18);
        }
    } catch {
        // Soporte de audio no disponible
    }
};

const showFeedback = (type: 'success' | 'error', message: string) => {
    scanFeedback.value = { type, message };
    clearTimeout(feedbackTimer);
    feedbackTimer = setTimeout(() => {
        scanFeedback.value = null;
    }, 3500);
};

const focusBarcodeInput = () => {
    nextTick(() => {
        barcodeInputRef.value?.focus();
    });
};

const toggleScannerMode = () => {
    scannerMode.value = !scannerMode.value;

    if (scannerMode.value) {
        focusBarcodeInput();
    }
};

// Producto al que corresponde el precio que autocompletamos en cada fila.
// Es lo que permite distinguir "cambió de producto" de "volvió a elegir el
// mismo", que es la diferencia entre pisar el precio o respetarlo.
const pricedFor = ref<(number | null)[]>([null]);

const addItem = () => {
    form.items.push({ product_id: null, quantity: 1, unit_price: '' });
    pricedFor.value.push(null);
};
const removeItem = (i: number) => {
    form.items.splice(i, 1);
    pricedFor.value.splice(i, 1);
};

const productById = computed(
    () => new Map(props.products.map((p) => [p.id, p])),
);

/** El precio al que realmente se vende: la oferta si existe, si no el de lista. */
const sellPrice = (product: SaleProductOption) =>
    Number(product.sale_price ?? product.price);

/** Agrega o incrementa un producto escaneado */
const addProductToSale = (product: SaleProductOption) => {
    const existingIndex = form.items.findIndex(
        (item) => item.product_id === product.id,
    );

    if (existingIndex >= 0) {
        // Si ya está en la lista de items, incrementamos la cantidad
        const currentQty = Number(form.items[existingIndex].quantity) || 0;
        form.items[existingIndex].quantity = currentQty + 1;

        if (!form.items[existingIndex].unit_price) {
            form.items[existingIndex].unit_price = sellPrice(product);
        }
    } else if (form.items.length === 1 && form.items[0].product_id === null) {
        // Si hay una única fila inicial vacía, la poblamos
        form.items[0].product_id = product.id;
        form.items[0].quantity = 1;
        form.items[0].unit_price = sellPrice(product);
        pricedFor.value[0] = product.id;
    } else {
        // Agregamos una nueva fila
        form.items.push({
            product_id: product.id,
            quantity: 1,
            unit_price: sellPrice(product),
        });
        pricedFor.value.push(product.id);
    }
};

const handleBarcodeScan = () => {
    const raw = barcodeQuery.value.trim();

    if (!raw) {
        return;
    }

    const normalized = raw.toLowerCase();
    const cleanScanned = normalized.replace(/\s+/g, '');

    // Buscar coincidencia exacta por SKU/código interno
    const product = props.products.find((p) => {
        const cleanCode = p.code.trim().toLowerCase().replace(/\s+/g, '');

        return (
            cleanCode === cleanScanned ||
            p.code.trim().toLowerCase() === normalized
        );
    });

    if (product) {
        playBeep(true);
        addProductToSale(product);
        const qty =
            form.items.find((item) => item.product_id === product.id)
                ?.quantity ?? 1;
        showFeedback(
            'success',
            `✓ ${product.code} · ${product.name} (Cantidad: ${qty})`,
        );
        barcodeQuery.value = '';
    } else {
        playBeep(false);
        showFeedback(
            'error',
            `✕ No se encontró ningún producto con el código "${raw}".`,
        );
    }

    focusBarcodeInput();
};

/**
 * Escucha eventos globales para que en modo escáner, los disparos del lector
 * de código de barras se dirijan al campo sin importar dónde se hizo clic.
 */
const onGlobalKeydown = (e: KeyboardEvent) => {
    if (!scannerMode.value) {
        return;
    }

    const target = e.target as HTMLElement | null;
    const isEditing =
        target &&
        (target.tagName === 'INPUT' ||
            target.tagName === 'TEXTAREA' ||
            target.tagName === 'SELECT' ||
            target.isContentEditable);

    if (target === barcodeInputRef.value) {
        return;
    }

    if (
        !isEditing &&
        e.key.length === 1 &&
        !e.ctrlKey &&
        !e.altKey &&
        !e.metaKey
    ) {
        barcodeInputRef.value?.focus();
    }
};

const onDocumentClick = (e: MouseEvent) => {
    if (
        clientSearchContainerRef.value &&
        !clientSearchContainerRef.value.contains(e.target as Node)
    ) {
        showClientDropdown.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    window.addEventListener('keydown', onGlobalKeydown);
});

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
    window.removeEventListener('keydown', onGlobalKeydown);
});

/**
 * Cambiar de producto invalida el precio de la fila: el que estaba era el del
 * producto anterior. Corregir el producto elegido es el gesto más común del
 * mostrador y, sin pisarlo, la venta quedaba registrada al precio equivocado.
 *
 * Volver a elegir el mismo producto no toca nada, para no borrar un precio
 * negociado a mano.
 */
const onProductPick = (i: number, product: ProductOption) => {
    const full = productById.value.get(product.id);

    if (!full || pricedFor.value[i] === full.id) {
        return;
    }

    pricedFor.value[i] = full.id;
    form.items[i].unit_price = sellPrice(full);
};

/** Stock disponible del producto de la fila, o null si todavía no se eligió. */
const stockOf = (i: number): number | null => {
    const id = form.items[i].product_id;

    return id === null ? null : (productById.value.get(id)?.stock ?? null);
};

// Vender mas de lo que hay se permite (el inventario cargado puede estar
// atrasado), pero se avisa: el stock quedaria negativo.
const exceedsStock = (i: number) => {
    const stock = stockOf(i);

    return stock !== null && (Number(form.items[i].quantity) || 0) > stock;
};

const subtotalOf = (i: number) =>
    (Number(form.items[i].quantity) || 0) *
    (Number(form.items[i].unit_price) || 0);

const subtotal = computed(() =>
    form.items.reduce((sum, _, i) => sum + subtotalOf(i), 0),
);

const discount = computed(() => Number(form.discount_amount) || 0);
const total = computed(() => subtotal.value - discount.value);
const discountTooBig = computed(() => discount.value > subtotal.value);

const submit = () => form.post('/admin/sales');
</script>

<template>
    <Head title="Nueva venta" />

    <div class="w-full space-y-3 p-3 md:p-4">
        <div class="flex items-center gap-2">
            <Button variant="ghost" size="icon-sm" as-child>
                <Link href="/admin/sales"><ArrowLeft class="size-4" /></Link>
            </Button>
            <h1 class="text-xl font-semibold">Nueva venta</h1>
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <!-- Datos del cliente y la venta -->
            <Card class="gap-3 py-4">
                <CardHeader class="pb-0">
                    <CardTitle
                        class="flex items-center justify-between text-base"
                    >
                        <span>Datos del cliente</span>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-7 text-xs"
                            @click="setConsumidorFinal"
                        >
                            Consumidor final
                        </Button>
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <!-- Buscador flexible de cliente por Nombre, CI o Teléfono -->
                    <div ref="clientSearchContainerRef" class="relative">
                        <Label class="mb-1 block text-xs text-muted-foreground">
                            Búsqueda rápida de cliente registrado (Nombre, CI o
                            Teléfono)
                        </Label>
                        <div class="relative">
                            <Search
                                class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                            />
                            <Input
                                v-model="clientSearchQuery"
                                type="text"
                                placeholder="Escribe el nombre, carnet o celular del cliente..."
                                class="pr-8 pl-9"
                                autocomplete="off"
                                @input="onClientSearchInput"
                                @focus="
                                    clientSearchResults.length > 0 &&
                                    (showClientDropdown = true)
                                "
                                @keydown.escape="showClientDropdown = false"
                            />
                            <Loader2
                                v-if="isSearchingClients"
                                class="absolute top-2.5 right-3 size-4 animate-spin text-muted-foreground"
                            />
                        </div>

                        <!-- Dropdown de resultados de búsqueda rápida de clientes -->
                        <div
                            v-if="
                                showClientDropdown &&
                                clientSearchResults.length > 0
                            "
                            class="absolute z-30 mt-1 max-h-56 w-full overflow-auto rounded-md border border-border bg-popover p-1 text-popover-foreground shadow-lg"
                        >
                            <button
                                v-for="c in clientSearchResults"
                                :key="c.id"
                                type="button"
                                class="flex w-full cursor-pointer items-center justify-between rounded px-3 py-2 text-left text-sm transition-colors hover:bg-accent hover:text-accent-foreground"
                                @click="selectClient(c)"
                            >
                                <div class="min-w-0 pr-2">
                                    <div class="truncate font-medium">
                                        {{ c.name }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{
                                            c.id_card
                                                ? `CI: ${c.id_card}`
                                                : 'Sin CI'
                                        }}
                                        {{ c.phone ? `· Tel: ${c.phone}` : '' }}
                                    </div>
                                </div>
                                <span
                                    v-if="c.sales_count > 0"
                                    class="shrink-0 rounded bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400"
                                >
                                    {{ c.sales_count }}
                                    {{
                                        c.sales_count === 1
                                            ? 'compra'
                                            : 'compras'
                                    }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- Ficha activa seleccionada -->
                    <div
                        v-if="foundClient"
                        class="flex items-center justify-between rounded-lg border border-emerald-200 bg-emerald-50/70 p-2.5 text-xs text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-300"
                    >
                        <div class="flex items-center gap-2">
                            <UserCheck
                                class="size-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                            />
                            <span>
                                <strong>{{ foundClient.name }}</strong>
                                <template v-if="foundClient.id_card">
                                    · CI: {{ foundClient.id_card }}</template
                                >
                                <template v-if="foundClient.phone">
                                    · Tel: {{ foundClient.phone }}</template
                                >
                                <template v-if="foundClient.sales_count">
                                    · {{ foundClient.sales_count }}
                                    {{
                                        foundClient.sales_count === 1
                                            ? 'compra registrada'
                                            : 'compras registradas'
                                    }}
                                </template>
                            </span>
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            class="size-6 text-emerald-700 hover:text-emerald-900 dark:text-emerald-400"
                            title="Cambiar cliente"
                            @click="clearSelectedClient"
                        >
                            <X class="size-3.5" />
                        </Button>
                    </div>

                    <!-- Campos del cliente -->
                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <div class="space-y-1.5">
                            <Label for="id_card">CI</Label>
                            <div class="relative">
                                <Input
                                    id="id_card"
                                    v-model="form.id_card"
                                    placeholder="Carnet de identidad"
                                    autocomplete="off"
                                    :aria-invalid="!!form.errors.id_card"
                                    @blur="onCiBlur"
                                />
                                <Loader2
                                    v-if="ciLookup === 'loading'"
                                    class="absolute top-2.5 right-3 size-4 animate-spin text-muted-foreground"
                                />
                            </div>
                            <InputError :message="form.errors.id_card" />
                            <p
                                v-if="ciLookup === 'found' && foundClient"
                                class="flex items-center gap-1 text-xs text-emerald-600 dark:text-emerald-400"
                            >
                                <UserCheck class="size-3.5" />
                                Cliente registrado. Datos completados.
                            </p>
                            <p
                                v-else-if="ciLookup === 'new'"
                                class="flex items-center gap-1 text-xs text-muted-foreground"
                            >
                                <UserPlus class="size-3.5" />
                                CI nuevo: se registrará el cliente con la venta.
                            </p>
                            <p v-else class="text-xs text-muted-foreground">
                                Si ya compró antes, se completan sus datos.
                            </p>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="customer_name">Cliente</Label>
                            <Input
                                id="customer_name"
                                v-model="form.customer_name"
                                placeholder="Consumidor final"
                                :aria-invalid="!!form.errors.customer_name"
                            />
                            <InputError :message="form.errors.customer_name" />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="customer_phone">
                                Teléfono
                                <span class="text-muted-foreground"
                                    >(opcional)</span
                                >
                            </Label>
                            <Input
                                id="customer_phone"
                                v-model="form.customer_phone"
                                placeholder="Ej. 70000000"
                                :aria-invalid="!!form.errors.customer_phone"
                            />
                            <InputError :message="form.errors.customer_phone" />
                        </div>

                        <div class="space-y-1.5">
                            <Label for="payment_method">Método de pago</Label>
                            <select
                                id="payment_method"
                                v-model="form.payment_method"
                                class="h-9 w-full rounded-md border border-input bg-card px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                <option
                                    v-for="m in paymentMethods"
                                    :key="m"
                                    :value="m"
                                >
                                    {{ PAYMENT_METHOD_LABELS[m] ?? m }}
                                </option>
                            </select>
                            <InputError :message="form.errors.payment_method" />
                        </div>

                        <div class="space-y-1.5 md:col-span-2">
                            <Label for="notes">Notas</Label>
                            <textarea
                                id="notes"
                                v-model="form.notes"
                                rows="1"
                                placeholder="Observaciones de la venta"
                                class="min-h-9 w-full rounded-md border border-input bg-card px-3 py-1.5 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            ></textarea>
                            <InputError :message="form.errors.notes" />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Sección de productos y modo lector de código de barras -->
            <Card class="gap-3 py-4">
                <CardHeader
                    class="flex flex-row items-center justify-between space-y-0 pb-0"
                >
                    <CardTitle class="flex items-center gap-2">
                        <ScanBarcode class="size-5 text-primary" />
                        <span>Productos</span>
                    </CardTitle>
                    <div class="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            :class="[
                                'text-xs transition-colors',
                                scannerMode
                                    ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                                    : '',
                            ]"
                            @click="toggleScannerMode"
                        >
                            <ScanBarcode class="mr-1.5 size-4" />
                            {{ scannerMode ? 'Lector activo' : 'Modo Lector' }}
                            <span
                                v-if="scannerMode"
                                class="ml-1.5 inline-block size-2 animate-pulse rounded-full bg-emerald-500"
                            />
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            :title="
                                soundEnabled
                                    ? 'Silenciar lector'
                                    : 'Activar sonido del lector'
                            "
                            @click="soundEnabled = !soundEnabled"
                        >
                            <Volume2
                                v-if="soundEnabled"
                                class="size-4 text-muted-foreground"
                            />
                            <VolumeX
                                v-else
                                class="size-4 text-muted-foreground"
                            />
                        </Button>
                    </div>
                </CardHeader>
                <CardContent class="space-y-3">
                    <!-- Barra de escaneo rápido por código de barras / SKU -->
                    <div
                        :class="[
                            'space-y-2 rounded-lg border p-3 transition-all',
                            scannerMode
                                ? 'border-emerald-300 bg-emerald-50/40 dark:border-emerald-800 dark:bg-emerald-950/20'
                                : 'border-border bg-muted/40',
                        ]"
                    >
                        <div
                            class="flex flex-col items-stretch gap-2 sm:flex-row sm:items-center"
                        >
                            <div class="relative flex-1">
                                <Barcode
                                    class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                                />
                                <Input
                                    ref="barcodeInputRef"
                                    v-model="barcodeQuery"
                                    type="text"
                                    placeholder="Escanear código de barras o escribir SKU y presionar Enter..."
                                    class="pl-9 font-mono text-sm"
                                    autocomplete="off"
                                    @keydown.enter.prevent="handleBarcodeScan"
                                />
                            </div>
                            <Button
                                type="button"
                                size="sm"
                                variant="secondary"
                                class="shrink-0"
                                @click="handleBarcodeScan"
                            >
                                <Plus class="mr-1 size-4" />
                                Agregar por código
                            </Button>
                        </div>

                        <!-- Feedback temporal del escaneo -->
                        <div
                            v-if="scanFeedback"
                            :class="[
                                'flex items-center justify-between rounded-md px-3 py-1.5 text-xs transition-all',
                                scanFeedback.type === 'success'
                                    ? 'border border-emerald-200 bg-emerald-100 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                    : 'border border-red-200 bg-red-100 text-red-800 dark:border-red-800 dark:bg-red-950/60 dark:text-red-300',
                            ]"
                        >
                            <div class="flex items-center gap-1.5">
                                <CheckCircle2
                                    v-if="scanFeedback.type === 'success'"
                                    class="size-3.5 shrink-0"
                                />
                                <AlertCircle v-else class="size-3.5 shrink-0" />
                                <span>{{ scanFeedback.message }}</span>
                            </div>
                            <button
                                type="button"
                                class="cursor-pointer text-xs opacity-70 hover:opacity-100"
                                @click="scanFeedback = null"
                            >
                                <X class="size-3.5" />
                            </button>
                        </div>
                    </div>

                    <InputError :message="form.errors.items" />

                    <!-- Encabezados -->
                    <div
                        class="hidden gap-2 px-1 text-[11px] font-medium tracking-wide text-muted-foreground uppercase sm:grid sm:grid-cols-[minmax(0,1fr)_100px_120px_110px_36px]"
                    >
                        <span>Producto</span>
                        <span>Cantidad</span>
                        <span>Precio unit.</span>
                        <span class="text-right">Subtotal</span>
                        <span></span>
                    </div>

                    <!-- Filas de productos -->
                    <div
                        v-for="(item, i) in form.items"
                        :key="i"
                        class="grid items-start gap-2 sm:grid-cols-[minmax(0,1fr)_100px_120px_110px_36px]"
                    >
                        <div>
                            <span
                                class="mb-1 block text-[11px] font-medium tracking-wide text-muted-foreground uppercase sm:hidden"
                            >
                                Producto
                            </span>
                            <ProductCombobox
                                v-model="item.product_id"
                                :products="products"
                                :aria-invalid="
                                    !!form.errors[`items.${i}.product_id`]
                                "
                                @select="(p) => onProductPick(i, p)"
                            />
                            <InputError
                                :message="form.errors[`items.${i}.product_id`]"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <span
                                class="mb-1 block text-[11px] font-medium tracking-wide text-muted-foreground uppercase sm:hidden"
                            >
                                Cantidad
                            </span>
                            <Input
                                v-model.number="item.quantity"
                                type="number"
                                min="1"
                                placeholder="Cant."
                                :aria-invalid="
                                    !!form.errors[`items.${i}.quantity`] ||
                                    exceedsStock(i)
                                "
                            />
                            <InputError
                                :message="form.errors[`items.${i}.quantity`]"
                                class="mt-1"
                            />
                            <p
                                v-if="exceedsStock(i)"
                                class="mt-1 text-xs text-amber-600 dark:text-amber-400"
                            >
                                Hay {{ stockOf(i) }} en stock: quedará negativo.
                            </p>
                        </div>
                        <div>
                            <span
                                class="mb-1 block text-[11px] font-medium tracking-wide text-muted-foreground uppercase sm:hidden"
                            >
                                Precio unit.
                            </span>
                            <Input
                                v-model.number="item.unit_price"
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="Precio unit."
                                :aria-invalid="
                                    !!form.errors[`items.${i}.unit_price`]
                                "
                            />
                            <InputError
                                :message="form.errors[`items.${i}.unit_price`]"
                                class="mt-1"
                            />
                        </div>
                        <div
                            class="flex h-9 items-center justify-end font-mono text-sm text-muted-foreground"
                        >
                            {{ cur(subtotalOf(i)) }}
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            :disabled="form.items.length === 1"
                            @click="removeItem(i)"
                        >
                            <Trash2 class="size-4 text-destructive" />
                        </Button>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="addItem"
                    >
                        <Plus class="size-4" /> Agregar producto
                    </Button>

                    <!-- El total se muestra en vivo como ayuda -->
                    <div
                        class="flex flex-col items-end gap-2 border-t border-border pt-3"
                    >
                        <div
                            class="flex w-full max-w-xs items-center justify-between gap-3 text-sm text-muted-foreground"
                        >
                            <span>Subtotal</span>
                            <span class="font-mono">{{ cur(subtotal) }}</span>
                        </div>
                        <div
                            class="flex w-full max-w-xs items-center justify-between gap-3 text-sm"
                        >
                            <Label for="discount_amount" class="font-normal">
                                Descuento
                            </Label>
                            <Input
                                id="discount_amount"
                                v-model.number="form.discount_amount"
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="0.00"
                                class="h-8 w-32 text-right"
                                :aria-invalid="
                                    !!form.errors.discount_amount ||
                                    discountTooBig
                                "
                            />
                        </div>
                        <InputError :message="form.errors.discount_amount" />
                        <p
                            v-if="discountTooBig"
                            class="text-xs text-destructive"
                        >
                            El descuento no puede superar el subtotal.
                        </p>
                        <div
                            class="flex w-full max-w-xs items-center justify-between gap-3 text-base font-semibold"
                        >
                            <span>Total</span>
                            <span class="font-mono">{{ cur(total) }}</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div class="flex justify-end gap-2">
                <Button variant="outline" as-child>
                    <Link href="/admin/sales">Cancelar</Link>
                </Button>
                <Button
                    type="submit"
                    :disabled="form.processing || discountTooBig"
                >
                    Registrar venta
                </Button>
            </div>
        </form>
    </div>
</template>
