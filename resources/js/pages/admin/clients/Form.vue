<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Clientes', href: '/admin/clients' },
        ],
    }),
});

type Client = {
    id: number;
    name: string;
    phone: string | null;
    id_card: string | null;
    email: string | null;
    address: string | null;
    notes: string | null;
    is_active: boolean;
};

const props = defineProps<{ client: Client | null }>();

const isEdit = props.client !== null;

const form = useForm({
    name: props.client?.name ?? '',
    phone: props.client?.phone ?? '',
    id_card: props.client?.id_card ?? '',
    email: props.client?.email ?? '',
    address: props.client?.address ?? '',
    notes: props.client?.notes ?? '',
    is_active: props.client?.is_active ?? true,
});

const submit = () => {
    if (isEdit) {
        form.put(`/admin/clients/${props.client!.id}`);

        return;
    }

    form.post('/admin/clients');
};
</script>

<template>
    <Head :title="isEdit ? 'Editar cliente' : 'Nuevo cliente'" />

    <div class="mx-auto max-w-2xl space-y-4 p-4 md:p-6">
        <div class="flex items-center gap-2">
            <Button variant="ghost" size="icon-sm" as-child>
                <Link href="/admin/clients"><ArrowLeft class="size-4" /></Link>
            </Button>
            <h1 class="text-xl font-semibold">
                {{ isEdit ? 'Editar cliente' : 'Nuevo cliente' }}
            </h1>
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <Card class="gap-3 py-4">
                <CardHeader class="pb-0">
                    <CardTitle>Datos del cliente</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5 sm:col-span-2">
                        <Label for="name">Nombre</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            :aria-invalid="!!form.errors.name"
                        />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="phone">Teléfono</Label>
                        <Input
                            id="phone"
                            v-model="form.phone"
                            placeholder="Ej. 70000000"
                            :aria-invalid="!!form.errors.phone"
                        />
                        <InputError :message="form.errors.phone" />
                        <p class="text-xs text-muted-foreground">
                            Es lo que identifica al cliente: si repetís uno ya
                            registrado, el sistema avisa en vez de duplicarlo.
                        </p>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="id_card">
                            Carnet de identidad
                            <span class="text-muted-foreground">
                                (opcional)
                            </span>
                        </Label>
                        <Input
                            id="id_card"
                            v-model="form.id_card"
                            :aria-invalid="!!form.errors.id_card"
                        />
                        <InputError :message="form.errors.id_card" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="email">
                            Email
                            <span class="text-muted-foreground">
                                (opcional)
                            </span>
                        </Label>
                        <Input
                            id="email"
                            v-model="form.email"
                            type="email"
                            :aria-invalid="!!form.errors.email"
                        />
                        <InputError :message="form.errors.email" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="address">
                            Dirección
                            <span class="text-muted-foreground">
                                (opcional)
                            </span>
                        </Label>
                        <Input
                            id="address"
                            v-model="form.address"
                            :aria-invalid="!!form.errors.address"
                        />
                        <InputError :message="form.errors.address" />
                    </div>
                    <div class="space-y-1.5 sm:col-span-2">
                        <Label for="notes">Notas</Label>
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            rows="3"
                            placeholder="Preferencias, acuerdos, lo que convenga recordar"
                            class="w-full rounded-md border border-input bg-card px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        ></textarea>
                        <InputError :message="form.errors.notes" />
                    </div>
                    <label
                        class="flex items-center gap-2 text-sm sm:col-span-2"
                    >
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="size-4 rounded border-input"
                        />
                        Cliente activo
                    </label>
                </CardContent>
            </Card>

            <div class="flex justify-end gap-2">
                <Button variant="outline" as-child>
                    <Link href="/admin/clients">Cancelar</Link>
                </Button>
                <Button type="submit" :disabled="form.processing">
                    {{ isEdit ? 'Guardar cambios' : 'Registrar cliente' }}
                </Button>
            </div>
        </form>
    </div>
</template>
