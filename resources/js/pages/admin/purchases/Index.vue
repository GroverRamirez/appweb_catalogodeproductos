<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Eye, PackagePlus, Plus, Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatPrice } from '@/lib/catalog';
import type { StoreSettings } from '@/lib/catalog';

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Compras', href: '/admin/purchases' },
        ],
    }),
});

type PurchaseRow = {
    id: number;
    reference_number: string | null;
    status: string;
    total_cost: string;
    items_count: number;
    created_at: string;
    supplier: { id: number; name: string } | null;
};

const props = defineProps<{
    purchases: {
        data: PurchaseRow[];
        links: { url: string | null; label: string; active: boolean }[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: { q?: string; status?: string };
}>();

const page = usePage();
const store = computed(() => page.props.store as StoreSettings);
const cur = (n: string) => formatPrice(n, store.value.currency_symbol);

const q = ref(props.filters.q ?? '');
const status = ref(props.filters.status ?? '');

let timer: ReturnType<typeof setTimeout> | null = null;
watch([q, status], () => {
    if (timer) {
        clearTimeout(timer);
    }

    timer = setTimeout(() => {
        router.get(
            '/admin/purchases',
            { q: q.value, status: status.value },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});
</script>

<template>
    <Head title="Compras" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            :icon="PackagePlus"
            eyebrow="Inventario"
            title="Compras"
            description="Ingresos de mercadería registrados, con el stock que sumaron."
        >
            <template #actions>
                <Button as-child class="rounded-md">
                    <Link href="/admin/purchases/create">
                        <Plus class="size-4" /> Nueva compra
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="flex flex-wrap items-center gap-3">
            <div class="relative max-w-sm flex-1">
                <Search
                    class="absolute top-1/2 left-2 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="q"
                    placeholder="Buscar por proveedor o referencia..."
                    class="pl-8"
                />
            </div>
            <select
                v-model="status"
                class="h-9 rounded-md border bg-card px-3 text-sm"
            >
                <option value="">Todas</option>
                <option value="confirmada">Confirmadas</option>
                <option value="anulada">Anuladas</option>
            </select>
        </div>

        <div class="overflow-x-auto rounded-md border bg-card">
            <table class="w-full text-sm">
                <thead
                    class="bg-muted/50 text-left text-xs text-muted-foreground uppercase"
                >
                    <tr>
                        <th class="px-3 py-2">Referencia</th>
                        <th class="px-3 py-2">Proveedor</th>
                        <th class="px-3 py-2">Fecha</th>
                        <th class="px-3 py-2 text-right">Productos</th>
                        <th class="px-3 py-2 text-right">Total</th>
                        <th class="w-28 px-3 py-2">Estado</th>
                        <th class="w-16 px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr
                        v-for="p in purchases.data"
                        :key="p.id"
                        class="hover:bg-muted/30"
                    >
                        <td class="px-3 py-2 font-medium">
                            {{ p.reference_number ?? `#${p.id}` }}
                        </td>
                        <td class="px-3 py-2 text-muted-foreground">
                            {{ p.supplier?.name ?? '—' }}
                        </td>
                        <td class="px-3 py-2 text-muted-foreground">
                            {{ new Date(p.created_at).toLocaleDateString() }}
                        </td>
                        <td class="px-3 py-2 text-right">
                            {{ p.items_count }}
                        </td>
                        <td class="px-3 py-2 text-right font-mono">
                            {{ cur(p.total_cost) }}
                        </td>
                        <td class="px-3 py-2">
                            <Badge
                                :variant="
                                    p.status === 'confirmada'
                                        ? 'success'
                                        : 'secondary'
                                "
                            >
                                {{
                                    p.status === 'confirmada'
                                        ? 'Confirmada'
                                        : 'Anulada'
                                }}
                            </Badge>
                        </td>
                        <td class="px-3 py-2 text-right">
                            <Button variant="ghost" size="icon-sm" as-child>
                                <Link :href="`/admin/purchases/${p.id}`">
                                    <Eye class="size-4" />
                                </Link>
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="!purchases.data.length">
                        <td
                            colspan="7"
                            class="px-3 py-6 text-center text-muted-foreground"
                        >
                            Sin resultados.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination
            :links="purchases.links"
            :from="purchases.from"
            :to="purchases.to"
            :total="purchases.total"
        />
    </div>
</template>
