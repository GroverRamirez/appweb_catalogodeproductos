<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
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

const addItem = () =>
    form.items.push({ product_id: null, quantity: 1, unit_price: '' });
const removeItem = (i: number) => form.items.splice(i, 1);

const productById = computed(
    () => new Map(props.products.map((p) => [p.id, p])),
);

/** El precio al que realmente se vende: la oferta si existe, si no el de lista. */
const sellPrice = (product: SaleProductOption) =>
    Number(product.sale_price ?? product.price);

const onProductPick = (i: number, product: ProductOption) => {
    const full = productById.value.get(product.id);

    if (full && !form.items[i].unit_price) {
        form.items[i].unit_price = sellPrice(full);
    }
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
            <Card class="gap-3 py-4">
                <CardHeader class="pb-0">
                    <CardTitle>Datos de la venta</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <div class="space-y-1.5">
                        <Label for="customer_name">
                            Cliente
                            <span class="text-muted-foreground">
                                (opcional)
                            </span>
                        </Label>
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
                            <span class="text-muted-foreground">
                                (opcional)
                            </span>
                        </Label>
                        <Input
                            id="customer_phone"
                            v-model="form.customer_phone"
                            placeholder="Ej. 70000000"
                            :aria-invalid="!!form.errors.customer_phone"
                        />
                        <InputError :message="form.errors.customer_phone" />
                        <p class="text-xs text-muted-foreground">
                            Si ya compró antes, se reusa su ficha de cliente.
                        </p>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="id_card">
                            CI
                            <span class="text-muted-foreground">
                                (opcional)
                            </span>
                        </Label>
                        <Input
                            id="id_card"
                            v-model="form.id_card"
                            placeholder="Carnet de identidad"
                            :aria-invalid="!!form.errors.id_card"
                        />
                        <InputError :message="form.errors.id_card" />
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
                    <div class="space-y-1.5">
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
                </CardContent>
            </Card>

            <Card class="gap-3 py-4">
                <CardHeader class="pb-0">
                    <CardTitle>Productos</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <InputError :message="form.errors.items" />

                    <!-- Encabezados: sin esto los campos numericos son dos
                         cajas sin nombre y no se sabe cual es cual. -->
                    <div
                        class="hidden gap-2 px-1 text-[11px] font-medium tracking-wide text-muted-foreground uppercase sm:grid sm:grid-cols-[minmax(0,1fr)_100px_120px_110px_36px]"
                    >
                        <span>Producto</span>
                        <span>Cantidad</span>
                        <span>Precio unit.</span>
                        <span class="text-right">Subtotal</span>
                        <span></span>
                    </div>

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

                    <!-- El total se muestra en vivo como ayuda: el que vale es
                         el que recalcula el servidor desde la base al guardar. -->
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
