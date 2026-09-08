<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Trash2, Truck } from 'lucide-vue-next';
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
            { title: 'Proveedores', href: '/admin/suppliers' },
        ],
    }),
});

type Supplier = {
    id: number;
    name: string;
    contact_name: string | null;
    phone: string | null;
    email: string | null;
    is_active: boolean;
};

const props = defineProps<{
    suppliers: {
        data: Supplier[];
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
            '/admin/suppliers',
            { q: q.value, status: status.value },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

const destroy = (s: Supplier) => {
    if (!confirm(`¿Eliminar el proveedor "${s.name}"?`)) {
        return;
    }

    router.delete(`/admin/suppliers/${s.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Proveedores" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            :icon="Truck"
            eyebrow="Inventario"
            title="Proveedores"
            description="A quién le comprás mercadería para tu catálogo."
        >
            <template #actions>
                <Button as-child class="rounded-md">
                    <Link href="/admin/suppliers/create">
                        <Plus class="size-4" /> Nuevo proveedor
                    </Link>
                </Button>
            </template>
        </PageHeader>

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
                <option value="">Todos</option>
                <option value="1">Activos</option>
                <option value="0">Inactivos</option>
            </select>
        </div>

        <div class="overflow-x-auto rounded-md border bg-card">
            <table class="w-full text-sm">
                <thead
                    class="bg-muted/50 text-left text-xs text-muted-foreground uppercase"
                >
                    <tr>
                        <th class="px-3 py-2">Nombre</th>
                        <th class="px-3 py-2">Contacto</th>
                        <th class="px-3 py-2">Teléfono</th>
                        <th class="w-24 px-3 py-2">Estado</th>
                        <th class="w-32 px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr
                        v-for="s in suppliers.data"
                        :key="s.id"
                        class="hover:bg-muted/30"
                    >
                        <td class="px-3 py-2">
                            <div class="font-medium">{{ s.name }}</div>
                            <div class="text-xs text-muted-foreground">
                                {{ s.email }}
                            </div>
                        </td>
                        <td class="px-3 py-2 text-muted-foreground">
                            {{ s.contact_name ?? '—' }}
                        </td>
                        <td class="px-3 py-2 text-muted-foreground">
                            {{ s.phone ?? '—' }}
                        </td>
                        <td class="px-3 py-2">
                            <Badge
                                :variant="s.is_active ? 'default' : 'secondary'"
                            >
                                {{ s.is_active ? 'Activo' : 'Inactivo' }}
                            </Badge>
                        </td>
                        <td class="px-3 py-2 text-right">
                            <Button variant="ghost" size="icon-sm" as-child>
                                <Link :href="`/admin/suppliers/${s.id}/edit`">
                                    <Pencil class="size-4" />
                                </Link>
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                @click="destroy(s)"
                            >
                                <Trash2 class="size-4 text-destructive" />
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="!suppliers.data.length">
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
            :links="suppliers.links"
            :from="suppliers.from"
            :to="suppliers.to"
            :total="suppliers.total"
        />
    </div>
</template>
