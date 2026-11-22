<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowRight,
    Calendar,
    CheckCircle2,
    Clock,
    CreditCard,
    Download,
    Eye,
    FileText,
    Heart,
    IdCard,
    MessageCircle,
    Package,
    QrCode,
    Receipt,
    RefreshCw,
    ShoppingBag,
    ShoppingCart,
    Trash2,
    UploadCloud,
    User,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useCart } from '@/composables/useCart';
import { useWishlist } from '@/composables/useWishlist';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { formatPrice } from '@/lib/catalog';
import type { StoreSettings } from '@/lib/catalog';

defineOptions({ layout: PublicLayout });

type InquiryItem = {
    id: number;
    name: string;
    code: string | null;
    quantity: number;
    unit_price: number;
    subtotal: number;
};

type Inquiry = {
    id: number;
    public_token: string;
    status: string;
    payment_status?: string;
    payment_receipt_url?: string | null;
    payment_notes?: string | null;
    source: string;
    total_estimated: number;
    discount_amount: number;
    coupon_code: string | null;
    created_at: string;
    items: InquiryItem[];
};

type SaleItem = {
    id: number;
    name: string;
    code: string | null;
    quantity: number;
    unit_price: number;
};

type Sale = {
    id: number;
    receipt_number: string;
    status: string;
    payment_method: string | null;
    subtotal: number;
    discount_amount: number;
    total: number;
    created_at: string;
    items_count: number;
    receipt_url: string;
    items: SaleItem[];
};

type WishlistProduct = {
    product_id: number;
    slug: string;
    name: string;
    code: string;
    price: number;
    stock: number;
    image: string | null;
};

const props = defineProps<{
    client: {
        name: string;
        id_card: string | null;
        phone: string | null;
        email: string | null;
        address: string | null;
    };
    inquiries: Inquiry[];
    sales: Sale[];
    wishlist: WishlistProduct[];
    storePhone: string;
}>();

const activeTab = ref<'orders' | 'purchases' | 'wishlist' | 'profile'>(
    'orders',
);

const { add: addToCart, drawerOpen } = useCart();
const { remove: removeFromWishlist } = useWishlist();

const formatMoney = (amount: number) => formatPrice(amount, 'Bs');

const getStatusBadge = (status: string) => {
    switch (status) {
        case 'pendiente':
            return {
                label: 'Pendiente de atención',
                variant: 'outline' as const,
                class: 'border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-400',
                icon: Clock,
            };
        case 'contactado':
            return {
                label: 'En preparación / Contactado',
                variant: 'outline' as const,
                class: 'border-sky-500/40 bg-sky-500/10 text-sky-700 dark:text-sky-400',
                icon: RefreshCw,
            };
        case 'vendido':
            return {
                label: 'Confirmado / Concretado',
                variant: 'outline' as const,
                class: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
                icon: CheckCircle2,
            };
        case 'cerrado':
        default:
            return {
                label: 'Cerrado',
                variant: 'outline' as const,
                class: 'border-zinc-500/40 bg-zinc-500/10 text-zinc-600 dark:text-zinc-400',
                icon: XCircle,
            };
    }
};

const getStatusStep = (status: string): number => {
    switch (status) {
        case 'pendiente':
            return 1;
        case 'contactado':
            return 2;
        case 'vendido':
            return 3;
        case 'cerrado':
            return 0;
        default:
            return 1;
    }
};

const getWhatsAppUrl = (inquiry: Inquiry) => {
    const phone = props.storePhone.replace(/[^0-9]/g, '');
    const text = encodeURIComponent(
        `Hola, me comunico sobre mi pedido #${inquiry.id} de la tienda web. ¿Podrían informarme el estado?`,
    );

    return `https://wa.me/${phone}?text=${text}`;
};

const handleAddToCartFromWishlist = (product: WishlistProduct) => {
    addToCart({
        product_id: product.product_id,
        slug: product.slug,
        name: product.name,
        code: product.code,
        price: product.price,
        image: product.image,
    });
    drawerOpen.value = true;
};

const page = usePage();
const store = computed(() => page.props.store as StoreSettings);

