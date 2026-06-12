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
            { title: 'Marcas', href: '/admin/brands' },
        ],
    }),
});

type Brand = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    logo: string | null;
    website: string | null;
    is_active: boolean;
};

const props = defineProps<{ brand: Brand | null }>();
const isEdit = !!props.brand;

const form = useForm({
    name: props.brand?.name ?? '',
    slug: props.brand?.slug ?? '',
    description: props.brand?.description ?? '',
    logo: props.brand?.logo ?? '',
    website: props.brand?.website ?? '',
    is_active: props.brand?.is_active ?? true,
});

const submit = () => {
    if (isEdit) {
        form.patch(`/admin/brands/${props.brand!.id}`);
    } else {
        form.post('/admin/brands');
    }
};
</script>

<template>
    <Head :title="isEdit ? 'Editar marca' : 'Nueva marca'" />

    <div class="mx-auto max-w-5xl space-y-3 p-3 md:p-4">
        <div class="flex items-center gap-2">
            <Button variant="ghost" size="icon-sm" as-child>
                <Link href="/admin/brands"><ArrowLeft class="size-4" /></Link>
            </Button>
            <h1 class="text-xl font-semibold">
                {{ isEdit ? 'Editar marca' : 'Nueva marca' }}
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
                            <Label for="slug">
                                URL amigable
                                <span class="text-muted-foreground">
                                    (opcional)
                                </span>
                            </Label>
                            <Input
                                id="slug"
                                v-model="form.slug"
                                placeholder="se-genera-automaticamente"
                            />
                            <p class="text-xs text-muted-foreground">
                                Déjalo vacío para generarla desde el nombre.
                            </p>
                            <p
                                v-if="form.errors.slug"
                                class="text-xs text-destructive"
                            >
                                {{ form.errors.slug }}
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label for="website">Sitio web</Label>
                            <Input
                                id="website"
                                type="url"
                                v-model="form.website"
                                placeholder="https://..."
                            />
                            <p
                                v-if="form.errors.website"
                                class="text-xs text-destructive"
                            >
                                {{ form.errors.website }}
                            </p>
                        </div>
                        <div class="hidden items-end gap-2 pb-2 md:flex">
                            <Checkbox
                                id="is_active_desktop"
                                v-model="form.is_active"
                            />
                            <Label for="is_active_desktop">Activa</Label>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="description">Descripción</Label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="4"
                            class="min-h-28 w-full rounded-md border border-input bg-card px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        ></textarea>
                    </div>
                    <div class="flex items-center gap-2 md:hidden">
                        <Checkbox
                            id="is_active_mobile"
                            v-model="form.is_active"
                        />
                        <Label for="is_active_mobile">Activa</Label>
                    </div>
                    <div
                        class="flex justify-end gap-2 border-t border-border pt-4"
                    >
                        <Button variant="outline" as-child>
                            <Link href="/admin/brands">Cancelar</Link>
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
