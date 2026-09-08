<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Ban, Printer } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { usePermissions } from '@/composables/usePermissions';
import { formatPrice } from '@/lib/catalog';
import type { StoreSettings } from '@/lib/catalog';
import { PAYMENT_METHOD_LABELS, receiptNumber } from '@/lib/sales';

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Ventas', href: '/admin/sales' },
            { title: 'Detalle', href: '' },
        ],
    }),
});

type Item = {
    id: number;
    product_id: number | null;
    product_name_snapshot: string;
    product_code_snapshot: string | null;
    quantity: number;
    unit_price: string;
    subtotal: number;
};

type SaleDetail = {
    id: number;
    receipt_number: number;
    origin: string;
    customer_name: string | null;
    customer_phone: string | null;
    payment_method: string;
    subtotal: string;
    discount_amount: string;
    total: string;
    status: string;
    notes: string | null;
    created_at: string;
    items: Item[];
    inquiry: { id: number; customer_name: string } | null;
    creator: { id: number; name: string } | null;
    voider: { id: number; name: string } | null;
    voided_at: string | null;
};

const props = defineProps<{ sale: SaleDetail }>();

const page = usePage();
const store = computed(() => page.props.store as StoreSettings);
const cur = (n: string | number) => formatPrice(n, store.value.currency_symbol);
const { can } = usePermissions();

const num = (n: string | number) => {
    const amount = typeof n === 'string' ? parseFloat(n) : n;

    return Number.isNaN(amount)
        ? '0.00'
        : amount.toLocaleString('es-BO', {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
          });
};

const totalFmt = (n: string | number) =>
    `${store.value.currency_symbol} ${num(n)}`;