const getPaymentStatusBadge = (status?: string) => {
    switch (status) {
        case 'comprobante_subido':
            return {
                label: 'Comprobante en revisión',
                class: 'border-blue-500/40 bg-blue-500/10 text-blue-700 dark:text-blue-400',
                icon: Clock,
            };
        case 'verificado':
            return {
                label: 'Pago verificado',
                class: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
                icon: CheckCircle2,
            };
        case 'rechazado':
            return {
                label: 'Comprobante observado',
                class: 'border-destructive/40 bg-destructive/10 text-destructive',
                icon: AlertCircle,
            };
        case 'sin_pago':
        default:
            return {
                label: 'Pendiente de pago',
                class: 'border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-400',
                icon: Clock,
            };
    }
};

const isUploadModalOpen = ref(false);
const selectedInquiry = ref<Inquiry | null>(null);
const modalFileInputRef = ref<HTMLInputElement | null>(null);
const uploadFileName = ref<string | null>(null);

const uploadForm = useForm({
    receipt: null as File | null,
});

const openUploadModal = (inquiry: Inquiry) => {
    selectedInquiry.value = inquiry;
    uploadForm.reset();
    uploadFileName.value = null;
    isUploadModalOpen.value = true;
};

const onModalFileSelect = (e: Event) => {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0];

    if (file) {
        uploadForm.receipt = file;
        uploadFileName.value = file.name;
    } else {
        uploadForm.receipt = null;
        uploadFileName.value = null;
    }
};

const submitReceiptUpload = () => {
    if (!selectedInquiry.value || !uploadForm.receipt) {
        return;
    }

    uploadForm.post(`/mis-pedidos/${selectedInquiry.value.id}/comprobante`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            isUploadModalOpen.value = false;
            uploadForm.reset();
            uploadFileName.value = null;

            if (modalFileInputRef.value) {
                modalFileInputRef.value.value = '';
            }
        },
    });
};
</script>

