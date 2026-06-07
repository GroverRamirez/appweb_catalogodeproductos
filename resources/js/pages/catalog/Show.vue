<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3';
import {
    Check,
    MessageCircle,
    Minus,
    Phone,
    Plus,
    Send,
    ShoppingCart,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import SeoHead from '@/components/catalog/SeoHead.vue';
import ProductCard from '@/components/catalog/ProductCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCart } from '@/composables/useCart';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { buildWhatsAppLink, formatPrice, imageUrl } from '@/lib/catalog';
import type { CatalogProduct, StoreSettings } from '@/lib/catalog';

defineOptions({ layout: PublicLayout });

type Image = { id: number; path: string; alt: string; is_main: boolean };
type Attr = { id: number; key: string; value: string };
type Product = {
    id: number;
    code: string;
    name: string;
    slug: string;
    short_description: string | null;
    description: string | null;
    price: string;
    sale_price: string | null;
    stock: number;
    unit: string;
    is_featured: boolean;
    images: Image[];
    attributes: Attr[];
    category?: { id: number; name: string; slug: string } | null;
    brand?: { id: number; name: string; slug: string } | null;
};

const props = defineProps<{
    product: Product;
    related: CatalogProduct[];
    alsoViewed?: CatalogProduct[];
    seo: {
        title: string;
        description: string | null;
        canonical: string;
        og_image: string | null;
        og_type?: string;
        json_ld?: Record<string, unknown> | null;
    };
}>();

const page = usePage();
const store = computed(() => page.props.store as StoreSettings);
const flash = computed(
    () => (page.props.flash ?? {}) as { success?: string; error?: string },
);

const mainImageIdx = ref(
    props.product.images.findIndex((i) => i.is_main) >= 0
        ? props.product.images.findIndex((i) => i.is_main)
        : 0,
);

const currentPrice = computed(() =>
    Number(props.product.sale_price ?? props.product.price),
);
const showSale = computed(
    () =>
        !!props.product.sale_price &&
        Number(props.product.sale_price) < Number(props.product.price),
);
const inStock = computed(() => props.product.stock > 0);

const quantity = ref(1);
const incQ = () => (quantity.value = Math.min(999, quantity.value + 1));
const decQ = () => (quantity.value = Math.max(1, quantity.value - 1));

const waLink = computed(() =>
    buildWhatsAppLink(store.value.whatsapp, store.value.whatsapp_template, {
        name: props.product.name,
        code: props.product.code,
    }),
);

// Mini-form alterno
const form = useForm({
    customer_name: '',
    customer_phone: '',
    customer_email: '',
    message: '',
    product_id: props.product.id,
    quantity: 1,
    source: 'web',
});

const submitForm = () => {
    form.quantity = quantity.value;
    form.post('/consultas', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset(
                'customer_name',
                'customer_phone',
                'customer_email',
                'message',
            );
            quantity.value = 1;
        },
    });
};

const { add: addToCart } = useCart();
const addCurrentToCart = () => {
    const mainImg =
        props.product.images.find((i) => i.is_main) ?? props.product.images[0];
    addToCart(
        {
            product_id: props.product.id,
            slug: props.product.slug,
            name: props.product.name,
            code: props.product.code,
            price: currentPrice.value,
            image: mainImg ? imageUrl(mainImg.path) : null,
        },
        quantity.value,
    );
};

const sendWhatsApp = () => {
    // También dejamos registro de la consulta antes de redirigir
    form.quantity = quantity.value;
    form.transform((data) => ({
        ...data,
        source: 'whatsapp',
        customer_name: data.customer_name || 'Cliente WhatsApp',
        customer_phone: data.customer_phone || 'no provisto',
    })).post('/consultas', {
        preserveScroll: true,
        onFinish: () => window.open(waLink.value, '_blank'),
    });
};
</script>

