<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Tag, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Marcas', href: '/admin/brands' },
        ],
    }),
});

type Brand = {
    id: number;
    name: string;
    slug: string;
    website?: string | null;
    is_active: boolean;
};

const props = defineProps<{
    brands: {
        data: Brand[];
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
            '/admin/brands',
            { q: q.value, status: status.value },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

const destroy = (b: Brand) => {
    if (!confirm(`¿Eliminar la marca "${b.name}"?`)) {
return;
}

    router.delete(`/admin/brands/${b.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Marcas" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            :icon="Tag"
            eyebrow="Catálogo"
            title="Marcas"
            description="Marcas asociadas a los productos."
        >
            <template #actions>
                <Button as-child class="rounded-full gradient-brand glow-brand border-transparent text-white">
                    <Link href="/admin/brands/create">
                        <Plus class="size-4" /> Nueva marca
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="flex flex-wrap items-center gap-3">
            <div class="relative max-w-sm flex-1">
                <Search
                    class="absolute left-2 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input v-model="q" placeholder="Buscar..." class="pl-8" />
            </div>
            <select
                v-model="status"
                class="h-9 rounded-md border bg-background px-3 text-sm"
            >
                <option value="">Todas</option>
                <option value="1">Activas</option>
                <option value="0">Inactivas</option>
            </select>
        </div>

        <div class="overflow-x-auto rounded-md border">
            <table class="w-full text-sm">
                <thead
                    class="bg-muted/50 text-left text-xs uppercase text-muted-foreground"
                >
                    <tr>
                        <th class="px-3 py-2">Nombre</th>
                        <th class="px-3 py-2">Sitio</th>
                        <th class="px-3 py-2 w-24">Estado</th>
                        <th class="px-3 py-2 w-32"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="b in brands.data" :key="b.id" class="hover:bg-muted/30">
                        <td class="px-3 py-2">
                            <div class="font-medium">{{ b.name }}</div>
                            <div class="text-xs text-muted-foreground">{{ b.slug }}</div>
                        </td>
                        <td class="px-3 py-2 text-muted-foreground">
                            <a
                                v-if="b.website"
                                :href="b.website"
                                target="_blank"
                                class="hover:underline"
                                >{{ b.website }}</a
                            >
                            <span v-else>—</span>
                        </td>
                        <td class="px-3 py-2">
                            <Badge :variant="b.is_active ? 'default' : 'secondary'">
                                {{ b.is_active ? 'Activa' : 'Inactiva' }}
                            </Badge>
                        </td>
                        <td class="px-3 py-2 text-right">
                            <Button variant="ghost" size="icon-sm" as-child>
                                <Link :href="`/admin/brands/${b.id}/edit`">
                                    <Pencil class="size-4" />
                                </Link>
                            </Button>
                            <Button variant="ghost" size="icon-sm" @click="destroy(b)">
                                <Trash2 class="size-4 text-destructive" />
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="!brands.data.length">
                        <td colspan="4" class="px-3 py-6 text-center text-muted-foreground">
                            Sin resultados.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination
            :links="brands.links"
            :from="brands.from"
            :to="brands.to"
            :total="brands.total"
        />
    </div>
</template>
