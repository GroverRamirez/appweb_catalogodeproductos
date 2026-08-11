<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

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
    address: string | null;
    notes: string | null;
    is_active: boolean;
};

const props = defineProps<{ supplier: Supplier | null }>();
const isEdit = !!props.supplier;

const form = useForm({
    name: props.supplier?.name ?? '',
    contact_name: props.supplier?.contact_name ?? '',
    phone: props.supplier?.phone ?? '',
    email: props.supplier?.email ?? '',
    address: props.supplier?.address ?? '',
    notes: props.supplier?.notes ?? '',
    is_active: props.supplier?.is_active ?? true,
});

const submit = () => {
    if (isEdit) {
        form.patch(`/admin/suppliers/${props.supplier!.id}`);
    } else {
        form.post('/admin/suppliers');
    }
};
</script>

<template>
    <Head :title="isEdit ? 'Editar proveedor' : 'Nuevo proveedor'" />

    <div class="mx-auto max-w-5xl space-y-3 p-3 md:p-4">
        <div class="flex items-center gap-2">
            <Button variant="ghost" size="icon-sm" as-child>
                <Link href="/admin/suppliers"
                    ><ArrowLeft class="size-4"
                /></Link>
            </Button>
            <h1 class="text-xl font-semibold">
                {{ isEdit ? 'Editar proveedor' : 'Nuevo proveedor' }}
            </h1>
        </div>

        <Card class="max-w-4xl py-5">
            <CardContent class="pt-0">
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label for="name">Nombre</Label>
                            <Input id="name" v-model="form.name" required />
                            <p
                                v-if="form.errors.name"
                                class="text-xs text-destructive"
                            >
                                {{ form.errors.name }}
                            </p>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="contact_name">
                                Persona de contacto
                                <span class="text-muted-foreground">
                                    (opcional)
                                </span>
                            </Label>
                            <Input
                                id="contact_name"
                                v-model="form.contact_name"
                            />
                            <p
                                v-if="form.errors.contact_name"
                                class="text-xs text-destructive"
                            >
                                {{ form.errors.contact_name }}
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label for="phone">Teléfono</Label>
                            <Input id="phone" v-model="form.phone" />
                            <p
                                v-if="form.errors.phone"
                                class="text-xs text-destructive"
                            >
                                {{ form.errors.phone }}
                            </p>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="email">Email</Label>
                            <Input
                                id="email"
                                type="email"
                                v-model="form.email"
                            />
                            <p
                                v-if="form.errors.email"
                                class="text-xs text-destructive"
                            >
                                {{ form.errors.email }}
                            </p>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="address">Dirección</Label>
                        <textarea
                            id="address"
                            v-model="form.address"
                            rows="2"
                            class="w-full rounded-md border border-input bg-card px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        ></textarea>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="notes">Notas</Label>
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            rows="3"
                            class="w-full rounded-md border border-input bg-card px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        ></textarea>
                    </div>

                    <div class="flex items-center gap-2">
                        <Checkbox id="is_active" v-model="form.is_active" />
                        <Label for="is_active">Activo</Label>
                    </div>

                    <div
                        class="flex justify-end gap-2 border-t border-border pt-4"
                    >
                        <Button variant="outline" as-child>
                            <Link href="/admin/suppliers">Cancelar</Link>
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ isEdit ? 'Guardar cambios' : 'Crear' }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
