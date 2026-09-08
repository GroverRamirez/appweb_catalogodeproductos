<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { LayoutGrid, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import ProductTabs from '@/components/admin/ProductTabs.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Productos', href: '/admin/products' },
        ],
    }),
});

type Category = {
    id: number;
    name: string;
    slug: string;
    sort_order: number;
    is_active: boolean;
    parent?: { id: number; name: string } | null;
};

const props = defineProps<{
    categories: {
        data: Category[];
        links: { url: string | null; label: string; active: boolean }[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: { q?: string; status?: string };
}>();

const q = ref(props.filters.q ?? '');
const status = ref(props.filters.status ?? '');

let timer: ReturnType<typeof setTimeout> | null = null;
watch([q, status], () => {
    if (timer) {
        clearTimeout(timer);
    }

    timer = setTimeout(() => {
        router.get(
            '/admin/categories',
            { q: q.value, status: status.value },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

const destroy = (c: Category) => {
    if (!confirm(`¿Eliminar la categoría "${c.name}"?`)) {
        return;
    }

    router.delete(`/admin/categories/${c.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Categorías" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            :icon="LayoutGrid"
            eyebrow="Catálogo"
            title="Gestión de Productos"
            description="Administra categorías, marcas y prendas."
        />

        <ProductTabs />

        <div class="flex flex-wrap items-center gap-3">
            <div class="relative max-w-sm flex-1">
                <Search
                    class="absolute top-1/2 left-2 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input v-model="q" placeholder="Buscar..." class="pl-8" />
            </div>
            <select
                v-model="status"
                class="h-9 rounded-md border bg-card px-3 text-sm"
            >
                <option value="">Todas</option>
                <option value="1">Activas</option>
                <option value="0">Inactivas</option>
            </select>
            <Button as-child class="ml-auto rounded-md">
                <Link href="/admin/categories/create">
                    <Plus class="size-4" /> Nueva categoría
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
                        <th class="px-3 py-2">Padre</th>
                        <th class="w-20 px-3 py-2">Orden</th>
                        <th class="w-24 px-3 py-2">Estado</th>
                        <th class="w-32 px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr
                        v-for="c in categories.data"
                        :key="c.id"
                        class="hover:bg-muted/30"
                    >
                        <td class="px-3 py-2">
                            <div class="font-medium">{{ c.name }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ c.slug }}
                            </div>
                        </td>
                        <td class="px-3 py-2 text-muted-foreground">
                            {{ c.parent?.name ?? '—' }}
                        </td>
                        <td class="px-3 py-2">{{ c.sort_order }}</td>
                        <td class="px-3 py-2">
                            <Badge
                                :variant="c.is_active ? 'default' : 'secondary'"
                            >
                                {{ c.is_active ? 'Activa' : 'Inactiva' }}
                            </Badge>
                        </td>
                        <td class="px-3 py-2 text-right">
                            <Button variant="ghost" size="icon-sm" as-child>
                                <Link :href="`/admin/categories/${c.id}/edit`">
                                    <Pencil class="size-4" />
                                </Link>
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                @click="destroy(c)"
                            >
                                <Trash2 class="size-4 text-destructive" />
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="!categories.data.length">
                        <td
                            colspan="5"
                            class="px-3 py-6 text-center text-muted-foreground"
                        >
                            Sin resultados.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination
            :links="categories.links"
            :from="categories.from"
            :to="categories.to"
            :total="categories.total"
        />
    </div>
</template>