<template>
    <SeoHead
        :title="seo.title"
        :description="seo.description ?? undefined"
        :canonical="seo.canonical"
        :og-image="seo.og_image ?? undefined"
        og-type="product"
        :json-ld="seo.json_ld ?? undefined"
    />

    <div class="mx-auto max-w-7xl px-4 py-6">
        <!-- Breadcrumb -->
        <nav
            class="mb-4 flex flex-wrap items-center gap-1 text-sm text-muted-foreground"
        >
            <Link href="/" class="hover:underline">Inicio</Link>
            <span>/</span>
            <Link href="/catalogo" class="hover:underline">Catálogo</Link>
            <template v-if="product.category">
                <span>/</span>
                <Link
                    :href="`/catalogo?category=${product.category.slug}`"
                    class="hover:underline"
                >
                    {{ product.category.name }}
                </Link>
            </template>
            <span>/</span>
            <span class="text-foreground">{{ product.name }}</span>
        </nav>

        <div class="grid gap-8 lg:grid-cols-2">
            <!-- Galería -->
            <div class="space-y-3">
                <div
                    class="relative aspect-square overflow-hidden rounded-lg border bg-muted"
                >
                    <img
                        v-if="product.images[mainImageIdx]"
                        :src="imageUrl(product.images[mainImageIdx].path)!"
                        :alt="product.images[mainImageIdx].alt"
                        class="h-full w-full object-cover"
                    />
                    <div
                        v-else
                        class="flex h-full w-full items-center justify-center text-muted-foreground"
                    >
                        Sin imagen
                    </div>
                </div>
                <div
                    v-if="product.images.length > 1"
                    class="grid grid-cols-5 gap-2"
                >
                    <button
                        v-for="(img, i) in product.images"
                        :key="img.id"
                        type="button"
                        @click="mainImageIdx = i"
                        class="aspect-square overflow-hidden rounded border-2 transition"
                        :class="
                            i === mainImageIdx
                                ? 'border-primary'
                                : 'border-transparent hover:border-muted-foreground/40'
                        "
                    >
                        <img
                            :src="imageUrl(img.path)!"
                            :alt="img.alt"
                            class="h-full w-full object-cover"
                        />
                    </button>
                </div>
            </div>

            <!-- Info -->
            <div class="space-y-4">
                <div>
                    <p
                        v-if="product.brand"
                        class="mb-1 text-sm text-muted-foreground"
                    >
                        {{ product.brand.name }}
                    </p>
                    <h1 class="text-2xl font-bold md:text-3xl">
                        {{ product.name }}
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Código: {{ product.code }}
                    </p>
                </div>

                <div v-if="store.show_prices" class="flex items-baseline gap-3">
                    <span class="text-3xl font-bold">
                        {{
                            formatPrice(
                                product.sale_price ?? product.price,
                                store.currency_symbol,
                            )
                        }}
                    </span>
                    <span
                        v-if="showSale"
                        class="text-lg text-muted-foreground line-through"
                    >
                        {{ formatPrice(product.price, store.currency_symbol) }}
                    </span>
                    <Badge v-if="showSale" variant="destructive">Oferta</Badge>
                </div>

                <div v-if="store.show_stock">
                    <Badge v-if="inStock" variant="default" class="gap-1">
                        <Check class="size-3" /> En stock ({{ product.stock }}
                        {{ product.unit }})
                    </Badge>
                    <Badge v-else variant="secondary">Sin stock</Badge>
                </div>

                <p
                    v-if="product.short_description"
                    class="text-sm text-muted-foreground"
                >
                    {{ product.short_description }}
                </p>

                <!-- Selector cantidad + acciones -->
                <Card>
                    <CardContent class="space-y-3 pt-5">
                        <div class="flex items-center gap-3">
                            <Label>Cantidad</Label>
                            <div class="flex items-center rounded-md border">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                    @click="decQ"
                                >
                                    <Minus class="size-3" />
                                </Button>
                                <span
                                    class="w-10 text-center text-sm font-medium"
                                    >{{ quantity }}</span
                                >
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                    @click="incQ"
                                >
                                    <Plus class="size-3" />
                                </Button>
                            </div>
                            <span
                                v-if="store.show_prices"
                                class="ml-auto text-sm text-muted-foreground"
                            >
                                Subtotal:
                                <span class="font-semibold text-foreground">
                                    {{
                                        formatPrice(
                                            currentPrice * quantity,
                                            store.currency_symbol,
                                        )
                                    }}
                                </span>
                            </span>
                        </div>

                        <Button
                            v-if="inStock"
                            type="button"
                            size="lg"
                            class="w-full"
                            @click="addCurrentToCart"
                        >
                            <ShoppingCart class="size-4" />
                            Agregar al carrito
                        </Button>

                        <Button
                            v-if="store.whatsapp"
                            type="button"
                            size="lg"
                            variant="outline"
                            class="w-full border-emerald-600 text-emerald-700 hover:bg-emerald-50"
                            @click="sendWhatsApp"
                        >
                            <MessageCircle class="size-4" />
                            Consultar por WhatsApp
                        </Button>

                        <p class="text-center text-xs text-muted-foreground">
                            o déjanos tus datos y te contactamos
                        </p>

                        <form @submit.prevent="submitForm" class="space-y-2">
                            <div class="grid gap-2 sm:grid-cols-2">
                                <Input
                                    v-model="form.customer_name"
                                    placeholder="Tu nombre"
                                    required
                                />
                                <Input
                                    v-model="form.customer_phone"
                                    placeholder="Teléfono"
                                    required
                                />
                            </div>
                            <Input
                                v-model="form.customer_email"
                                type="email"
                                placeholder="Email (opcional)"
                            />
                            <textarea
                                v-model="form.message"
                                rows="2"
                                placeholder="Mensaje (opcional)"
                                class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                            ></textarea>
                            <Button
                                type="submit"
                                variant="outline"
                                class="w-full"
                                :disabled="form.processing"
                            >
                                <Send class="size-4" /> Enviar consulta
                            </Button>
                            <p
                                v-if="form.errors.customer_name"
                                class="text-xs text-destructive"
                            >
                                {{ form.errors.customer_name }}
                            </p>
                            <p
                                v-if="form.errors.customer_phone"
                                class="text-xs text-destructive"
                            >
                                {{ form.errors.customer_phone }}
                            </p>
                            <p
                                v-if="flash.success"
                                class="rounded-md bg-emerald-50 px-3 py-2 text-xs text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200"
                            >
                                {{ flash.success }}
                            </p>
                        </form>

                        <div
                            v-if="store.whatsapp"
                            class="border-t pt-3 text-center text-xs text-muted-foreground"
                        >
                            <Phone class="mr-1 inline size-3" />
                            {{ store.whatsapp }}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Descripción y características -->
        <div class="mt-10 grid gap-8 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <h2 class="mb-3 text-lg font-semibold">Descripción</h2>
                <p
                    v-if="product.description"
                    class="text-sm leading-relaxed whitespace-pre-line text-muted-foreground"
                >
                    {{ product.description }}
                </p>
                <p v-else class="text-sm text-muted-foreground">
                    Sin descripción.
                </p>
            </div>

            <div v-if="product.attributes.length">
                <h2 class="mb-3 text-lg font-semibold">Características</h2>
                <dl class="divide-y rounded-lg border bg-card text-sm">
                    <div
                        v-for="a in product.attributes"
                        :key="a.id"
                        class="flex justify-between gap-3 px-3 py-2"
                    >
                        <dt class="text-muted-foreground">{{ a.key }}</dt>
                        <dd class="font-medium">{{ a.value }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Relacionados -->
        <div v-if="related.length" class="mt-12">
            <h2 class="mb-4 text-xl font-semibold">Productos relacionados</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <ProductCard v-for="p in related" :key="p.id" :product="p" />
            </div>
        </div>

        <!-- También te puede interesar -->
        <div v-if="alsoViewed && alsoViewed.length" class="mt-12">
            <h2 class="mb-4 text-xl font-semibold">
                También te puede interesar
            </h2>
            <p class="mb-3 text-sm text-muted-foreground">
                Lo más visto en esta categoría últimamente.
            </p>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <ProductCard v-for="p in alsoViewed" :key="p.id" :product="p" />
            </div>
        </div>
    </div>
</template>
