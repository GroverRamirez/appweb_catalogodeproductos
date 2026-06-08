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
            { title: 'Categorías', href: '/admin/categories' },
        ],
    }),
});

type Category = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    parent_id: number | null;
    sort_order: number;
    is_active: boolean;
};

const props = defineProps<{
    category: Category | null;
    parents: { id: number; name: string }[];
}>();

const isEdit = !!props.category;

const form = useForm({
    name: props.category?.name ?? '',
    slug: props.category?.slug ?? '',
    description: props.category?.description ?? '',
    parent_id: props.category?.parent_id ?? null,
    sort_order: props.category?.sort_order ?? 0,
    is_active: props.category?.is_active ?? true,
});

const submit = () => {
    if (isEdit) {
        form.patch(`/admin/categories/${props.category!.id}`);
    } else {
        form.post('/admin/categories');
    }
};
</script>

<template>
    <Head :title="isEdit ? 'Editar categoría' : 'Nueva categoría'" />

    <div class="mx-auto max-w-2xl space-y-4 p-4 md:p-6">
        <div class="flex items-center gap-2">
            <Button variant="ghost" size="icon-sm" as-child>
                <Link href="/admin/categories"
                    ><ArrowLeft class="size-4"
                /></Link>
            </Button>
            <h1 class="text-2xl font-semibold">
                {{ isEdit ? 'Editar categoría' : 'Nueva categoría' }}
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
                        <Input
                            id="slug"
                            v-model="form.slug"
                            placeholder="Se genera automáticamente"
                        />
                        <p
                            v-if="form.errors.slug"
                            class="mt-1 text-xs text-destructive"
                        >
                            {{ form.errors.slug }}
                        </p>
                    </div>

                    <div>
                        <Label for="parent_id">Categoría padre</Label>
                        <select
                            id="parent_id"
                            v-model="form.parent_id"
                            class="h-9 w-full rounded-md border bg-background px-3 text-sm"
                        >
                            <option :value="null">— Categoría raíz —</option>
                            <option
                                v-for="p in parents"
                                :key="p.id"
                                :value="p.id"
                            >
                                {{ p.name }}
                            </option>
                        </select>
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

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <Label for="sort_order">Orden</Label>
                            <Input
                                id="sort_order"
                                type="number"
                                min="0"
                                v-model.number="form.sort_order"
                            />
                        </div>
                        <div class="flex items-end gap-2 pb-1">
                            <Checkbox id="is_active" v-model="form.is_active" />
                            <Label for="is_active">Activa</Label>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <Button variant="outline" as-child>
                            <Link href="/admin/categories">Cancelar</Link>
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
