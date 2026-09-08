<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Eye, Plus, Search, UserRound } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissions } from '@/composables/usePermissions';
import { formatPrice } from '@/lib/catalog';
import type { StoreSettings } from '@/lib/catalog';

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Clientes', href: '/admin/clients' },
        ],
    }),
});

type ClientRow = {
    id: number;
    name: string;
    phone: string | null;
    id_card: string | null;
    email: string | null;
    is_active: boolean;
    user_id: number | null;
    purchases_count: number;
    total_spent: string | null;
};

const props = defineProps<{
    clients: {
        data: ClientRow[];
        links: { url: string | null; label: string; active: boolean }[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: { q?: string; status?: string };
}>();

const page = usePage();
const store = computed(() => page.props.store as StoreSettings);
const cur = (n: string | null) =>
    formatPrice(n ?? 0, store.value.currency_symbol);
const { can } = usePermissions();

const q = ref(props.filters.q ?? '');
const status = ref(props.filters.status ?? '');

let timer: ReturnType<typeof setTimeout> | null = null;
watch([q, status], () => {
    if (timer) {
        clearTimeout(timer);
    }

    timer = setTimeout(() => {
        router.get(
            '/admin/clients',
            { q: q.value, status: status.value },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});
</script>

<template>
    <Head title="Clientes" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            :icon="UserRound"
            eyebrow="Operación"
            title="Clientes"
            :description="`${clients.total} cliente(s) registrado(s).`"
        />

        <div class="flex flex-wrap items-center gap-3">
            <div class="relative max-w-sm flex-1">
                <Search
                    class="absolute top-1/2 left-2 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="q"
                    placeholder="Buscar por nombre, teléfono, CI o email..."
                    class="pl-8"
                />
            </div>
            <select
                v-model="status"
                class="h-9 rounded-md border bg-card px-3 text-sm"
            >
                <option value="">Todos</option>
                <option value="1">Activos</option>
                <option value="0">Inactivos</option>
            </select>
            <Button
                v-if="can('clients.create')"
                as-child
                class="ml-auto rounded-md"
            >
                <Link href="/admin/clients/create">
                    <Plus class="size-4" /> Nuevo cliente
                </Link>
            </Button>
        </div>

        <div class="overflow-x-auto rounded-md border bg-card">
            <table class="w-full text-sm">
                <thead
                    class="bg-muted/50 text-left text-xs text-muted-foreground uppercase"
                >
                    <tr>
                        <th class="px-3 py-2">Nombre</th>
                        <th class="px-3 py-2">Teléfono</th>
                        <th class="px-3 py-2">CI</th>
                        <th class="px-3 py-2 text-right">Compras</th>
                        <th class="px-3 py-2 text-right">Total gastado</th>
                        <th class="w-28 px-3 py-2">Estado</th>
                        <th class="w-16 px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr
                        v-for="c in clients.data"
                        :key="c.id"
                        class="hover:bg-muted/30"
                    >
                        <td class="px-3 py-2">
                            <Link
                                :href="`/admin/clients/${c.id}`"
                                class="font-medium hover:text-primary hover:underline"
                            >
                                {{ c.name }}
                            </Link>
                            <!-- Distingue al que además tiene cuenta web del
                                 que solo compra en la tienda. -->
                            <span
                                v-if="c.user_id"
                                class="ml-2 text-xs text-muted-foreground"
                            >
                                · cuenta web
                            </span>
                            <span
                                v-if="c.email"
                                class="block text-xs text-muted-foreground"
                            >
                                {{ c.email }}
                            </span>
                        </td>
                        <td class="px-3 py-2 font-mono">
                            {{ c.phone ?? '—' }}
                        </td>
                        <td class="px-3 py-2 text-muted-foreground">
                            {{ c.id_card ?? '—' }}
                        </td>
                        <td class="px-3 py-2 text-right">
                            {{ c.purchases_count }}
                        </td>
                        <td class="px-3 py-2 text-right font-mono">
                            {{ cur(c.total_spent) }}
                        </td>
                        <td class="px-3 py-2">
                            <Badge
                                :variant="c.is_active ? 'success' : 'secondary'"
                            >
                                {{ c.is_active ? 'Activo' : 'Inactivo' }}
                            </Badge>
                        </td>
                        <td class="px-3 py-2 text-right">
                            <Button variant="ghost" size="icon-sm" as-child>
                                <Link :href="`/admin/clients/${c.id}`">
                                    <Eye class="size-4" />
                                </Link>
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="!clients.data.length">
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
            :links="clients.links"
            :from="clients.from"
            :to="clients.to"
            :total="clients.total"
        />
    </div>
</template>
