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

    <div class="mx-auto max-w-2xl space-y-4 p-4 md:p-6">
        <div class="flex items-center gap-2">
            <Button variant="ghost" size="icon-sm" as-child>
                <Link href="/admin/brands"><ArrowLeft class="size-4" /></Link>
            </Button>
            <h1 class="text-2xl font-semibold">
                {{ isEdit ? 'Editar marca' : 'Nueva marca' }}
            </h1>
        </div>

        <Card>
            <CardContent class="pt-6">
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
                        <Label for="slug"
                            >Slug
                            <span class="text-muted-foreground"
                                >(opcional)</span
                            ></Label
                        >
                        <Input id="slug" v-model="form.slug" />
                        <p
                            v-if="form.errors.slug"
                            class="mt-1 text-xs text-destructive"
                        >
                            {{ form.errors.slug }}
                        </p>
                    </div>
                    <div>
                        <Label for="website">Sitio web</Label>
                        <Input
                            id="website"
                            type="url"
                            v-model="form.website"
                            placeholder="https://..."
                        />
                        <p
                            v-if="form.errors.website"
                            class="mt-1 text-xs text-destructive"
                        >
                            {{ form.errors.website }}
                        </p>
                    </div>
                    <div>
                        <Label for="description">Descripción</Label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="3"
                            class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                        ></textarea>
                    </div>
                    <div class="flex items-center gap-2">
                        <Checkbox id="is_active" v-model="form.is_active" />
                        <Label for="is_active">Activa</Label>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
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