<template>
    <div class="min-h-screen bg-muted/20 py-8">
        <Head title="Mi Cuenta y Pedidos" />

        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <!-- Encabezado de bienvenida -->
            <div
                class="mb-8 flex flex-col justify-between gap-4 rounded-3xl border border-border/80 bg-card p-6 shadow-sm sm:flex-row sm:items-center"
            >
                <div class="flex items-center gap-4">
                    <div
                        class="flex size-14 items-center justify-center rounded-2xl bg-primary/10 text-primary"
                    >
                        <User class="size-7" />
                    </div>
                    <div>
                        <h1
                            class="font-display text-2xl font-bold text-foreground"
                        >
                            Hola, {{ client.name }}
                        </h1>
                        <p class="text-xs text-muted-foreground sm:text-sm">
                            Consulta el estado de tus pedidos, descarga tus
                            comprobantes y administra tus favoritos.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        as-child
                        class="rounded-xl text-xs"
                    >
                        <Link href="/completar-datos">
                            <IdCard class="mr-1.5 size-3.5" />
                            Editar Mis Datos
                        </Link>
                    </Button>
                </div>
            </div>

            <!-- Navegación por pestañas -->
            <div
                class="mb-6 flex scrollbar-none gap-2 overflow-x-auto border-b border-border/70"
            >
                <button
                    type="button"
                    @click="activeTab = 'orders'"
                    :class="[
                        'inline-flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-semibold whitespace-nowrap transition-colors',
                        activeTab === 'orders'
                            ? 'border-primary text-primary'
                            : 'border-transparent text-muted-foreground hover:border-border hover:text-foreground',
                    ]"
                >
                    <Package class="size-4" />
                    Cotizaciones y Pedidos
                    <span
                        v-if="inquiries.length > 0"
                        class="rounded-full bg-primary/10 px-2 py-0.5 text-xs font-bold text-primary"
                    >
                        {{ inquiries.length }}
                    </span>
                </button>

                <button
                    type="button"
                    @click="activeTab = 'purchases'"
                    :class="[
                        'inline-flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-semibold whitespace-nowrap transition-colors',
                        activeTab === 'purchases'
                            ? 'border-primary text-primary'
                            : 'border-transparent text-muted-foreground hover:border-border hover:text-foreground',
                    ]"
                >
                    <Receipt class="size-4" />
                    Historial de Compras (PDF)
                    <span
                        v-if="sales.length > 0"
                        class="rounded-full bg-muted px-2 py-0.5 text-xs font-bold text-muted-foreground"
                    >
                        {{ sales.length }}
                    </span>
                </button>

                <button
                    type="button"
                    @click="activeTab = 'wishlist'"
                    :class="[
                        'inline-flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-semibold whitespace-nowrap transition-colors',
                        activeTab === 'wishlist'
                            ? 'border-primary text-primary'
                            : 'border-transparent text-muted-foreground hover:border-border hover:text-foreground',
                    ]"
                >
                    <Heart class="size-4" />
                    Mis Favoritos
                    <span
                        v-if="wishlist.length > 0"
                        class="rounded-full bg-muted px-2 py-0.5 text-xs font-bold text-muted-foreground"
                    >
                        {{ wishlist.length }}
                    </span>
                </button>

                <button
                    type="button"
                    @click="activeTab = 'profile'"
                    :class="[
                        'inline-flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-semibold whitespace-nowrap transition-colors',
                        activeTab === 'profile'
                            ? 'border-primary text-primary'
                            : 'border-transparent text-muted-foreground hover:border-border hover:text-foreground',
                    ]"
                >
                    <User class="size-4" />
                    Datos de Contacto
                </button>
            </div>

            <!-- CONTENIDO DE PESTAÑAS -->

            <!-- Pestaña 1: Cotizaciones y Pedidos Web -->
            <div v-if="activeTab === 'orders'" class="space-y-6">
                <div
                    v-if="inquiries.length === 0"
                    class="rounded-3xl border border-dashed border-border bg-card p-12 text-center"
                >
                    <div
                        class="mx-auto mb-4 flex size-16 items-center justify-center rounded-2xl bg-muted text-muted-foreground"
                    >
                        <ShoppingBag class="size-8" />
                    </div>
                    <h3 class="font-display text-lg font-bold text-foreground">
                        Aún no tienes pedidos registrados
                    </h3>
                    <p
                        class="mx-auto mt-1 max-w-sm text-sm text-muted-foreground"
                    >
                        Explora nuestro catálogo de productos, añade los
                        artículos que desees al carrito y cotiza con atención
                        inmediata.
                    </p>
                    <Button as-child class="mt-6 rounded-xl">
                        <Link href="/catalogo">
                            Ver Catálogo
                            <ArrowRight class="ml-1.5 size-4" />
                        </Link>
                    </Button>
                </div>

                <div v-else class="space-y-5">
                    <div
                        v-for="inquiry in inquiries"
                        :key="inquiry.id"
                        class="overflow-hidden rounded-3xl border border-border/80 bg-card shadow-sm transition hover:border-primary/40"
                    >
                        <!-- Cabecera del pedido -->
                        <div
                            class="border-b border-border/70 bg-muted/30 p-5 sm:flex sm:items-center sm:justify-between"
                        >
                            <div class="flex flex-wrap items-center gap-3">
                                <span
                                    class="font-display text-base font-bold text-foreground"
                                >
                                    Pedido #{{ inquiry.id }}
                                </span>
                                <Badge
                                    :variant="
                                        getStatusBadge(inquiry.status).variant
                                    "
                                    :class="
                                        getStatusBadge(inquiry.status).class
                                    "
                                >
                                    <component
                                        :is="
                                            getStatusBadge(inquiry.status).icon
                                        "
                                        class="mr-1 size-3"
                                    />
                                    {{ getStatusBadge(inquiry.status).label }}
                                </Badge>
                                <Badge
                                    variant="outline"
                                    :class="
                                        getPaymentStatusBadge(
                                            inquiry.payment_status,
                                        ).class
                                    "
                                >
                                    <component
                                        :is="
                                            getPaymentStatusBadge(
                                                inquiry.payment_status,
                                            ).icon
                                        "
                                        class="mr-1 size-3"
                                    />
                                    {{
                                        getPaymentStatusBadge(
                                            inquiry.payment_status,
                                        ).label
                                    }}
                                </Badge>
                                <span class="text-xs text-muted-foreground">
                                    <Calendar
                                        class="mr-1 inline-block size-3.5 text-muted-foreground/70"
                                    />
                                    {{ inquiry.created_at }}
                                </span>
                            </div>

                            <div
                                class="mt-3 flex items-center justify-between sm:mt-0 sm:justify-end sm:gap-4"
                            >
                                <div class="text-right">
                                    <span class="text-xs text-muted-foreground"
                                        >Total Estimado</span
                                    >
                                    <div
                                        class="font-display text-lg font-bold text-foreground"
                                    >
                                        {{
                                            formatMoney(inquiry.total_estimated)
                                        }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Stepper visual de estado -->
                        <div
                            v-if="inquiry.status !== 'cerrado'"
                            class="border-b border-border/60 bg-muted/10 px-6 py-4"
                        >
                            <div class="grid grid-cols-3 gap-2 text-center">
                                <div class="flex flex-col items-center">
                                    <div
                                        :class="[
                                            'flex size-7 items-center justify-center rounded-full text-xs font-bold transition',
                                            getStatusStep(inquiry.status) >= 1
                                                ? 'bg-primary text-primary-foreground'
                                                : 'bg-muted text-muted-foreground',
                                        ]"
                                    >
                                        1
                                    </div>
                                    <span
                                        class="mt-1 text-[11px] font-medium text-foreground"
                                        >Recibido</span
                                    >
                                </div>

                                <div class="flex flex-col items-center">
                                    <div
                                        :class="[
                                            'flex size-7 items-center justify-center rounded-full text-xs font-bold transition',
                                            getStatusStep(inquiry.status) >= 2
                                                ? 'bg-primary text-primary-foreground'
                                                : 'bg-muted text-muted-foreground',
                                        ]"
                                    >
                                        2
                                    </div>
                                    <span
                                        class="mt-1 text-[11px] font-medium text-foreground"
                                        >En preparación</span
                                    >
                                </div>

                                <div class="flex flex-col items-center">
                                    <div
                                        :class="[
                                            'flex size-7 items-center justify-center rounded-full text-xs font-bold transition',
                                            getStatusStep(inquiry.status) >= 3
                                                ? 'bg-emerald-600 text-white'
                                                : 'bg-muted text-muted-foreground',
                                        ]"
                                    >
                                        3
                                    </div>
                                    <span
                                        class="mt-1 text-[11px] font-medium text-foreground"
                                        >Confirmado / Entregado</span
                                    >
                                </div>
                            </div>
                        </div>

                        <!-- Aviso si el comprobante fue observado/rechazado -->
                        <div
                            v-if="inquiry.payment_status === 'rechazado'"
                            class="border-b border-destructive/20 bg-destructive/10 px-6 py-3 text-xs text-destructive"
                        >
                            <div
                                class="flex items-center gap-1.5 font-semibold"
                            >
                                <AlertCircle class="size-4" /> Comprobante de
                                pago observado
                            </div>
                            <p v-if="inquiry.payment_notes" class="mt-1">
                                Motivo: {{ inquiry.payment_notes }}
                            </p>
                            <p class="mt-1 text-[11px] text-destructive/80">
                                Por favor, sube un nuevo comprobante legible con
                                el botón inferior.
                            </p>
                        </div>

                        <!-- Lista de productos -->
                        <div class="divide-y divide-border/60 p-5">
                            <div
                                v-for="item in inquiry.items"
                                :key="item.id"
                                class="flex items-center justify-between py-2 text-sm first:pt-0 last:pb-0"
                            >
                                <div class="pr-4">
                                    <p class="font-medium text-foreground">
                                        {{ item.name }}
                                    </p>
                                    <span
                                        v-if="item.code"
                                        class="text-xs text-muted-foreground"
                                        >SKU: {{ item.code }}</span
                                    >
                                </div>
                                <div class="text-right whitespace-nowrap">
                                    <span class="text-xs text-muted-foreground"
                                        >{{ item.quantity }} ×
                                        {{ formatMoney(item.unit_price) }}</span
                                    >
                                    <p class="font-semibold text-foreground">
                                        {{ formatMoney(item.subtotal) }}
                                    </p>
                                </div>
                            </div>

                            <div
                                v-if="inquiry.discount_amount > 0"
                                class="flex justify-between pt-3 text-sm text-emerald-600 dark:text-emerald-400"
                            >
                                <span
                                    >Descuento aplicado (Cupón:
                                    {{ inquiry.coupon_code }})</span
                                >
                                <span
                                    >-
                                    {{
                                        formatMoney(inquiry.discount_amount)
                                    }}</span
                                >
                            </div>
                        </div>

                        <!-- Pie de acción: Comprobante y WhatsApp -->
                        <div
                            class="flex flex-col items-start justify-between gap-3 border-t border-border/70 bg-muted/20 px-5 py-3 sm:flex-row sm:items-center"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <Button
                                    v-if="inquiry.payment_receipt_url"
                                    variant="outline"
                                    size="sm"
                                    as-child
                                    class="rounded-xl border-border"
                                >
                                    <a
                                        :href="inquiry.payment_receipt_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <Eye class="mr-1.5 size-3.5" />
                                        Ver Comprobante
                                    </a>
                                </Button>

                                <Button
                                    v-if="
                                        inquiry.payment_status !== 'verificado'
                                    "
                                    variant="default"
                                    size="sm"
                                    class="rounded-xl"
                                    @click="openUploadModal(inquiry)"
                                >
                                    <UploadCloud class="mr-1.5 size-3.5" />
                                    {{
                                        inquiry.payment_status ===
                                        'comprobante_subido'
                                            ? 'Cambiar Comprobante'
                                            : 'Subir Comprobante QR'
                                    }}
                                </Button>
                            </div>

                            <Button
                                v-if="storePhone"
                                variant="outline"
                                size="sm"
                                as-child
                                class="rounded-xl border-emerald-600/30 text-emerald-700 hover:bg-emerald-500/10 dark:text-emerald-400"
                            >
                                <a
                                    :href="getWhatsAppUrl(inquiry)"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <MessageCircle class="mr-1.5 size-3.5" />
                                    Consultar por WhatsApp
                                </a>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pestaña 2: Historial de Compras (Ventas) -->
            <div v-if="activeTab === 'purchases'" class="space-y-6">
                <div
                    v-if="sales.length === 0"
                    class="rounded-3xl border border-dashed border-border bg-card p-12 text-center"
                >
                    <div
                        class="mx-auto mb-4 flex size-16 items-center justify-center rounded-2xl bg-muted text-muted-foreground"
                    >
                        <Receipt class="size-8" />
                    </div>
                    <h3 class="font-display text-lg font-bold text-foreground">
                        No tienes comprobantes de compra emitidos
                    </h3>
                    <p
                        class="mx-auto mt-1 max-w-md text-sm text-muted-foreground"
                    >
                        Cuando realices compras en nuestro mostrador o se
                        concrete un pedido web con tu C.I. o teléfono, aquí
                        podrás descargar tus comprobantes en PDF.
                    </p>
                </div>

                <div v-else class="space-y-4">
                    <div
                        v-for="sale in sales"
                        :key="sale.id"
                        class="overflow-hidden rounded-3xl border border-border/80 bg-card p-6 shadow-sm transition hover:border-primary/40"
                    >
                        <div
                            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
                        >
                            <div>
                                <div class="flex items-center gap-3">
                                    <h3
                                        class="font-display text-lg font-bold text-foreground"
                                    >
                                        Comprobante N° {{ sale.receipt_number }}
                                    </h3>
                                    <Badge
                                        :variant="
                                            sale.status === 'confirmada'
                                                ? 'outline'
                                                : 'destructive'
                                        "
                                        :class="
                                            sale.status === 'confirmada'
                                                ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                                : ''
                                        "
                                    >
                                        {{
                                            sale.status === 'confirmada'
                                                ? 'Confirmada'
                                                : 'Anulada'
                                        }}
                                    </Badge>
                                </div>
                                <div
                                    class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground"
                                >
                                    <span
                                        ><Calendar
                                            class="mr-1 inline-block size-3.5"
                                        />{{ sale.created_at }}</span
                                    >
                                    <span
                                        ><CreditCard
                                            class="mr-1 inline-block size-3.5"
                                        />{{
                                            sale.payment_method
                                                ? sale.payment_method.toUpperCase()
                                                : 'EFECTIVO'
                                        }}</span
                                    >
                                    <span
                                        >{{
                                            sale.items_count
                                        }}
                                        artículo(s)</span
                                    >
                                </div>
                            </div>

                            <div
                                class="flex items-center justify-between gap-4 sm:justify-end"
                            >
                                <div class="text-right">
                                    <span class="text-xs text-muted-foreground"
                                        >Total Pagado</span
                                    >
                                    <div
                                        class="font-display text-xl font-bold text-foreground"
                                    >
                                        {{ formatMoney(sale.total) }}
                                    </div>
                                </div>

                                <Button size="sm" as-child class="rounded-xl">
                                    <a :href="sale.receipt_url" target="_blank">
                                        <Download class="mr-1.5 size-4" />
                                        Descargar PDF
                                    </a>
                                </Button>
                            </div>
                        </div>

                        <!-- Artículos del recibo -->
                        <div class="mt-4 border-t border-border/60 pt-3">
                            <p
                                class="mb-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                Productos incluidos
                            </p>
                            <ul class="space-y-1 text-xs text-foreground">
                                <li
                                    v-for="item in sale.items"
                                    :key="item.id"
                                    class="flex justify-between"
                                >
                                    <span
                                        >{{ item.quantity }} ×
                                        {{ item.name }}</span
                                    >
                                    <span class="font-medium">{{
                                        formatMoney(
                                            item.quantity * item.unit_price,
                                        )
                                    }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pestaña 3: Mis Favoritos Sincronizados -->
            <div v-if="activeTab === 'wishlist'" class="space-y-6">
                <div
                    v-if="wishlist.length === 0"
                    class="rounded-3xl border border-dashed border-border bg-card p-12 text-center"
                >
                    <div
                        class="mx-auto mb-4 flex size-16 items-center justify-center rounded-2xl bg-muted text-muted-foreground"
                    >
                        <Heart class="size-8" />
                    </div>
                    <h3 class="font-display text-lg font-bold text-foreground">
                        Tu lista de favoritos está vacía
                    </h3>
                    <p
                        class="mx-auto mt-1 max-w-sm text-sm text-muted-foreground"
                    >
                        Guarda los productos que te gusten haciendo clic en el
                        corazón para encontrarlos rápidamente desde cualquier
                        dispositivo.
                    </p>
                    <Button as-child class="mt-6 rounded-xl">
                        <Link href="/catalogo"> Explorar Catálogo </Link>
                    </Button>
                </div>

                <div
                    v-else
                    class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4"
                >
                    <div
                        v-for="item in wishlist"
                        :key="item.product_id"
                        class="flex flex-col justify-between overflow-hidden rounded-2xl border border-border/80 bg-card p-4 shadow-sm transition hover:border-primary/40"
                    >
                        <div>
                            <div
                                class="relative mb-3 aspect-square overflow-hidden rounded-xl bg-muted/40"
                            >
                                <img
                                    v-if="item.image"
                                    :src="item.image"
                                    :alt="item.name"
                                    class="size-full object-cover"
                                    loading="lazy"
                                />
                                <div
                                    v-else
                                    class="flex size-full items-center justify-center text-muted-foreground"
                                >
                                    <Package class="size-10" />
                                </div>

                                <button
                                    type="button"
                                    @click="removeFromWishlist(item.product_id)"
                                    class="absolute top-2 right-2 rounded-full bg-background/80 p-1.5 text-muted-foreground backdrop-blur-sm transition hover:bg-destructive/10 hover:text-destructive"
                                    title="Quitar de favoritos"
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </div>

                            <Link
                                :href="`/catalogo/${item.slug}`"
                                class="block"
                            >
                                <span
                                    v-if="item.code"
                                    class="font-mono text-[11px] text-muted-foreground"
                                >
                                    {{ item.code }}
                                </span>
                                <h4
                                    class="line-clamp-2 font-semibold text-foreground hover:text-primary"
                                >
                                    {{ item.name }}
                                </h4>
                            </Link>
                        </div>

                        <div class="mt-4 border-t border-border/60 pt-3">
                            <div class="mb-3 flex items-center justify-between">
                                <span
                                    class="font-display text-base font-bold text-foreground"
                                >
                                    {{ formatMoney(item.price) }}
                                </span>
                                <Badge
                                    :variant="
                                        item.stock > 0 ? 'outline' : 'secondary'
                                    "
                                    class="text-[10px]"
                                >
                                    {{
                                        item.stock > 0 ? 'En stock' : 'Agotado'
                                    }}
                                </Badge>
                            </div>

                            <Button
                                size="sm"
                                class="w-full rounded-xl"
                                :disabled="item.stock <= 0"
                                @click="handleAddToCartFromWishlist(item)"
                            >
                                <ShoppingCart class="mr-1.5 size-4" />
                                Agregar al Carrito
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pestaña 4: Datos de Contacto -->
            <div v-if="activeTab === 'profile'" class="max-w-2xl">
                <Card class="rounded-3xl border-border/80 shadow-sm">
                    <CardHeader>
                        <CardTitle class="font-display text-xl"
                            >Datos de Facturación y Contacto</CardTitle
                        >
                        <CardDescription>
                            Estos datos se utilizan para asociar automáticamente
                            tus compras en mostrador y pedidos web a tu cuenta.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div
                                class="rounded-2xl border border-border/60 bg-muted/20 p-4"
                            >
                                <span class="text-xs text-muted-foreground"
                                    >Nombre Completo</span
                                >
                                <p class="font-semibold text-foreground">
                                    {{ client.name }}
                                </p>
                            </div>

                            <div
                                class="rounded-2xl border border-border/60 bg-muted/20 p-4"
                            >
                                <span class="text-xs text-muted-foreground"
                                    >C.I. / NIT</span
                                >
                                <p class="font-semibold text-foreground">
                                    {{ client.id_card || 'No registrado' }}
                                </p>
                            </div>

                            <div
                                class="rounded-2xl border border-border/60 bg-muted/20 p-4"
                            >
                                <span class="text-xs text-muted-foreground"
                                    >Teléfono / WhatsApp</span
                                >
                                <p class="font-semibold text-foreground">
                                    {{ client.phone || 'No registrado' }}
                                </p>
                            </div>

                            <div
                                class="rounded-2xl border border-border/60 bg-muted/20 p-4"
                            >
                                <span class="text-xs text-muted-foreground"
                                    >Correo Electrónico</span
                                >
                                <p class="font-semibold text-foreground">
                                    {{ client.email || 'No registrado' }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="client.address"
                            class="rounded-2xl border border-border/60 bg-muted/20 p-4"
                        >
                            <span class="text-xs text-muted-foreground"
                                >Dirección Registrada</span
                            >
                            <p class="font-semibold text-foreground">
                                {{ client.address }}
                            </p>
                        </div>

                        <div class="flex justify-end pt-4">
                            <Button as-child class="rounded-xl">
                                <Link href="/completar-datos">
                                    Actualizar Mis Datos
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Modal de Subida de Comprobante QR -->
        <Dialog v-model:open="isUploadModalOpen">
            <DialogContent class="max-w-md rounded-2xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-lg">
                        <QrCode class="size-5 text-primary" />
                        Comprobante de Pago
                    </DialogTitle>
                    <DialogDescription>
                        Pedido #{{ selectedInquiry?.id }} &middot; Total:
                        {{ formatMoney(selectedInquiry?.total_estimated ?? 0) }}
                    </DialogDescription>
                </DialogHeader>

                <div
                    v-if="store?.payment?.bank_qr_enabled"
                    class="space-y-3 pt-1"
                >
                    <!-- Vista del QR si existe -->
                    <div
                        v-if="store.payment.bank_qr_image_url"
                        class="flex flex-col items-center justify-center rounded-xl border bg-muted/20 p-3"
                    >
                        <img
                            :src="store.payment.bank_qr_image_url"
                            alt="Código QR de Pago"
                            class="size-36 rounded-lg border bg-white object-contain p-2 shadow-xs"
                        />
                        <p
                            class="mt-2 text-center text-[11px] text-muted-foreground"
                        >
                            Escanea con tu aplicación de banca móvil
                        </p>
                    </div>

                    <!-- Datos bancarios resumidos -->
                    <div class="space-y-1.5 rounded-xl bg-muted/40 p-3 text-xs">
                        <div
                            v-if="store.payment.bank_name"
                            class="flex justify-between"
                        >
                            <span class="text-muted-foreground">Banco:</span>
                            <span class="font-semibold text-foreground">
                                {{ store.payment.bank_name }}
                            </span>
                        </div>
                        <div
                            v-if="store.payment.bank_account_number"
                            class="flex justify-between"
                        >
                            <span class="text-muted-foreground"
                                >N° Cuenta:</span
                            >
                            <span class="font-mono font-bold text-foreground">
                                {{ store.payment.bank_account_number }}
                            </span>
                        </div>
                        <div
                            v-if="store.payment.bank_account_holder"
                            class="flex justify-between"
                        >
                            <span class="text-muted-foreground">Titular:</span>
                            <span class="font-medium text-foreground">
                                {{ store.payment.bank_account_holder }}
                            </span>
                        </div>
                        <div class="flex justify-between text-muted-foreground">
                            <span>Concepto / Glosa:</span>
                            <span class="font-mono font-medium text-foreground">
                                Pedido #{{ selectedInquiry?.id }}
                            </span>
                        </div>
                    </div>
                </div>

                <form
                    @submit.prevent="submitReceiptUpload"
                    class="space-y-4 pt-2"
                >
                    <div
                        class="relative flex flex-col items-center justify-center rounded-xl border-2 border-dashed p-4 text-center transition hover:border-primary/50"
                        :class="
                            uploadForm.receipt
                                ? 'border-primary/40 bg-primary/5'
                                : 'border-border bg-muted/20'
                        "
                    >
                        <input
                            ref="modalFileInputRef"
                            type="file"
                            accept="image/jpeg,image/png,image/webp,application/pdf"
                            class="absolute inset-0 cursor-pointer opacity-0"
                            @change="onModalFileSelect"
                        />
                        <div v-if="!uploadForm.receipt" class="space-y-1">
                            <UploadCloud
                                class="mx-auto size-7 text-muted-foreground"
                            />
                            <p class="text-xs font-medium text-foreground">
                                Selecciona tu comprobante de pago
                            </p>
                            <p class="text-[10px] text-muted-foreground">
                                Formatos: JPG, PNG, WebP o PDF (máx. 5 MB)
                            </p>
                        </div>
                        <div v-else class="text-xs">
                            <FileText class="mx-auto size-7 text-primary" />
                            <p
                                class="mt-1 max-w-[240px] truncate font-medium text-foreground"
                            >
                                {{ uploadFileName }}
                            </p>
                            <p class="text-[10px] text-muted-foreground">
                                Archivo seleccionado
                            </p>
                        </div>
                    </div>

                    <p
                        v-if="uploadForm.errors.receipt"
                        class="text-xs font-medium text-destructive"
                    >
                        {{ uploadForm.errors.receipt }}
                    </p>

                    <DialogFooter class="gap-2 sm:gap-0">
                        <Button
                            type="button"
                            variant="outline"
                            class="rounded-xl"
                            @click="isUploadModalOpen = false"
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            class="rounded-xl"
                            :disabled="
                                !uploadForm.receipt || uploadForm.processing
                            "
                        >
                            <span v-if="uploadForm.processing"
                                >Subiendo...</span
                            >
                            <span v-else>Confirmar y Enviar</span>
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