const fmtDate = (value: string) => {
    const d = new Date(value);
    const pad = (x: number) => String(x).padStart(2, '0');

    return `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

const hasDiscount = computed(() => Number(props.sale.discount_amount) > 0);

const paymentLabel = computed(
    () =>
        PAYMENT_METHOD_LABELS[props.sale.payment_method] ??
        props.sale.payment_method,
);

const phone = computed(() => {
    const raw = store.value.whatsapp?.trim() ?? '';

    if (!raw) {
        return '';
    }

    return raw.startsWith('+') ? raw : `+${raw}`;
});

// Las reglas de impresion (incluido @page con el ancho de 80 mm) se inyectan
// al montar y se quitan al salir. Si vivieran en un bloque <style> del SFC
// serian globales y persistirian: al ser una SPA, el chunk CSS no se
// descarga, y despues de visitar este detalle cualquier otra pantalla del
// panel se imprimiria en una tira de 80 mm.
const PRINT_STYLE_ID = 'comprobante-venta-print';

const PRINT_CSS = `
@page { size: 80mm auto; margin: 4mm; }
@media print {
    body { background: #fff !important; }
    [data-slot='sidebar'] { display: none !important; }
    [data-slot='sidebar-inset'] {
        margin: 0 !important;
        border: 0 !important;
        border-radius: 0 !important;
        box-shadow: none !important;
    }
}`;

onMounted(() => {
    if (document.getElementById(PRINT_STYLE_ID)) {
        return;
    }

    const style = document.createElement('style');
    style.id = PRINT_STYLE_ID;
    style.textContent = PRINT_CSS;
    document.head.appendChild(style);
});

onBeforeUnmount(() => {
    document.getElementById(PRINT_STYLE_ID)?.remove();
});

const printView = () => window.print();

const voidSale = () => {
    if (
        !confirm(
            '¿Anular esta venta? El stock que descontó se va a devolver a cada producto.',
        )
    ) {
        return;
    }

    router.patch(`/admin/sales/${props.sale.id}/void`);
};
</script>

<template>
    <Head :title="`Venta ${receiptNumber(sale.receipt_number)}`" />

    <div class="mx-auto max-w-4xl space-y-4 p-3 md:p-4">
        <div class="flex items-center justify-between gap-2 print:hidden">
            <div class="flex items-center gap-2">
                <Button variant="ghost" size="icon-sm" as-child>
                    <Link href="/admin/sales">
                        <ArrowLeft class="size-4" />
                    </Link>
                </Button>
                <h1 class="text-xl font-semibold">
                    Recibo {{ receiptNumber(sale.receipt_number) }}
                </h1>
                <Badge
                    :variant="
                        sale.status === 'confirmada' ? 'success' : 'secondary'
                    "
                >
                    {{
                        sale.status === 'confirmada' ? 'Confirmada' : 'Anulada'
                    }}
                </Badge>
            </div>
            <div class="flex items-center gap-2">
                <Button variant="outline" @click="printView">
                    <Printer class="size-4" /> Imprimir
                </Button>
                <Button
                    v-if="sale.status === 'confirmada' && can('sales.void')"
                    variant="outline"
                    class="text-destructive hover:text-destructive"
                    @click="voidSale"
                >
                    <Ban class="size-4" /> Anular venta
                </Button>
            </div>
        </div>

        <Card class="gap-3 py-4 print:hidden">
            <CardContent class="grid gap-3 sm:grid-cols-3">
                <div>
                    <p class="text-xs text-muted-foreground uppercase">
                        Cliente
                    </p>
                    <p class="font-medium">
                        {{ sale.customer_name ?? 'Consumidor final' }}
                    </p>
                    <p
                        v-if="sale.customer_phone"
                        class="text-xs text-muted-foreground"
                    >
                        {{ sale.customer_phone }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground uppercase">Fecha</p>
                    <p class="font-medium">
                        {{ new Date(sale.created_at).toLocaleString() }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground uppercase">
                        Método de pago
                    </p>
                    <p class="font-medium">{{ paymentLabel }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground uppercase">
                        Registrada por
                    </p>
                    <p class="font-medium">{{ sale.creator?.name ?? '—' }}</p>
                </div>
                <div v-if="sale.inquiry">
                    <p class="text-xs text-muted-foreground uppercase">
                        Origen
                    </p>
                    <Link
                        :href="`/admin/inquiries/${sale.inquiry.id}`"
                        class="font-medium hover:text-primary hover:underline"
                    >
                        Consulta #{{ sale.inquiry.id }}
                    </Link>
                </div>
                <div v-if="sale.notes" class="sm:col-span-3">
                    <p class="text-xs text-muted-foreground uppercase">Notas</p>
                    <p>{{ sale.notes }}</p>
                </div>
                <div v-if="sale.status === 'anulada'" class="sm:col-span-3">
                    <p class="text-xs text-muted-foreground uppercase">
                        Anulada
                    </p>
                    <p>
                        {{ sale.voider?.name ?? '—' }} ·
                        {{
                            sale.voided_at
                                ? new Date(sale.voided_at).toLocaleString()
                                : ''
                        }}
                    </p>
                </div>
            </CardContent>
        </Card>

        <Card class="gap-3 overflow-hidden py-4 print:hidden">
            <CardHeader class="pb-0">
                <CardTitle>Productos</CardTitle>
            </CardHeader>
            <CardContent class="p-0 pt-3">
                <table class="w-full text-sm">
                    <thead
                        class="admin-table-header text-left text-[11px] tracking-wider uppercase"
                    >
                        <tr>
                            <th class="px-4 py-2">Producto</th>
                            <th class="px-4 py-2 text-right">Cantidad</th>
                            <th class="px-4 py-2 text-right">Precio unit.</th>
                            <th class="px-4 py-2 text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/80">
                        <tr v-for="item in sale.items" :key="item.id">
                            <td class="px-4 py-3">
                                <Link
                                    v-if="item.product_id"
                                    :href="`/admin/products/${item.product_id}/edit`"
                                    class="font-medium hover:text-primary hover:underline"
                                >
                                    {{ item.product_name_snapshot }}
                                </Link>
                                <span v-else class="font-medium">
                                    {{ item.product_name_snapshot }}
                                </span>
                                <p class="text-xs text-muted-foreground">
                                    {{ item.product_code_snapshot }}
                                </p>
                            </td>
                            <td class="px-4 py-3 text-right">
                                {{ item.quantity }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono">
                                {{ cur(item.unit_price) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono">
                                {{ cur(item.subtotal) }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t">
                            <td colspan="3" class="px-4 py-2 text-right">
                                Subtotal
                            </td>
                            <td class="px-4 py-2 text-right font-mono">
                                {{ cur(sale.subtotal) }}
                            </td>
                        </tr>
                        <tr v-if="hasDiscount">
                            <td colspan="3" class="px-4 py-2 text-right">
                                Descuento
                            </td>
                            <td class="px-4 py-2 text-right font-mono">
                                −{{ cur(sale.discount_amount) }}
                            </td>
                        </tr>
                        <tr class="border-t">
                            <td
                                colspan="3"
                                class="px-4 py-3 text-right font-semibold"
                            >
                                Total
                            </td>
                            <td
                                class="px-4 py-3 text-right font-mono font-semibold"
                            >
                                {{ cur(sale.total) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </CardContent>
        </Card>

        <section
            id="print-receipt"
            class="mx-auto w-full max-w-[360px] rounded-lg border border-border bg-card px-4 py-5 font-mono text-[11px] leading-relaxed text-foreground shadow-sm print:max-w-none print:rounded-none print:border-0 print:bg-white print:p-0 print:text-black print:shadow-none"
        >
            <div class="text-center">
                <img
                    v-if="store.logo_url"
                    :src="store.logo_url"
                    :alt="store.name"
                    class="mx-auto mb-1 h-12 w-auto object-contain print:h-10"
                />
                <p class="text-[13px] font-bold">{{ store.name }}</p>
                <p v-if="store.address" class="whitespace-pre-line">
                    {{ store.address }}
                </p>
                <p v-if="phone || store.email">
                    {{ [phone, store.email].filter(Boolean).join(' · ') }}
                </p>
            </div>

            <div
                class="my-2 border-t border-dashed border-border print:border-black"
            />

            <p class="text-center font-bold">RECIBO DE VENTA</p>
            <p class="text-center text-[13px] font-bold">
                N.o {{ receiptNumber(sale.receipt_number) }}
            </p>

            <div class="mt-2 space-y-0.5">
                <div class="flex gap-2">
                    <span class="w-28 shrink-0">Fecha:</span>
                    <span>{{ fmtDate(sale.created_at) }}</span>
                </div>
                <div class="flex gap-2">
                    <span class="w-28 shrink-0">Cliente:</span>
                    <span class="min-w-0 flex-1 truncate">
                        {{ sale.customer_name ?? 'Consumidor final' }}
                    </span>
                </div>
                <div v-if="sale.customer_phone" class="flex gap-2">
                    <span class="w-28 shrink-0">Teléfono:</span>
                    <span class="min-w-0 flex-1 truncate">
                        {{ sale.customer_phone }}
                    </span>
                </div>
                <div class="flex gap-2">
                    <span class="w-28 shrink-0">Pago:</span>
                    <span>{{ paymentLabel }}</span>
                </div>
                <div class="flex gap-2">
                    <span class="w-28 shrink-0">Atendió:</span>
                    <span class="min-w-0 flex-1 truncate">
                        {{ sale.creator?.name ?? '—' }}
                    </span>
                </div>
            </div>

            <div
                class="my-2 border-t border-dashed border-border print:border-black"
            />

            <table class="w-full tabular-nums">
                <thead>
                    <tr>
                        <th class="text-left font-bold">Producto</th>
                        <th class="text-right font-bold">Cant</th>
                        <th class="text-right font-bold">P.Unit</th>
                        <th class="text-right font-bold">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in sale.items" :key="item.id">
                        <td class="max-w-0 truncate pr-2">
                            {{ item.product_name_snapshot }}
                        </td>
                        <td class="text-right">{{ item.quantity }}</td>
                        <td class="text-right">{{ item.unit_price }}</td>
                        <td class="text-right">{{ num(item.subtotal) }}</td>
                    </tr>
                </tbody>
            </table>

            <div
                class="my-2 border-t border-dashed border-border print:border-black"
            />

            <div v-if="hasDiscount" class="space-y-0.5">
                <div class="flex justify-end">
                    <span>Subtotal:&nbsp;{{ totalFmt(sale.subtotal) }}</span>
                </div>
                <div class="flex justify-end">
                    <span>
                        Descuento:&nbsp;−{{ totalFmt(sale.discount_amount) }}
                    </span>
                </div>
            </div>

            <div class="flex justify-end font-bold">
                <span>TOTAL:&nbsp;{{ totalFmt(sale.total) }}</span>
            </div>

            <div
                v-if="sale.status === 'anulada'"
                class="mt-3 border-2 border-border p-2 text-center font-bold uppercase print:border-black"
            >
                <p>Anulada</p>
                <p class="font-normal normal-case">
                    {{ sale.voider?.name ?? '—' }} ·
                    {{ sale.voided_at ? fmtDate(sale.voided_at) : '' }}
                </p>
            </div>

            <p v-if="sale.notes" class="mt-3 whitespace-pre-line">
                <span class="font-bold">Notas:</span> {{ sale.notes }}
            </p>

            <p class="mt-3 text-center">¡Gracias por su compra!</p>

            <div class="mt-10 flex justify-between gap-4">
                <div class="flex-1 text-center">
                    <div class="border-t border-border pt-1 print:border-black">
                        Recibí conforme
                    </div>
                </div>
                <div class="flex-1 text-center">
                    <div class="border-t border-border pt-1 print:border-black">
                        Entregado por
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
