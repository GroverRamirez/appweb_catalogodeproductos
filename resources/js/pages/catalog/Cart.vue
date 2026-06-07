<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    Check,
    MessageCircle,
    Minus,
    Plus,
    Send,
    ShoppingCart,
    Tag,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCart } from '@/composables/useCart';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { formatPrice } from '@/lib/catalog';
import type { StoreSettings } from '@/lib/catalog';

defineOptions({ layout: PublicLayout });

const { items, subtotal, setQuantity, remove, clear, validate, removedByValidation } = useCart();

// ── Validación al montar ──────────────────────────────────────────────────────
const validationBanner = ref(false);

onMounted(async () => {
    const removed = await validate(/* force= */ true);
    if (removed > 0) {
        validationBanner.value = true;
    }
});

const page = usePage();
const store = computed(() => page.props.store as StoreSettings);

const form = reactive({
    customer_name: '',
    customer_phone: '',
    customer_email: '',
    message: '',
    source: 'web',
});

const errors = ref<Record<string, string>>({});
const submitting = ref(false);

// Cupón
const couponInput = ref('');
const appliedCoupon = ref<{
    code: string;
    description: string | null;
    discount: number;
    type: 'percent' | 'fixed';
    value: number;
} | null>(null);
const couponError = ref<string | null>(null);
const couponLoading = ref(false);

const discount = computed(() => appliedCoupon.value?.discount ?? 0);
const total = computed(() => Math.max(0, subtotal.value - discount.value));

const applyCoupon = async () => {
    if (!couponInput.value.trim()) {
        return;
    }

    couponError.value = null;
    couponLoading.value = true;

    try {
        const res = await fetch('/cupones/validar', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN':
                    document.querySelector<HTMLMetaElement>(
                        'meta[name="csrf-token"]',
                    )?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                code: couponInput.value,
                subtotal: subtotal.value,
            }),
        });
        const json = await res.json();

        if (json.valid) {
            appliedCoupon.value = json;
        } else {
            appliedCoupon.value = null;
            couponError.value = json.message || 'Cupón inválido.';
        }
    } catch {
        couponError.value = 'No pudimos validar el cupón. Intenta de nuevo.';
    } finally {
        couponLoading.value = false;
    }
};

const removeCoupon = () => {
    appliedCoupon.value = null;
    couponInput.value = '';
    couponError.value = null;
};

const submit = (source: 'web' | 'whatsapp') => {
    if (!items.value.length) {
        return;
    }

    errors.value = {};
    submitting.value = true;

    const payload = {
        ...form,
        source,
        coupon_code: appliedCoupon.value?.code ?? null,
        items: items.value.map((i) => ({
            product_id: i.product_id,
            quantity: i.quantity,
        })),
    };

    router.post('/checkout', payload, {
        preserveScroll: true,
        onError: (e) => {
            errors.value = e as any;
        },
        onSuccess: () => {
            const summary = items.value
                .map((i) => `• ${i.quantity}x ${i.name} (${i.code})`)
                .join('\n');
            const total = formatPrice(
                subtotal.value,
                store.value.currency_symbol,
            );
            const message = `Hola, hice un pedido por la web:\n\n${summary}\n\nTotal estimado: ${total}\nNombre: ${form.customer_name}`;

            clear();

            if (source === 'whatsapp' && store.value.whatsapp) {
                const link = `https://wa.me/${store.value.whatsapp.replace(/[^0-9]/g, '')}?text=${encodeURIComponent(message)}`;
                window.open(link, '_blank');
            }
        },
        onFinish: () => {
            submitting.value = false;
        },
    });
};
</script>

