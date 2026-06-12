<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Lock } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Roles', href: '/admin/roles' },
        ],
    }),
});

type Role = {
    id: number;
    name: string;
    permissions: string[];
    is_protected: boolean;
};

const props = defineProps<{
    role: Role | null;
    permissionGroups: Record<string, string[]>;
}>();

const isEdit = !!props.role;
const isProtected = props.role?.is_protected ?? false;

const moduleLabels: Record<string, string> = {
    categories: 'Categorías',
    brands: 'Marcas',
    products: 'Productos',
    banners: 'Banners',
    coupons: 'Cupones',
    inquiries: 'Consultas',
    inventory: 'Inventario',
    users: 'Usuarios',
    roles: 'Roles',
    settings: 'Configuración',
    reports: 'Reportes',
};

const actionLabels: Record<string, string> = {
    view: 'Ver',
    create: 'Crear',
    update: 'Editar',
    delete: 'Eliminar',
    adjust: 'Ajustar',
};

const permissionLabel = (permission: string): string => {
    const action = permission.split('.')[1] ?? permission;

    return actionLabels[action] ?? action;
};

const form = useForm<{ name: string; permissions: string[] }>({
    name: props.role?.name ?? '',
    permissions: props.role?.permissions ? [...props.role.permissions] : [],
});

const groups = computed(() => Object.entries(props.permissionGroups));

const groupAllSelected = (perms: string[]): boolean =>
    perms.every((p) => form.permissions.includes(p));

const toggleGroup = (perms: string[]) => {
    if (groupAllSelected(perms)) {
        form.permissions = form.permissions.filter((p) => !perms.includes(p));
    } else {
        const set = new Set([...form.permissions, ...perms]);
        form.permissions = [...set];
    }
};

const submit = () => {
    if (isEdit) {
        form.patch(`/admin/roles/${props.role!.id}`);
    } else {
        form.post('/admin/roles');
    }
};
</script>

<template>
    <Head :title="isEdit ? 'Editar rol' : 'Nuevo rol'" />

    <div class="mx-auto max-w-5xl space-y-3 p-3 md:p-4">
        <div class="flex items-center gap-2">
            <Button variant="ghost" size="icon-sm" as-child>
                <Link href="/admin/roles"><ArrowLeft class="size-4" /></Link>
            </Button>
            <h1 class="text-xl font-semibold">
                {{ isEdit ? 'Editar rol' : 'Nuevo rol' }}
            </h1>
            <Badge v-if="isProtected" variant="outline">
                <Lock class="size-3" /> rol del sistema
            </Badge>
        </div>

        <Card class="py-5">
            <CardContent class="space-y-4 pt-0">
                <form class="space-y-4" @submit.prevent="submit">
                    <div>
                        <Label for="name">Nombre del rol</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            :disabled="isProtected"
                            required
                        />
                        <p
                            v-if="isProtected"
                            class="mt-1 text-xs text-muted-foreground"
                        >
                            Los roles del sistema no pueden renombrarse, pero sí
                            ajustar sus permisos.
                        </p>
                        <p
                            v-if="form.errors.name"
                            class="mt-1 text-xs text-destructive"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div class="space-y-4">
                        <Label>Permisos</Label>
                        <p
                            v-if="form.errors.permissions"
                            class="text-xs text-destructive"
                        >
                            {{ form.errors.permissions }}
                        </p>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div
                                v-for="[module, perms] in groups"
                                :key="module"
                                class="rounded-md border p-3"
                            >
                                <div
                                    class="mb-2 flex items-center justify-between"
                                >
                                    <span class="text-sm font-semibold">
                                        {{ moduleLabels[module] ?? module }}
                                    </span>
                                    <button
                                        type="button"
                                        class="text-xs text-primary hover:underline"
                                        @click="toggleGroup(perms)"
                                    >
                                        {{
                                            groupAllSelected(perms)
                                                ? 'Quitar todo'
                                                : 'Seleccionar todo'
                                        }}
                                    </button>
                                </div>
                                <div class="grid grid-cols-2 gap-x-3 gap-y-1.5">
                                    <label
                                        v-for="perm in perms"
                                        :key="perm"
                                        class="flex items-center gap-2 text-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            :value="perm"
                                            v-model="form.permissions"
                                            class="size-4 rounded border-input accent-primary"
                                        />
                                        {{ permissionLabel(perm) }}
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <Button variant="outline" as-child>
                            <Link href="/admin/roles">Cancelar</Link>
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ isEdit ? 'Guardar' : 'Crear' }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
