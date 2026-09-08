<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Image, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import PageHeader from '@/components/admin/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Banners publicitarios', href: '/admin/banners' },
        ],
    }),
});

type Banner = {
    id: number;
    title: string | null;
    subtitle: string | null;
    image: string;
    sort_order: number;
    is_active: boolean;
    starts_at: string | null;
    ends_at: string | null;
};

defineProps<{
    banners: {
        data: Banner[];
        links: { url: string | null; label: string; active: boolean }[];
        from: number | null;
        to: number | null;
        total: number;
    };
}>();

const imageUrl = (path: string) =>
    path.startsWith('http') ? path : `/storage/${path}`;

const destroy = (b: Banner) => {
    if (!confirm(`¿Eliminar el banner "${b.title ?? 'sin título'}"?`)) {
        return;
    }

    router.delete(`/admin/banners/${b.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Banners publicitarios" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            :icon="Image"
            eyebrow="Catálogo"
            title="Banners publicitarios"
            :description="`Aparecen en el carrusel del home. ${banners.total} total.`"
        >
            <template #actions>
                <Button as-child class="rounded-md">
                    <Link href="/admin/banners/create">
                        <Plus class="size-4" /> Nuevo banner
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="overflow-x-auto rounded-md border bg-card">
            <table class="w-full text-sm">
                <thead
                    class="bg-muted/50 text-left text-xs text-muted-foreground uppercase"
                >
                    <tr>
                        <th class="w-32 px-3 py-2">Imagen</th>
                        <th class="px-3 py-2">Título</th>
                        <th class="w-20 px-3 py-2">Orden</th>
                        <th class="w-24 px-3 py-2">Estado</th>
                        <th class="w-40 px-3 py-2">Vigencia</th>
                        <th class="w-32 px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr
                        v-for="b in banners.data"
                        :key="b.id"
                        class="hover:bg-muted/30"
                    >
                        <td class="px-3 py-2">
                            <img
                                :src="imageUrl(b.image)"
                                :alt="b.title ?? ''"
                                class="h-12 w-28 rounded object-cover"
                            />
                        </td>
                        <td class="px-3 py-2">
                            <div class="font-medium">{{ b.title ?? '—' }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ b.subtitle }}
                            </div>
                        </td>
                        <td class="px-3 py-2">{{ b.sort_order }}</td>
                        <td class="px-3 py-2">
                            <Badge
                                :variant="b.is_active ? 'default' : 'secondary'"
                            >
                                {{ b.is_active ? 'Activo' : 'Inactivo' }}
                            </Badge>
                        </td>
                        <td class="px-3 py-2 text-xs text-muted-foreground">
                            <div v-if="b.starts_at || b.ends_at">
                                <div v-if="b.starts_at">
                                    Desde: {{ b.starts_at.substring(0, 10) }}
                                </div>
                                <div v-if="b.ends_at">
                                    Hasta: {{ b.ends_at.substring(0, 10) }}
                                </div>
                            </div>
                            <span v-else>Siempre</span>
                        </td>
                        <td class="px-3 py-2 text-right">
                            <Button variant="ghost" size="icon-sm" as-child>
                                <Link :href="`/admin/banners/${b.id}/edit`">
                                    <Pencil class="size-4" />
                                </Link>
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                @click="destroy(b)"
                            >
                                <Trash2 class="size-4 text-destructive" />
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="!banners.data.length">
                        <td
                            colspan="6"
                            class="px-3 py-6 text-center text-muted-foreground"
                        >
                            Sin banners todavía. ¡Crea el primero!
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination
            :links="banners.links"
            :from="banners.from"
            :to="banners.to"
            :total="banners.total"
        />
    </div>
</template>
