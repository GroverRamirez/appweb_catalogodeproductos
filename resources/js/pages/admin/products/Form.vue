<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus, Trash2 } from 'lucide-vue-next';
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
            { title: 'Productos', href: '/admin/products' },
        ],
    }),
});

type Image = { id: number; path: string; alt: string; is_main: boolean };
type Attribute = { id?: number; key: string; value: string };
type Product = {
    id: number;
    code: string;
    name: string;
    slug: string;
    short_description: string | null;
    description: string | null;
    price: string;
    sale_price: string | null;
    cost: string | null;
    stock: number;
    min_stock: number;
    unit: string;
    category_id: number | null;
    brand_id: number | null;
    is_featured: boolean;
    is_active: boolean;
    images: Image[];
    attributes: Attribute[];
};

const props = defineProps<{
    product: Product | null;
    categories: { id: number; name: string }[];
    brands: { id: number; name: string }[];
}>();

const isEdit = !!props.product;

const form = useForm({
    code: props.product?.code ?? '',
    name: props.product?.name ?? '',
    slug: props.product?.slug ?? '',
    short_description: props.product?.short_description ?? '',
    description: props.product?.description ?? '',
    price: props.product?.price ?? '0',
    sale_price: props.product?.sale_price ?? '',
    cost: props.product?.cost ?? '',
    stock: props.product?.stock ?? 0,
    min_stock: props.product?.min_stock ?? 0,
    unit: props.product?.unit ?? 'unidad',
    category_id: props.product?.category_id ?? null,
    brand_id: props.product?.brand_id ?? null,
    is_featured: props.product?.is_featured ?? false,
    is_active: props.product?.is_active ?? true,
    attributes: (props.product?.attributes ?? []).map((a) => ({
        key: a.key,
        value: a.value,
    })) as Attribute[],
    images: [] as File[],
    remove_image_ids: [] as number[],
    _method: isEdit ? 'patch' : 'post',
});

const newAttr = () => form.attributes.push({ key: '', value: '' });
const removeAttr = (i: number) => form.attributes.splice(i, 1);

const fileInput = ref<HTMLInputElement | null>(null);
const onFiles = (e: Event) => {
    const files = (e.target as HTMLInputElement).files;

    if (files) {
        form.images = Array.from(files);
    }
};

const toggleRemove = (id: number) => {
    const idx = form.remove_image_ids.indexOf(id);

    if (idx >= 0) {
        form.remove_image_ids.splice(idx, 1);
    } else {
        form.remove_image_ids.push(id);
    }
};

const isMarkedRemoved = (id: number) => form.remove_image_ids.includes(id);

const imageUrl = (path: string) =>
    path.startsWith('http') ? path : `/storage/${path}`;

const submit = () => {
    const url = isEdit
        ? `/admin/products/${props.product!.id}`
        : '/admin/products';
    // Inertia useForm con _method=patch + post para enviar FormData con archivos
    form.post(url, {
        forceFormData: true,
        onSuccess: () => router.visit('/admin/products'),
    });
};
</script>

