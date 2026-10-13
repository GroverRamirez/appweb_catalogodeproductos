<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Pencil, Plus, Power, PowerOff, Search, Users } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissions } from '@/composables/usePermissions';

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Usuarios', href: '/admin/users' },
        ],
    }),
});

type Row = {
    id: number;
    name: string;
    email: string;
    created_at: string;
    email_verified_at: string | null;
    is_active: boolean;
    roles: string[];
};

const props = defineProps<{
    users: {
        data: Row[];
        links: { url: string | null; label: string; active: boolean }[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: { q?: string; role?: string };
    roles: string[];
}>();

const q = ref(props.filters.q ?? '');
const role = ref(props.filters.role ?? '');

let timer: ReturnType<typeof setTimeout> | null = null;
watch([q, role], () => {
    if (timer) {
        clearTimeout(timer);
    }

    timer = setTimeout(() => {
        router.get(
            '/admin/users',
            { q: q.value, role: role.value },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

const page = usePage();
const { can } = usePermissions();
const currentUserId = computed(() => page.props.auth?.user?.id);

const toggleActive = (u: Row) => {
    if (
        u.is_active &&
        !confirm(
            `¿Desactivar a "${u.name}"? No podrá iniciar sesión, pero sus ventas y compras se conservan.`,
        )
    ) {
        return;
    }

    router.patch(`/admin/users/${u.id}/activo`, {}, { preserveScroll: true });
};

const roleVariant = (r: string) =>
    r === 'propietario'
        ? 'destructive'
        : r === 'encargado' || r === 'vendedor'
          ? 'default'
          : 'secondary';
</script>

<template>
    <Head title="Usuarios" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            :icon="Users"
            eyebrow="Equipo"
            title="Usuarios"
            :description="`${users.total} usuarios registrados.`"
        >
            <template #actions>
                <Button as-child class="rounded-md">
                    <Link href="/admin/users/create">
                        <Plus class="size-4" /> Nuevo usuario
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
                    placeholder="Buscar por nombre o correo..."
                    class="pl-8"
                />
            </div>
            <select
                v-model="role"
                class="h-9 rounded-md border bg-card px-3 text-sm"
            >
                <option value="">Todos los roles</option>
                <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
            </select>
        </div>

        <div class="overflow-x-auto rounded-md border bg-card">
            <table class="w-full text-sm">
                <thead
                    class="bg-muted/50 text-left text-xs text-muted-foreground uppercase"
                >
                    <tr>
                        <th class="px-3 py-2">Nombre</th>
                        <th class="px-3 py-2">Correo</th>
                        <th class="px-3 py-2">Roles</th>
                        <th class="w-28 px-3 py-2">Estado</th>
                        <th class="w-32 px-3 py-2">Registro</th>
                        <th class="w-32 px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr
                        v-for="u in users.data"
                        :key="u.id"
                        class="hover:bg-muted/30"
                    >
                        <td class="px-3 py-2 font-medium">{{ u.name }}</td>
                        <td class="px-3 py-2">
                            {{ u.email }}
                            <Badge
                                v-if="!u.email_verified_at"
                                variant="outline"
                                class="ml-1"
                            >
                                no verif.
                            </Badge>
                        </td>
                        <td class="px-3 py-2">
                            <Badge
                                v-for="r in u.roles"
                                :key="r"
                                :variant="roleVariant(r) as any"
                                class="mr-1"
                            >
                                {{ r }}
                            </Badge>
                            <span
                                v-if="!u.roles.length"
                                class="text-xs text-muted-foreground"
                            >
                                — sin rol —
                            </span>
                        </td>
                        <td class="px-3 py-2">
                            <Badge
                                :variant="u.is_active ? 'default' : 'secondary'"
                            >
                                {{ u.is_active ? 'Activo' : 'Inactivo' }}
                            </Badge>
                        </td>
                        <td class="px-3 py-2 text-xs text-muted-foreground">
                            {{ u.created_at?.substring(0, 10) }}
                        </td>
                        <td class="px-3 py-2 text-right">
                            <Button variant="ghost" size="icon-sm" as-child>
                                <Link :href="`/admin/users/${u.id}/edit`">
                                    <Pencil class="size-4" />
                                </Link>
                            </Button>
                            <Button
                                v-if="
                                    can('users.update') &&
                                    u.id !== currentUserId
                                "
                                variant="ghost"
                                size="icon-sm"
                                :title="
                                    u.is_active
                                        ? 'Desactivar usuario'
                                        : 'Activar usuario'
                                "
                                :aria-label="
                                    u.is_active
                                        ? 'Desactivar usuario'
                                        : 'Activar usuario'
                                "
                                @click="toggleActive(u)"
                            >
                                <PowerOff
                                    v-if="u.is_active"
                                    class="size-4 text-muted-foreground"
                                />
                                <Power v-else class="size-4 text-primary" />
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="!users.data.length">
                        <td
                            colspan="6"
                            class="px-3 py-6 text-center text-muted-foreground"
                        >
                            Sin usuarios.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination
            :links="users.links"
            :from="users.from"
            :to="users.to"
            :total="users.total"
        />
    </div>
</template>
