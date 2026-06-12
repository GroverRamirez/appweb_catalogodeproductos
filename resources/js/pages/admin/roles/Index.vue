<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Lock,
    Pencil,
    Plus,
    Search,
    ShieldCheck,
    Trash2,
} from 'lucide-vue-next';
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
            { title: 'Roles', href: '/admin/roles' },
        ],
    }),
});

type Row = {
    id: number;
    name: string;
    permissions_count: number;
    users_count: number;
    is_protected: boolean;
};

const props = defineProps<{
    roles: {
        data: Row[];
        links: { url: string | null; label: string; active: boolean }[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: { q?: string };
}>();

const q = ref(props.filters.q ?? '');

let timer: ReturnType<typeof setTimeout> | null = null;
watch(q, () => {
    if (timer) {
        clearTimeout(timer);
    }

    timer = setTimeout(() => {
        router.get(
            '/admin/roles',
            { q: q.value },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

const destroy = (r: Row) => {
    if (r.is_protected || r.users_count > 0) {
        return;
    }

    if (!confirm(`¿Eliminar el rol "${r.name}"?`)) {
        return;
    }

    router.delete(`/admin/roles/${r.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Roles" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            :icon="ShieldCheck"
            eyebrow="Seguridad"
            title="Roles y permisos"
            :description="`${roles.total} roles definidos.`"
        >
            <template #actions>
                <Button as-child class="rounded-md">
                    <Link href="/admin/roles/create">
                        <Plus class="size-4" /> Nuevo rol
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="flex flex-wrap items-center gap-3">
            <div class="relative max-w-sm flex-1">
                <Search
                    class="absolute top-1/2 left-2 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input v-model="q" placeholder="Buscar rol..." class="pl-8" />
            </div>
        </div>

        <div class="overflow-x-auto rounded-md border">
            <table class="w-full text-sm">
                <thead
                    class="bg-muted/50 text-left text-xs text-muted-foreground uppercase"
                >
                    <tr>
                        <th class="px-3 py-2">Rol</th>
                        <th class="w-32 px-3 py-2">Permisos</th>
                        <th class="w-32 px-3 py-2">Usuarios</th>
                        <th class="w-32 px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr
                        v-for="r in roles.data"
                        :key="r.id"
                        class="hover:bg-muted/30"
                    >
                        <td class="px-3 py-2 font-medium">
                            <span class="inline-flex items-center gap-1.5">
                                {{ r.name }}
                                <Badge v-if="r.is_protected" variant="outline">
                                    <Lock class="size-3" /> sistema
                                </Badge>
                            </span>
                        </td>
                        <td class="px-3 py-2 text-muted-foreground">
                            {{ r.permissions_count }}
                        </td>
                        <td class="px-3 py-2 text-muted-foreground">
                            {{ r.users_count }}
                        </td>
                        <td class="px-3 py-2 text-right">
                            <Button variant="ghost" size="icon-sm" as-child>
                                <Link :href="`/admin/roles/${r.id}/edit`">
                                    <Pencil class="size-4" />
                                </Link>
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                :disabled="r.is_protected || r.users_count > 0"
                                :title="
                                    r.is_protected
                                        ? 'Rol del sistema'
                                        : r.users_count > 0
                                          ? 'Tiene usuarios asignados'
                                          : 'Eliminar'
                                "
                                @click="destroy(r)"
                            >
                                <Trash2 class="size-4 text-destructive" />
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="!roles.data.length">
                        <td
                            colspan="4"
                            class="px-3 py-6 text-center text-muted-foreground"
                        >
                            Sin roles.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination
            :links="roles.links"
            :from="roles.from"
            :to="roles.to"
            :total="roles.total"
        />
    </div>
</template>