<template>
    <Head :title="isEdit ? 'Editar producto' : 'Nuevo producto'" />

    <div class="mx-auto max-w-6xl space-y-3 p-3 md:p-4">
        <div class="flex items-center gap-2">
            <Button variant="ghost" size="icon-sm" as-child>
                <Link href="/admin/products"><ArrowLeft class="size-4" /></Link>
            </Button>
            <h1 class="text-xl font-semibold">
                {{ isEdit ? 'Editar producto' : 'Nuevo producto' }}
            </h1>
        </div>

        <form class="grid gap-3 lg:grid-cols-3" @submit.prevent="submit">
            <div class="space-y-3 lg:col-span-2">
                <Card class="gap-3 py-4">
                    <CardHeader class="pb-0"
                        ><CardTitle>Datos básicos</CardTitle></CardHeader
                    >
                    <CardContent class="space-y-3">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label for="code">Código (SKU)</Label>
                                <Input id="code" v-model="form.code" required />
                                <p
                                    v-if="form.errors.code"
                                    class="mt-1 text-xs text-destructive"
                                >
                                    {{ form.errors.code }}
                                </p>
                            </div>
                            <div>
                                <Label for="unit">Unidad</Label>
                                <Input id="unit" v-model="form.unit" />
                            </div>
                        </div>

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

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <Label for="slug"
                                    >Slug
                                    <span class="text-muted-foreground"
                                        >(opcional)</span
                                    ></Label
                                >
                                <Input id="slug" v-model="form.slug" />
                            </div>
                            <div>
                                <Label for="short_description"
                                    >Descripción corta</Label
                                >
                                <Input
                                    id="short_description"
                                    v-model="form.short_description"
                                />
                            </div>
                        </div>

                        <div>
                            <Label for="description"
                                >Descripción completa</Label
                            >
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="3"
                                class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                            ></textarea>
                        </div>
                    </CardContent>
                </Card>

                <Card class="gap-3 py-4">
                    <CardHeader class="pb-0"
                        ><CardTitle>Características</CardTitle></CardHeader
                    >
                    <CardContent class="space-y-2">
                        <div
                            v-for="(attr, i) in form.attributes"
                            :key="i"
                            class="flex items-center gap-2"
                        >
                            <Input
                                v-model="attr.key"
                                placeholder="Atributo (ej: Color)"
                                class="flex-1"
                            />
                            <Input
                                v-model="attr.value"
                                placeholder="Valor (ej: Negro)"
                                class="flex-1"
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                @click="removeAttr(i)"
                            >
                                <Trash2 class="size-4 text-destructive" />
                            </Button>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="newAttr"
                        >
                            <Plus class="size-4" /> Agregar característica
                        </Button>
                    </CardContent>
                </Card>

                <Card class="gap-3 py-4">
                    <CardHeader class="pb-0"
                        ><CardTitle>Imágenes</CardTitle></CardHeader
                    >
                    <CardContent class="space-y-3">
                        <div
                            v-if="product?.images?.length"
                            class="grid grid-cols-2 gap-3 sm:grid-cols-4"
                        >
                            <div
                                v-for="img in product.images"
                                :key="img.id"
                                class="group relative aspect-square overflow-hidden rounded-md border"
                                :class="{
                                    'opacity-30': isMarkedRemoved(img.id),
                                }"
                            >
                                <img
                                    :src="imageUrl(img.path)"
                                    :alt="img.alt"
                                    class="h-full w-full object-cover"
                                />
                                <button
                                    type="button"
                                    class="absolute top-1 right-1 rounded-full bg-white/90 p-1 text-destructive opacity-0 shadow group-hover:opacity-100"
                                    @click="toggleRemove(img.id)"
                                >
                                    <Trash2 class="size-3" />
                                </button>
                                <span
                                    v-if="img.is_main"
                                    class="absolute bottom-1 left-1 rounded bg-primary px-1 text-xs text-primary-foreground"
                                    >Principal</span
                                >
                            </div>
                        </div>

                        <input
                            ref="fileInput"
                            type="file"
                            multiple
                            accept="image/*"
                            @change="onFiles"
                            class="block w-full text-sm"
                        />
                        <p class="text-xs text-muted-foreground">
                            Hasta 8 imágenes, máx 4 MB cada una. La primera se
                            marca como principal automáticamente.
                        </p>
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-3">
                <Card class="gap-3 py-4">
                    <CardHeader class="pb-0"
                        ><CardTitle>Precio y stock</CardTitle></CardHeader
                    >
                    <CardContent class="space-y-3">
                        <div>
                            <Label for="price">Precio</Label>
                            <Input
                                id="price"
                                type="number"
                                step="0.01"
                                min="0"
                                v-model="form.price"
                                required
                            />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <Label for="sale_price">Precio oferta</Label>
                                <Input
                                    id="sale_price"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    v-model="form.sale_price"
                                />
                            </div>
                            <div>
                                <Label for="cost">Costo (interno)</Label>
                                <Input
                                    id="cost"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    v-model="form.cost"
                                />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <Label for="stock">Stock</Label>
                                <Input
                                    id="stock"
                                    type="number"
                                    min="0"
                                    v-model.number="form.stock"
                                    required
                                />
                            </div>
                            <div>
                                <Label for="min_stock">Mín. stock</Label>
                                <Input
                                    id="min_stock"
                                    type="number"
                                    min="0"
                                    v-model.number="form.min_stock"
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card class="gap-3 py-4">
                    <CardHeader class="pb-0"
                        ><CardTitle>Clasificación</CardTitle></CardHeader
                    >
                    <CardContent class="space-y-3">
                        <div>
                            <Label for="category_id">Categoría</Label>
                            <select
                                id="category_id"
                                v-model="form.category_id"
                                class="h-9 w-full rounded-md border bg-background px-3 text-sm"
                            >
                                <option :value="null">— Sin categoría —</option>
                                <option
                                    v-for="c in categories"
                                    :key="c.id"
                                    :value="c.id"
                                >
                                    {{ c.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <Label for="brand_id">Marca</Label>
                            <select
                                id="brand_id"
                                v-model="form.brand_id"
                                class="h-9 w-full rounded-md border bg-background px-3 text-sm"
                            >
                                <option :value="null">— Sin marca —</option>
                                <option
                                    v-for="b in brands"
                                    :key="b.id"
                                    :value="b.id"
                                >
                                    {{ b.name }}
                                </option>
                            </select>
                        </div>
                        <div class="flex items-center gap-6 pt-1">
                            <div class="flex items-center gap-2">
                                <Checkbox
                                    id="is_featured"
                                    v-model="form.is_featured"
                                />
                                <Label for="is_featured">Destacado</Label>
                            </div>
                            <div class="flex items-center gap-2">
                                <Checkbox
                                    id="is_active"
                                    v-model="form.is_active"
                                />
                                <Label for="is_active">Activo</Label>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div class="flex gap-2">
                    <Button variant="outline" as-child class="flex-1">
                        <Link href="/admin/products">Cancelar</Link>
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
