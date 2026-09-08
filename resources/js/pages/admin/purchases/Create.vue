<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import ProductCombobox from '@/components/admin/ProductCombobox.vue';
import type { ProductOption } from '@/components/admin/ProductCombobox.vue';
import SearchCombobox from '@/components/admin/SearchCombobox.vue';
import type { ComboOption } from '@/components/admin/SearchCombobox.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatPrice } from '@/lib/catalog';
import type { StoreSettings } from '@/lib/catalog';

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Compras', href: '/admin/purchases' },
            { title: 'Nueva compra', href: '/admin/purchases/create' },
        ],
    }),
});

type SupplierOption = { id: number; name: string };

const props = defineProps<{
    suppliers: SupplierOption[];
    products: ProductOption[];
}>();

// El proveedor tambien se busca escribiendo, igual que el producto: con la
// lista creciendo, un <select> obliga a recorrerla a mano.
const supplierOptions = computed<ComboOption[]>(() =>
    props.suppliers.map((supplier) => ({
        id: supplier.id,
        label: supplier.name,
    })),
);

const page = usePage();
const store = computed(() => page.props.store as StoreSettings);
const cur = (n: number) => formatPrice(n, store.value.currency_symbol);

const form = useForm({
    supplier_id: null as number | null,
    reference_number: '',
    notes: '',
    // unit_cost arranca vacio a proposito: si arrancara en 0, olvidarse de
    // llenarlo registraria la compra con costo cero en silencio.
    items: [
        {
            product_id: null as number | null,
            quantity: 1,
            unit_cost: '' as number | string,
        },
    ],
});

const addItem = () =>
    form.items.push({ product_id: null, quantity: 1, unit_cost: '' });
const removeItem = (i: number) => form.items.splice(i, 1);

// Al elegir un producto sugiere su ultimo costo conocido como punto de
// partida. Si el producto no tiene costo, deja el campo vacio: sugerir 0
// seria inventar un dato.
const onProductPick = (i: number, product: ProductOption) => {
    const known = Number(product?.cost ?? 0);

    if (known > 0 && !form.items[i].unit_cost) {
        form.items[i].unit_cost = known;
    }
};

// Un producto repetido en dos filas suele ser un error de carga: sumaria
// stock dos veces. Se avisa, no se bloquea, porque a veces es legitimo
// (dos lotes del mismo item con costos distintos).
const duplicatedIds = computed(() => {
    const seen = new Map<number, number>();

    for (const item of form.items) {
        if (item.product_id !== null) {
            seen.set(item.product_id, (seen.get(item.product_id) ?? 0) + 1);
        }
    }

    return new Set(
        [...seen.entries()].filter(([, n]) => n > 1).map(([id]) => id),
    );
});

const isDuplicated = (i: number) => {
    const id = form.items[i].product_id;

    return id !== null && duplicatedIds.value.has(id);
};

const subtotal = (i: number) =>
    (Number(form.items[i].quantity) || 0) *
    (Number(form.items[i].unit_cost) || 0);
const total = computed(() =>
    form.items.reduce((sum, _, i) => sum + subtotal(i), 0),
);

// Aviso de remito repetido para el mismo proveedor. Solo avisa: un proveedor
// puede reiniciar su numeracion entre anios, asi que bloquear seria peor que
// dejar decidir a quien carga.
const duplicateRef = ref<{ purchase_id: number; created_at: string } | null>(
    null,
);

let refTimer: ReturnType<typeof setTimeout> | null = null;

watch(
    () => [form.reference_number, form.supplier_id],
    () => {
        if (refTimer) {
            clearTimeout(refTimer);
        }

        const reference = form.reference_number.trim();

        if (!reference) {
            duplicateRef.value = null;

            return;
        }

        refTimer = setTimeout(async () => {
            const params = new URLSearchParams({ reference });

            if (form.supplier_id !== null) {
                params.set('supplier_id', String(form.supplier_id));
            }

            try {
                const res = await fetch(
                    `/admin/purchases/reference-check?${params}`,
                    { headers: { Accept: 'application/json' } },
                );

                if (!res.ok) {
                    return;
                }

                const data = await res.json();

                duplicateRef.value = data.duplicate
                    ? {
                          purchase_id: data.purchase_id,
                          created_at: data.created_at,
                      }
                    : null;
            } catch {
                // Sin red no se avisa nada: es una ayuda, no una validacion.
                duplicateRef.value = null;
            }
        }, 400);
    },
);

const submit = () => form.post('/admin/purchases');
</script>

