<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Banners publicitarios', href: '/admin/banners' },
        ],
    }),
});

type Banner = {
    id: number;
    title: string | null;
    subtitle: string | null;
    image: string;
    link: string | null;
    cta_text: string | null;
    sort_order: number;
    is_active: boolean;
    starts_at: string | null;
    ends_at: string | null;
};

const props = defineProps<{ banner: Banner | null }>();
const isEdit = !!props.banner;

const form = useForm({
    title: props.banner?.title ?? '',
    subtitle: props.banner?.subtitle ?? '',
    image_url:
        props.banner?.image && props.banner.image.startsWith('http')
            ? props.banner.image
            : '',
    image_file: null as File | null,
    link: props.banner?.link ?? '',
    cta_text: props.banner?.cta_text ?? '',
    sort_order: props.banner?.sort_order ?? 0,
    is_active: props.banner?.is_active ?? true,
    starts_at: props.banner?.starts_at?.substring(0, 10) ?? '',
    ends_at: props.banner?.ends_at?.substring(0, 10) ?? '',
    _method: isEdit ? 'patch' : 'post',
});

const onFile = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0];
    form.image_file = file ?? null;

    if (file) {
        form.image_url = '';
    }
};

const submit = () => {
    const url = isEdit
        ? `/admin/banners/${props.banner!.id}`
        : '/admin/banners';
    form.post(url, {
        forceFormData: true,
        onSuccess: () => router.visit('/admin/banners'),
    });
};

const currentImg = ref(
    props.banner?.image
        ? props.banner.image.startsWith('http')
            ? props.banner.image
            : `/storage/${props.banner.image}`
        : null,
);
</script>

<template>
    <Head :title="isEdit ? 'Editar banner' : 'Nuevo banner'" />

    <div class="mx-auto max-w-4xl space-y-3 p-3 md:p-4">
        <div class="flex items-center gap-2">
            <Button variant="ghost" size="icon-sm" as-child>
                <Link href="/admin/banners"><ArrowLeft class="size-4" /></Link>
            </Button>
            <h1 class="text-xl font-semibold">
                {{ isEdit ? 'Editar banner' : 'Nuevo banner' }}
            </h1>
        </div>

        <form class="grid gap-3 lg:grid-cols-3" @submit.prevent="submit">
            <div class="space-y-3 lg:col-span-2">
                <Card class="gap-3 py-4">
                    <CardHeader class="pb-0"
                        ><CardTitle>Contenido</CardTitle></CardHeader
                    >
                    <CardContent class="space-y-3">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label for="title">Título</Label>
                                <Input id="title" v-model="form.title" />
                            </div>
                            <div class="space-y-1.5">
                                <Label for="cta_text">Texto del botón</Label>
                                <Input
                                    id="cta_text"
                                    v-model="form.cta_text"
                                    placeholder="Ver más"
                                />
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="subtitle">Subtítulo</Label>
                            <Input id="subtitle" v-model="form.subtitle" />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="link">Enlace (URL o ruta)</Label>
                            <Input
                                id="link"
                                v-model="form.link"
                                placeholder="/catalogo?category=tecnologia"
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card class="gap-3 py-4">
                    <CardHeader class="pb-0"
                        ><CardTitle>Imagen</CardTitle></CardHeader
                    >
                    <CardContent class="space-y-3">
                        <img
                            v-if="currentImg"
                            :src="currentImg"
                            alt="actual"
                            class="aspect-[16/5] w-full rounded-md object-cover"
                        />
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label for="image_file">Subir imagen</Label>
                                <input
                                    id="image_file"
                                    type="file"
                                    accept="image/*"
                                    @change="onFile"
                                    class="block w-full text-sm"
                                />
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Recomendado 1600×600. Máx. 4 MB.
                                </p>
                            </div>
                            <div class="space-y-1.5">
                                <Label for="image_url">…o URL externa</Label>
                                <Input
                                    id="image_url"
                                    type="url"
                                    v-model="form.image_url"
                                    placeholder="https://..."
                                />
                            </div>
                        </div>
                        <p
                            v-if="form.errors.image_file"
                            class="text-xs text-destructive"
                        >
                            {{ form.errors.image_file }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-3">
                <Card class="gap-3 py-4">
                    <CardHeader class="pb-0"
                        ><CardTitle>Visibilidad</CardTitle></CardHeader
                    >
                    <CardContent class="space-y-3">
                        <div class="space-y-1.5">
                            <Label for="sort_order">Orden</Label>
                            <Input
                                id="sort_order"
                                type="number"
                                min="0"
                                v-model.number="form.sort_order"
                            />
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label for="starts_at">Desde</Label>
                                <Input
                                    id="starts_at"
                                    type="date"
                                    v-model="form.starts_at"
                                />
                            </div>
                            <div class="space-y-1.5">
                                <Label for="ends_at">Hasta</Label>
                                <Input
                                    id="ends_at"
                                    type="date"
                                    v-model="form.ends_at"
                                />
                            </div>
                        </div>
                        <div class="flex items-center gap-2 pt-1">
                            <Checkbox id="is_active" v-model="form.is_active" />
                            <Label for="is_active">Activo</Label>
                        </div>
                    </CardContent>
                </Card>

                <div class="flex gap-2">
                    <Button variant="outline" as-child class="flex-1">
                        <Link href="/admin/banners">Cancelar</Link>
                    </Button>
                    <Button
                        type="submit"
                        :disabled="form.processing"
                        class="flex-1"
                    >
                        {{ isEdit ? 'Guardar' : 'Crear' }}
                    </Button>
                </div>
            </div>
        </form>
    </div>
</template>