<template>
    <Head title="Carrito" />

    <!-- Aviso de productos removidos por validación -->
    <Transition
        enter-active-class="transition ease-out duration-200"
        enter-from-class="opacity-0 -translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-2"
    >
        <div
            v-if="validationBanner"
            class="mx-auto mb-0 flex max-w-6xl items-start gap-3 rounded-b-lg bg-amber-50 px-4 py-3 text-sm text-amber-800 shadow-sm dark:bg-amber-950/40 dark:text-amber-200"
        >
            <AlertTriangle class="mt-0.5 size-4 shrink-0 text-amber-500" />
            <p class="flex-1">
                Se eliminaron
                <strong>{{ removedByValidation.length }} producto(s)</strong>
                de tu carrito porque ya no están disponibles.
            </p>
            <button
                type="button"
                aria-label="Cerrar aviso"
                class="ml-auto text-amber-500 hover:text-amber-700"
                @click="validationBanner = false"
            >
                <X class="size-4" />
            </button>
        </div>
    </Transition>

    <div class="mx-auto max-w-6xl px-4 py-6">
        <div class="mb-6 flex items-center gap-2">
            <Button variant="ghost" size="icon-sm" as-child>
                <Link href="/catalogo"><ArrowLeft class="size-4" /></Link>
            </Button>
            <h1 class="text-2xl font-semibold">Tu carrito</h1>
        </div>

        <div
            v-if="!items.length"
            class="rounded-lg border bg-card p-12 text-center"
        >
            <ShoppingCart
                class="mx-auto mb-3 size-12 text-muted-foreground/40"
            />
            <p class="mb-4 text-muted-foreground">Tu carrito está vacío.</p>
            <Button as-child>
                <Link href="/catalogo">Ir al catálogo</Link>
            </Button>
        </div>

        <div v-else class="grid gap-6 lg:grid-cols-[1fr_360px]">
            <!-- Items -->
            <Card>
                <CardHeader>
                    <CardTitle> Productos ({{ items.length }}) </CardTitle>
                </CardHeader>
                <CardContent>
                    <ul class="divide-y">
                        <li
                            v-for="item in items"
                            :key="item.product_id"
                            class="flex gap-4 py-3"
                        >
                            <Link
                                :href="`/catalogo/${item.slug}`"
                                class="shrink-0"
                            >
                                <img
                                    v-if="item.image"
                                    :src="item.image"
                                    :alt="item.name"
                                    class="size-20 rounded object-cover"
                                />
                                <div
                                    v-else
                                    class="size-20 rounded bg-muted"
                                ></div>
                            </Link>
                            <div class="flex flex-1 flex-col">
                                <Link
                                    :href="`/catalogo/${item.slug}`"
                                    class="text-sm font-medium hover:underline"
                                >
                                    {{ item.name }}
                                </Link>
                                <p class="text-xs text-muted-foreground">
                                    {{ item.code }}
                                </p>
                                <div class="mt-auto flex items-center gap-3">
                                    <div
                                        class="inline-flex items-center rounded-md border"
                                    >
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon-sm"
                                            @click="
                                                setQuantity(
                                                    item.product_id,
                                                    item.quantity - 1,
                                                )
                                            "
                                        >
                                            <Minus class="size-3" />
                                        </Button>
                                        <span
                                            class="min-w-8 text-center text-sm"
                                        >
                                            {{ item.quantity }}
                                        </span>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon-sm"
                                            @click="
                                                setQuantity(
                                                    item.product_id,
                                                    item.quantity + 1,
                                                )
                                            "
                                        >
                                            <Plus class="size-3" />
                                        </Button>
                                    </div>
                                    <button
                                        type="button"
                                        class="text-xs text-destructive hover:underline"
                                        @click="remove(item.product_id)"
                                    >
                                        <Trash2 class="inline size-3" /> Quitar
                                    </button>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold">
                                    {{
                                        formatPrice(
                                            item.price * item.quantity,
                                            store.currency_symbol,
                                        )
                                    }}
                                </p>
                                <p
                                    v-if="item.quantity > 1"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{
                                        formatPrice(
                                            item.price,
                                            store.currency_symbol,
                                        )
                                    }}
                                    c/u
                                </p>
                            </div>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <!-- Checkout -->
            <Card class="h-fit lg:sticky lg:top-20">
                <CardHeader>
                    <CardTitle>Datos para el pedido</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div>
                        <Label for="customer_name">Nombre</Label>
                        <Input
                            id="customer_name"
                            v-model="form.customer_name"
                            required
                        />
                        <p
                            v-if="errors.customer_name"
                            class="mt-1 text-xs text-destructive"
                        >
                            {{ errors.customer_name }}
                        </p>
                    </div>
                    <div>
                        <Label for="customer_phone">Teléfono / WhatsApp</Label>
                        <Input
                            id="customer_phone"
                            v-model="form.customer_phone"
                            required
                        />
                        <p
                            v-if="errors.customer_phone"
                            class="mt-1 text-xs text-destructive"
                        >
                            {{ errors.customer_phone }}
                        </p>
                    </div>
                    <div>
                        <Label for="customer_email">Email (opcional)</Label>
                        <Input
                            id="customer_email"
                            type="email"
                            v-model="form.customer_email"
                        />
                    </div>
                    <div>
                        <Label for="message">Mensaje (opcional)</Label>
                        <textarea
                            id="message"
                            v-model="form.message"
                            rows="3"
                            class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                        ></textarea>
                    </div>

                    <!-- Cupón -->
                    <div class="space-y-2 border-t pt-3">
                        <Label class="text-xs">Código de descuento</Label>
                        <div v-if="!appliedCoupon" class="flex gap-2">
                            <Input
                                v-model="couponInput"
                                placeholder="DESCUENTO10"
                                class="uppercase"
                                @keyup.enter="applyCoupon"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                :disabled="couponLoading"
                                @click="applyCoupon"
                            >
                                <Tag class="size-3" /> Aplicar
                            </Button>
                        </div>
                        <div
                            v-else
                            class="flex items-center justify-between gap-2 rounded-md border border-emerald-300 bg-emerald-50 px-3 py-2 text-xs dark:border-emerald-700 dark:bg-emerald-950/30"
                        >
                            <div
                                class="flex items-center gap-2 text-emerald-700 dark:text-emerald-200"
                            >
                                <Check class="size-3" />
                                <span class="font-mono font-semibold">{{
                                    appliedCoupon.code
                                }}</span>
                                <span v-if="appliedCoupon.description"
                                    >— {{ appliedCoupon.description }}</span
                                >
                            </div>
                            <button
                                type="button"
                                @click="removeCoupon"
                                class="text-destructive hover:underline"
                                aria-label="Quitar cupón"
                            >
                                <X class="size-3" />
                            </button>
                        </div>
                        <p v-if="couponError" class="text-xs text-destructive">
                            {{ couponError }}
                        </p>
                    </div>

                    <div class="space-y-1 border-t pt-3">
                        <div class="flex justify-between text-sm">
                            <span class="text-muted-foreground">Subtotal</span>
                            <span>
                                {{
                                    formatPrice(subtotal, store.currency_symbol)
                                }}
                            </span>
                        </div>
                        <div
                            v-if="discount > 0"
                            class="flex justify-between text-sm text-emerald-600"
                        >
                            <span>Descuento</span>
                            <span
                                >−{{
                                    formatPrice(discount, store.currency_symbol)
                                }}</span
                            >
                        </div>
                        <div class="flex justify-between text-base">
                            <span class="font-semibold">Total</span>
                            <span class="font-bold">
                                {{ formatPrice(total, store.currency_symbol) }}
                            </span>
                        </div>

                        <Button
                            v-if="store.whatsapp"
                            type="button"
                            size="lg"
                            class="w-full bg-emerald-600 text-white hover:bg-emerald-700"
                            :disabled="submitting"
                            @click="submit('whatsapp')"
                        >
                            <MessageCircle class="size-4" />
                            Enviar por WhatsApp
                        </Button>

                        <Button
                            type="button"
                            size="lg"
                            variant="outline"
                            class="w-full"
                            :disabled="submitting"
                            @click="submit('web')"
                        >
                            <Send class="size-4" />
                            Enviar pedido
                        </Button>

                        <p class="text-center text-xs text-muted-foreground">
                            Te contactaremos para confirmar precios y stock.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
