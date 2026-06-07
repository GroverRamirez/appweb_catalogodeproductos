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

    <div class="mx-auto max-w-3xl space-y-4 p-4 md:p-6">
        <div class="flex items-center gap-2">
            <Button variant="ghost" size="icon-sm" as-child>
                <Link href="/admin/banners"><ArrowLeft class="size-4" /></Link>
            </Button>
            <h1 class="text-2xl font-semibold">
                {{ isEdit ? 'Editar banner' : 'Nuevo banner' }}
            </h1>
        </div>

        <Card>
            <CardContent class="pt-6">
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label for="title">Título</Label>
                            <Input id="title" v-model="form.title" />
                        </div>
                        <div>
                            <Label for="cta_text">Texto del botón</Label>
                            <Input
                                id="cta_text"
                                v-model="form.cta_text"
                                placeholder="Ver más"
                            />
                        </div>
                    </div>

                    <div>
                        <Label for="subtitle">Subtítulo</Label>
                        <Input id="subtitle" v-model="form.subtitle" />
                    </div>

                    <div>
                        <Label for="link">Enlace (URL o ruta)</Label>
                        <Input
                            id="link"
                            v-model="form.link"
                            placeholder="/catalogo?category=tecnologia"
                        />
                    </div>
                </form>
            </CardContent>
        </Card>

        <Card>
            <CardHeader><CardTitle>Imagen</CardTitle></CardHeader>
            <CardContent class="space-y-4">
                <img
                    v-if="currentImg"
                    :src="currentImg"
                    alt="actual"
                    class="aspect-[16/6] w-full rounded-md object-cover"
                />
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
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
                    <div>
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

        <Card>
            <CardHeader><CardTitle>Visibilidad</CardTitle></CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2">
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
                    <Label for="is_active">Activo</Label>
                </div>
                <div>
                    <Label for="starts_at">Desde</Label>
                    <Input
                        id="starts_at"
                        type="date"
                        v-model="form.starts_at"
                    />
                </div>
                <div>
                    <Label for="ends_at">Hasta</Label>
                    <Input id="ends_at" type="date" v-model="form.ends_at" />
                </div>
            </CardContent>
        </Card>

        <div class="flex justify-end gap-2">
            <Button variant="outline" as-child>
                <Link href="/admin/banners">Cancelar</Link>
            </Button>
            <Button type="submit" :disabled="form.processing" @click="submit">
                {{ isEdit ? 'Guardar' : 'Crear' }}
            </Button>
        </div>
    </div>
</template>