<template>
    <Head title="Nueva compra" />

    <div class="w-full space-y-3 p-3 md:p-4">
        <div class="flex items-center gap-2">
            <Button variant="ghost" size="icon-sm" as-child>
                <Link href="/admin/purchases"
                    ><ArrowLeft class="size-4"
                /></Link>
            </Button>
            <h1 class="text-xl font-semibold">Nueva compra</h1>
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <Card class="gap-3 py-4">
                <CardHeader class="pb-0"
                    ><CardTitle>Datos de la compra</CardTitle></CardHeader
                >
                <CardContent class="grid gap-4 md:grid-cols-3">
                    <div class="space-y-1.5">
                        <Label for="supplier_id">Proveedor</Label>
                        <SearchCombobox
                            v-model="form.supplier_id"
                            :options="supplierOptions"
                            placeholder="Buscar proveedor…"
                            :aria-invalid="!!form.errors.supplier_id"
                        />
                        <InputError :message="form.errors.supplier_id" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="reference_number">
                            N.º de referencia
                            <span class="text-muted-foreground"
                                >(opcional)</span
                            >
                        </Label>
                        <Input
                            id="reference_number"
                            v-model="form.reference_number"
                            placeholder="Remito o factura del proveedor"
                        />
                        <InputError :message="form.errors.reference_number" />
                        <p
                            v-if="duplicateRef"
                            class="text-xs text-amber-600 dark:text-amber-400"
                        >
                            Ya existe una compra con este remito para el mismo
                            proveedor ({{ duplicateRef.created_at }}).
                            <Link
                                :href="`/admin/purchases/${duplicateRef.purchase_id}`"
                                class="font-semibold underline"
                            >
                                Verla
                            </Link>
                        </p>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="notes">Notas</Label>
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            rows="1"
                            placeholder="Observaciones de la entrega"
                            class="min-h-9 w-full rounded-md border border-input bg-card px-3 py-1.5 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        ></textarea>
                        <InputError :message="form.errors.notes" />
                    </div>
                </CardContent>
            </Card>

            <Card class="gap-3 py-4">
                <CardHeader class="pb-0"
                    ><CardTitle>Productos</CardTitle></CardHeader
                >
                <CardContent class="space-y-3">
                    <InputError :message="form.errors.items" />

                    <!-- Encabezados: sin esto los campos numericos son dos
                         cajas sin nombre y no se sabe cual es cual. -->
                    <div
                        class="hidden gap-2 px-1 text-[11px] font-medium tracking-wide text-muted-foreground uppercase sm:grid sm:grid-cols-[minmax(0,1fr)_100px_120px_110px_36px]"
                    >
                        <span>Producto</span>
                        <span>Cantidad</span>
                        <span>Costo unit.</span>
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
                                >Producto</span
                            >
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
                            <p
                                v-if="isDuplicated(i)"
                                class="mt-1 text-xs text-amber-600 dark:text-amber-400"
                            >
                                Este producto ya está en otra fila: se le sumará
                                el stock de ambas.
                            </p>
                        </div>
                        <div>
                            <span
                                class="mb-1 block text-[11px] font-medium tracking-wide text-muted-foreground uppercase sm:hidden"
                                >Cantidad</span
                            >
                            <Input
                                v-model.number="item.quantity"
                                type="number"
                                min="1"
                                placeholder="Cant."
                                :aria-invalid="
                                    !!form.errors[`items.${i}.quantity`]
                                "
                            />
                            <InputError
                                :message="form.errors[`items.${i}.quantity`]"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <span
                                class="mb-1 block text-[11px] font-medium tracking-wide text-muted-foreground uppercase sm:hidden"
                                >Costo unit.</span
                            >
                            <Input
                                v-model.number="item.unit_cost"
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="Costo unit."
                                :aria-invalid="
                                    !!form.errors[`items.${i}.unit_cost`]
                                "
                            />
                            <InputError
                                :message="form.errors[`items.${i}.unit_cost`]"
                                class="mt-1"
                            />
                        </div>
                        <div
                            class="flex h-9 items-center justify-end font-mono text-sm text-muted-foreground"
                        >
                            {{ cur(subtotal(i)) }}
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

                    <div
                        class="flex items-center justify-end gap-2 border-t border-border pt-3 text-base font-semibold"
                    >
                        Total: {{ cur(total) }}
                    </div>
                </CardContent>
            </Card>

            <div class="flex justify-end gap-2">
                <Button variant="outline" as-child>
                    <Link href="/admin/purchases">Cancelar</Link>
                </Button>
                <Button type="submit" :disabled="form.processing">
                    Registrar compra
                </Button>
            </div>
        </form>
    </div>
</template>
