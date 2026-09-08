<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, Check, Coins } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissions } from '@/composables/usePermissions';
import { formatPrice } from '@/lib/catalog';
import type { StoreSettings } from '@/lib/catalog';

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Productos', href: '/admin/products' },
            { title: 'Costos', href: '/admin/products/costs' },
        ],
    }),
});

type Row = {
    id: number;
    code: string;
    name: string;
    price: string;
    sale_price: string | null;
    cost: string | null;
    stock: number;
};

const props = defineProps<{
    products: Row[];
    showAll: boolean;
    missingCount: number;
    totalCount: number;
}>();

const page = usePage();
const store = computed(() => page.props.store as StoreSettings);
const cur = (n: number) => formatPrice(n, store.value.currency_symbol);
const { can } = usePermissions();

// Borradores locales por id. No usamos useForm porque al guardar volvemos con
// back() y las props se recargan: limpiar un objeto propio es más simple que
// resincronizar un form contra la lista nueva.
const drafts = ref<Record<number, string>>({});
const processing = ref(false);
const errors = ref<Record<string, string>>({});

// El margen se mide contra el precio al que realmente se vende.
const sellPrice = (r: Row) => Number(r.sale_price ?? r.price);

const draftCost = (r: Row): number | null => {
    const raw = drafts.value[r.id];

    if (raw === undefined || raw === '') {
        return r.cost !== null ? Number(r.cost) : null;
    }

    const parsed = Number(raw);

    return Number.isNaN(parsed) ? null : parsed;
};

const margin = (r: Row): number | null => {
    const cost = draftCost(r);

    return cost === null ? null : sellPrice(r) - cost;
};

const marginPct = (r: Row): number | null => {
    const value = margin(r);
    const sell = sellPrice(r);

    return value === null || sell <= 0 ? null : (value / sell) * 100;
};

const filled = computed(() =>
    Object.entries(drafts.value).filter(
        ([, value]) => value !== '' && value !== undefined,
    ),
);

const invalidCount = computed(
    () =>
        props.products.filter((r) => {
            const value = margin(r);

            return value !== null && value < 0;
        }).length,
);

const submit = () => {
    if (!filled.value.length) {
        return;
    }

    router.patch(
        '/admin/products/costs',
        {
            items: filled.value.map(([id, cost]) => ({
                id: Number(id),
                cost,
            })),
        },
        {
            preserveScroll: true,
            onStart: () => {
                processing.value = true;
                errors.value = {};
            },
            onFinish: () => {
                processing.value = false;
            },
            onSuccess: () => {
                drafts.value = {};
            },
            onError: (e) => {
                errors.value = e as Record<string, string>;
            },
        },
    );
};
</script>

<template>
    <Head title="Cargar costos" />

    <div class="space-y-6 p-4 pb-28 md:p-6">
        <PageHeader
            :icon="Coins"
            eyebrow="Inventario"
            title="Cargar costos"
            description="Sin costo no se puede valorizar el inventario ni calcular el margen."
        >
            <template #actions>
                <Button variant="outline" as-child class="rounded-md">
                    <Link href="/admin/products">
                        <ArrowLeft class="size-4" /> Volver a productos
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-muted-foreground">
                <span class="font-semibold text-foreground">
                    {{ missingCount }}
                </span>
                de {{ totalCount }} productos sin costo cargado.
                <span v-if="!showAll">Mostrando solo los que faltan.</span>
            </p>
            <Link
                :href="
                    showAll
                        ? '/admin/products/costs'
                        : '/admin/products/costs?all=1'
                "
                class="text-sm font-semibold text-primary hover:underline"
            >
                {{
                    showAll
                        ? 'Ver solo los que faltan'
                        : 'Ver todos los productos'
                }}
            </Link>
        </div>

        <div
            v-if="invalidCount > 0"
            class="flex items-start gap-3 rounded-xl border border-amber-500/45 bg-amber-50/60 p-4 text-sm dark:border-amber-900/50 dark:bg-amber-500/10"
        >
            <AlertTriangle class="mt-0.5 size-5 shrink-0 text-amber-600" />
            <span>
                <span class="block font-semibold">
                    {{ invalidCount }} producto(s) con costo mayor al precio de
                    venta
                </span>
                <span class="text-muted-foreground">
                    Se puede guardar igual, pero revisá esas filas: darían
                    margen negativo.
                </span>
            </span>
        </div>

        <div class="overflow-x-auto rounded-md border bg-card">
            <table class="w-full text-sm">
                <thead
                    class="bg-muted/50 text-left text-xs text-muted-foreground uppercase"
                >
                    <tr>
                        <th class="px-3 py-2">Producto</th>
                        <th class="px-3 py-2 text-right">Stock</th>
                        <th class="px-3 py-2 text-right">Precio venta</th>
                        <th class="w-40 px-3 py-2">Costo</th>
                        <th class="px-3 py-2 text-right">Margen</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr
                        v-for="r in products"
                        :key="r.id"
                        class="hover:bg-muted/30"
                    >
                        <td class="px-3 py-2">
                            <Link
                                :href="`/admin/products/${r.id}/edit`"
                                class="font-medium hover:text-primary hover:underline"
                            >
                                {{ r.name }}
                            </Link>
                            <span class="block text-xs text-muted-foreground">
                                {{ r.code }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums">
                            {{ r.stock }}
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums">
                            {{ cur(sellPrice(r)) }}
                        </td>
                        <td class="px-3 py-2">
                            <Input
                                v-model="drafts[r.id]"
                                type="number"
                                min="0"
                                step="0.01"
                                :placeholder="r.cost ?? 'Sin cargar'"
                                :aria-invalid="!!errors[`items.${r.id}.cost`]"
                                class="text-right tabular-nums"
                            />
                        </td>
                        <td
                            class="px-3 py-2 text-right tabular-nums"
                            :class="{
                                'text-destructive':
                                    margin(r) !== null && margin(r)! < 0,
                                'text-muted-foreground': margin(r) === null,
                            }"
                        >
                            <template v-if="margin(r) !== null">
                                {{ cur(margin(r)!) }}
                                <span
                                    v-if="marginPct(r) !== null"
                                    class="block text-xs text-muted-foreground"
                                >
                                    {{ marginPct(r)!.toFixed(0) }}%
                                </span>
                            </template>
                            <template v-else>—</template>
                        </td>
                    </tr>
                    <tr v-if="!products.length">
                        <td
                            colspan="5"
                            class="px-3 py-10 text-center text-muted-foreground"
                        >
                            <Check
                                class="mx-auto mb-2 size-6 text-muted-foreground/50"
                            />
                            Todos los productos tienen costo cargado.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Barra fija: la tabla es larga y el botón tiene que estar siempre a mano -->
        <div
            v-if="can('inventory.adjust') && products.length"
            class="fixed inset-x-0 bottom-0 border-t border-border bg-card/95 p-3 backdrop-blur"
        >
            <div
                class="mx-auto flex max-w-5xl items-center justify-between gap-3"
            >
                <p class="text-sm text-muted-foreground">
                    {{ filled.length }} costo(s) por guardar
                </p>
                <Button
                    :disabled="!filled.length || processing"
                    @click="submit"
                >
                    {{ processing ? 'Guardando...' : 'Guardar costos' }}
                </Button>
            </div>
        </div>
    </div>
</template>
