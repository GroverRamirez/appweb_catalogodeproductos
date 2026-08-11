<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
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
type ProductOption = {
    id: number;
    name: string;
    code: string;
    cost: string | null;
    stock: number;
};

const props = defineProps<{
    suppliers: SupplierOption[];
    products: ProductOption[];
}>();

const page = usePage();
const store = computed(() => page.props.store as StoreSettings);
const cur = (n: number) => formatPrice(n, store.value.currency_symbol);

const form = useForm({
    supplier_id: null as number | null,
    reference_number: '',
    notes: '',
    items: [{ product_id: null as number | null, quantity: 1, unit_cost: 0 }],
});

const productsById = computed(
    () => new Map(props.products.map((p) => [p.id, p])),
);

const addItem = () =>
    form.items.push({ product_id: null, quantity: 1, unit_cost: 0 });
const removeItem = (i: number) => form.items.splice(i, 1);

// Al elegir un producto, sugiere su último costo conocido como punto de partida.
const onProductPick = (i: number) => {
    const product = productsById.value.get(form.items[i].product_id!);

    if (product && !form.items[i].unit_cost) {
        form.items[i].unit_cost = Number(product.cost ?? 0);
    }
};

const subtotal = (i: number) =>
    (form.items[i].quantity || 0) * (form.items[i].unit_cost || 0);
const total = computed(() =>
    form.items.reduce((sum, _, i) => sum + subtotal(i), 0),
);

const submit = () => form.post('/admin/purchases');
</script>

<template>
    <Head title="Nueva compra" />

    <div class="mx-auto max-w-5xl space-y-3 p-3 md:p-4">
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
                        <select
                            id="supplier_id"
                            v-model="form.supplier_id"
                            class="h-9 w-full rounded-md border bg-background px-3 text-sm"
                        >
                            <option :value="null">— Sin proveedor —</option>
                            <option
                                v-for="s in suppliers"
                                :key="s.id"
                                :value="s.id"
                            >
                                {{ s.name }}
                            </option>
                        </select>
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
                    </div>
                    <div class="space-y-1.5">
                        <Label for="notes">Notas</Label>
                        <Input id="notes" v-model="form.notes" />
                    </div>
                </CardContent>
            </Card>

            <Card class="gap-3 py-4">
                <CardHeader class="pb-0"
                    ><CardTitle>Productos</CardTitle></CardHeader
                >
                <CardContent class="space-y-3">
                    <InputError :message="form.errors.items" />
                    <div
                        v-for="(item, i) in form.items"
                        :key="i"
                        class="grid items-start gap-2 sm:grid-cols-[1fr_100px_120px_100px_36px]"
                    >
                        <div>
                            <select
                                v-model="item.product_id"
                                class="h-9 w-full rounded-md border bg-background px-3 text-sm"
                                :aria-invalid="
                                    !!form.errors[`items.${i}.product_id`]
                                "
                                @change="onProductPick(i)"
                            >
                                <option :value="null">
                                    — Elegí un producto —
                                </option>
                                <option
                                    v-for="p in products"
                                    :key="p.id"
                                    :value="p.id"
                                >
                                    {{ p.code }} — {{ p.name }} (stock:
                                    {{ p.stock }})
                                </option>
                            </select>
                            <InputError
                                :message="form.errors[`items.${i}.product_id`]"
                                class="mt-1"
                            />
                        </div>
                        <div>
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
