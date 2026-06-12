<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Usuarios', href: '/admin/users' },
        ],
    }),
});

type User = {
    id: number;
    name: string;
    email: string;
    role: string | null;
};

const props = defineProps<{
    user: User | null;
    roles: string[];
}>();

const isEdit = !!props.user;

const form = useForm({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    password: '',
    role: props.user?.role ?? '',
});

const submit = () => {
    if (isEdit) {
        form.patch(`/admin/users/${props.user!.id}`);
    } else {
        form.post('/admin/users');
    }
};
</script>

<template>
    <Head :title="isEdit ? 'Editar usuario' : 'Nuevo usuario'" />

    <div class="mx-auto max-w-xl space-y-3 p-3 md:p-4">
        <div class="flex items-center gap-2">
            <Button variant="ghost" size="icon-sm" as-child>
                <Link href="/admin/users"><ArrowLeft class="size-4" /></Link>
            </Button>
            <h1 class="text-xl font-semibold">
                {{ isEdit ? 'Editar usuario' : 'Nuevo usuario' }}
            </h1>
        </div>

        <Card class="py-5">
            <CardContent class="pt-0">
                <form class="space-y-4" @submit.prevent="submit">
                    <div>
                        <Label for="name">Nombre</Label>
                        <Input id="name" v-model="form.name" required />
                        <p
                            v-if="form.errors.name"
                            class="mt-1 text-xs text-destructive"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div>
                        <Label for="email">Correo</Label>
                        <Input
                            id="email"
                            type="email"
                            v-model="form.email"
                            required
                        />
                        <p
                            v-if="form.errors.email"
                            class="mt-1 text-xs text-destructive"
                        >
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <div>
                        <Label for="password"
                            >Contraseña
                            <span v-if="isEdit" class="text-muted-foreground"
                                >(dejar vacío para no cambiar)</span
                            ></Label
                        >
                        <Input
                            id="password"
                            type="password"
                            v-model="form.password"
                            :required="!isEdit"
                            autocomplete="new-password"
                        />
                        <p
                            v-if="form.errors.password"
                            class="mt-1 text-xs text-destructive"
                        >
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <div>
                        <Label for="role">Rol</Label>
                        <select
                            id="role"
                            v-model="form.role"
                            class="h-9 w-full rounded-md border bg-background px-3 text-sm"
                        >
                            <option value="">— Sin rol —</option>
                            <option v-for="r in roles" :key="r" :value="r">
                                {{ r }}
                            </option>
                        </select>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <Button variant="outline" as-child>
                            <Link href="/admin/users">Cancelar</Link>
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
