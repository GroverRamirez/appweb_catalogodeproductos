<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    Building2,
    Check,
    CheckCircle2,
    Clock,
    Copy,
    Download,
    Eye,
    FileText,
    Info,
    QrCode,
    UploadCloud,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { formatPrice } from '@/lib/catalog';
import type { StoreSettings } from '@/lib/catalog';

defineOptions({ layout: PublicLayout });

type Item = {
    id: number;
    product_name_snapshot: string;
    product_code_snapshot: string | null;
    quantity: number;
    unit_price: string;
};

type Inquiry = {
    id: number;
    public_token?: string;
    customer_name?: string;
    customer_phone?: string;
    customer_email?: string;
    status?: string;
    payment_status?: string;
    payment_receipt_url?: string | null;
    total_estimated: string | null;
    discount_amount?: string | number | null;
    coupon_code?: string | null;
    items: Item[];
};

const props = defineProps<{ inquiry: Inquiry | null }>();

const page = usePage();
const store = computed(() => page.props.store as StoreSettings);
const isAuth = computed(() => !!page.props.auth?.user);

const paymentSettings = computed(() => store.value?.payment);

const whatsappNumber = computed(() => {
    const raw = store.value?.whatsapp?.replace(/[^0-9]/g, '') ?? '';

    if (!raw) {
        return '';
    }

    return raw.length === 8 ? `591${raw}` : raw;
});

const whatsappUrl = computed(() => {
    if (!whatsappNumber.value || !props.inquiry) {
        return null;
    }

    const inq = props.inquiry;
    const storeName = store.value?.name ?? 'la tienda';
    const currency = store.value?.currency_symbol ?? 'Bs';

    const itemsSummary =
        inq.items
            ?.map(
                (i) =>
                    `• ${i.quantity}x ${i.product_name_snapshot}${
                        i.product_code_snapshot
                            ? ` (${i.product_code_snapshot})`
                            : ''
                    }`,
            )
            .join('\n') ?? '';

    let statusNote = 'Pendiente de pago';

    if (inq.payment_status === 'comprobante_subido') {
        statusNote = 'Comprobante de pago subido en la web (en revisión)';
    } else if (inq.payment_status === 'verificado') {
        statusNote = 'Pago verificado';
    }

    const customerDisplayName = page.props.auth?.user?.name || '';

    const lines = [
        `¡Hola ${storeName}!`,
        `Acabo de registrar el pedido *#${inq.id}* en la tienda web.`,
        '',
        customerDisplayName ? `👤 *Cliente:* ${customerDisplayName}` : '',
        itemsSummary ? `📦 *Productos:*\n${itemsSummary}` : '',
        inq.total_estimated
            ? `💰 *Total a pagar:* ${formatPrice(inq.total_estimated, currency)}`
            : '',
        `💳 *Estado de pago:* ${statusNote}`,
        '',
        inq.payment_status === 'comprobante_subido'
            ? 'Ya adjunté mi comprobante de pago en la web. ¿Podrían confirmarme la recepción para coordinar el despacho? ¡Muchas gracias!'
            : '¿Podrían ayudarme con la confirmación y detalles de entrega de mi pedido? ¡Muchas gracias!',
    ].filter((line) => line !== '');

    const text = encodeURIComponent(lines.join('\n'));

    return `https://wa.me/${whatsappNumber.value}?text=${text}`;
});

// Copiado al portapapeles con feedback visual
const copiedField = ref<string | null>(null);
const copyToClipboard = async (text: string, field: string) => {
    try {
        await navigator.clipboard.writeText(text);
        copiedField.value = field;
        setTimeout(() => {
            if (copiedField.value === field) {
                copiedField.value = null;
            }
        }, 2000);
    } catch {
        // Fallback silencioso
    }
};

// Formulario de subida de comprobante
const fileInputRef = ref<HTMLInputElement | null>(null);
const selectedFileName = ref<string | null>(null);
const isPdf = ref(false);
const previewUrl = ref<string | null>(null);

const uploadForm = useForm({
    receipt: null as File | null,
});

const onFileSelect = (e: Event) => {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!file) {
        uploadForm.receipt = null;
        selectedFileName.value = null;
        previewUrl.value = null;
        isPdf.value = false;

        return;
    }

    uploadForm.receipt = file;
    selectedFileName.value = file.name;
    isPdf.value = file.type === 'application/pdf' || file.name.endsWith('.pdf');

    if (!isPdf.value && file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = (ev) => {
            previewUrl.value = ev.target?.result as string;
        };
        reader.readAsDataURL(file);
    } else {
        previewUrl.value = null;
    }
};

const submitReceipt = () => {
    if (!props.inquiry?.public_token || !uploadForm.receipt) {
        return;
    }

    uploadForm.post(
        `/carrito/gracias/${props.inquiry.public_token}/comprobante`,
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                uploadForm.reset();
                selectedFileName.value = null;
                previewUrl.value = null;

                if (fileInputRef.value) {
                    fileInputRef.value.value = '';
                }
            },
        },
    );
};

const paymentStatusConfig = computed(() => {
    const status = props.inquiry?.payment_status ?? 'sin_pago';

    switch (status) {
        case 'comprobante_subido':
            return {
                label: 'Comprobante en revisión',
                variant:
                    'bg-blue-500/15 text-blue-700 dark:text-blue-300 border-blue-300 dark:border-blue-700',
                icon: Clock,
                headerBorder: 'border-blue-500/30',
                headerIcon: Clock,
                headerIconClass:
                    'bg-blue-500/10 text-blue-600 dark:text-blue-400',
                title: '¡Comprobante en revisión!',
                subtitle: 'Recibimos tu comprobante de pago para la solicitud',
                message:
                    'Tu comprobante fue recibido y está en proceso de verificación por nuestro equipo.',
                notice: 'Estamos validando tu pago bancario. En cuanto sea confirmado, te avisaremos y comenzaremos a preparar tu pedido.',
            };
        case 'verificado':
            return {
                label: 'Pago verificado',
                variant:
                    'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-300 dark:border-emerald-700',
                icon: CheckCircle2,
                headerBorder: 'border-emerald-500/30',
                headerIcon: CheckCircle2,
                headerIconClass:
                    'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
                title: '¡Pago confirmado con éxito!',
                subtitle: 'El pago de tu solicitud fue verificado exitosamente',
                message:
                    '¡Tu pago ha sido confirmado exitosamente! Tu pedido está listo para preparación y entrega.',
                notice: null,
            };
        case 'rechazado':
            return {
                label: 'Comprobante observado',
                variant:
                    'bg-destructive/15 text-destructive border-destructive/30',
                icon: AlertCircle,
                headerBorder: 'border-destructive/30',
                headerIcon: AlertCircle,
                headerIconClass: 'bg-destructive/10 text-destructive',
                title: 'Comprobante de pago observado',
                subtitle:
                    'Hubo una observación con el comprobante de tu solicitud',
                message:
                    'El comprobante enviado no pudo ser verificado. Por favor, sube un nuevo comprobante legible.',
                notice: 'Tu pedido no podrá ser despachado hasta que verifiquemos un comprobante de pago válido.',
            };
        default:
            return {
                label: 'Pendiente de pago',
                variant:
                    'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-300 dark:border-amber-700',
                icon: Clock,
                headerBorder: 'border-amber-500/30',
                headerIcon: Clock,
                headerIconClass:
                    'bg-amber-500/10 text-amber-600 dark:text-amber-400',
                title: '¡Pedido registrado! Pendiente de pago',
                subtitle:
                    'Tu solicitud fue registrada con éxito en nuestro sistema',
                message:
                    'Realiza tu transferencia o pago con el código QR y sube tu comprobante a continuación.',
                notice: 'Importante: Tu pedido se procesará y enviará una vez que confirmemos la recepción de tu transferencia o pago QR.',
            };
    }
});
</script>

<template>
    <Head title="¡Gracias por tu pedido!" />

    <div class="mx-auto max-w-3xl space-y-6 px-4 py-8 md:py-12">
        <!-- Cabecera de Confirmación -->
        <Card :class="[paymentStatusConfig.headerBorder, 'shadow-xs']">
            <CardHeader class="items-center text-center">
                <div
                    :class="[
                        'rounded-full p-3',
                        paymentStatusConfig.headerIconClass,
                    ]"
                >
                    <component
                        :is="paymentStatusConfig.headerIcon"
                        class="size-10"
                    />
                </div>
                <CardTitle class="mt-2 text-2xl font-bold tracking-tight">
                    {{ paymentStatusConfig.title }}
                </CardTitle>
                <p class="max-w-xl text-sm text-muted-foreground">
                    {{ paymentStatusConfig.subtitle }}
                    <span class="font-mono font-semibold text-foreground">
                        #{{ inquiry?.id ?? '...' }} </span
                    >.
                </p>
            </CardHeader>
            <CardContent class="space-y-4">
                <!-- Resumen de items -->
                <div v-if="inquiry" class="rounded-lg border bg-muted/30 p-4">
                    <p
                        class="mb-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        Resumen de la compra
                    </p>
                    <ul class="divide-y text-sm">
                        <li
                            v-for="i in inquiry.items"
                            :key="i.id"
                            class="flex items-center justify-between py-2"
                        >
                            <div>
                                <span class="font-medium text-foreground">
                                    {{ i.quantity }}x
                                    {{ i.product_name_snapshot }}
                                </span>
                                <span
                                    v-if="i.product_code_snapshot"
                                    class="ml-2 font-mono text-xs text-muted-foreground"
                                >
                                    ({{ i.product_code_snapshot }})
                                </span>
                            </div>
                            <span class="font-medium tabular-nums">
                                {{
                                    formatPrice(
                                        Number(i.unit_price) * i.quantity,
                                        store.currency_symbol,
                                    )
                                }}
                            </span>
                        </li>
                    </ul>
                    <!-- Descuento si hubo cupón aplicado -->
                    <div
                        v-if="
                            inquiry.discount_amount &&
                            Number(inquiry.discount_amount) > 0
                        "
                        class="mt-2 flex items-center justify-between text-xs text-emerald-600 dark:text-emerald-400"
                    >
                        <span>
                            Descuento aplicado
                            <template v-if="inquiry.coupon_code">
                                (Cupón: {{ inquiry.coupon_code }})
                            </template>
                        </span>
                        <span class="font-medium tabular-nums">
                            -{{
                                formatPrice(
                                    Number(inquiry.discount_amount),
                                    store.currency_symbol,
                                )
                            }}
                        </span>
                    </div>

                    <div
                        v-if="inquiry.total_estimated"
                        class="mt-3 flex items-center justify-between border-t pt-3 text-base font-bold"
                    >
                        <span>Total a pagar</span>
                        <span class="text-lg text-primary tabular-nums">
                            {{
                                formatPrice(
                                    inquiry.total_estimated,
                                    store.currency_symbol,
                                )
                            }}
                        </span>
                    </div>
                </div>

                <!-- Aviso informativo de despacho condicionado al pago -->
                <div
                    v-if="paymentStatusConfig.notice"
                    class="flex items-start gap-2.5 rounded-lg border p-3 text-xs"
                    :class="paymentStatusConfig.variant"
                >
                    <Info class="mt-0.5 size-4 shrink-0" />
                    <p class="leading-relaxed font-medium">
                        {{ paymentStatusConfig.notice }}
                    </p>
                </div>

                <!-- Banner / Botón de aviso directo por WhatsApp -->
                <div
                    v-if="whatsappUrl"
                    class="rounded-xl border border-emerald-300/70 bg-emerald-500/10 p-4 sm:p-5 dark:border-emerald-800/70 dark:bg-emerald-950/40"
                >
                    <div
                        class="flex flex-col items-center justify-between gap-4 sm:flex-row"
                    >
                        <div
                            class="flex items-center gap-3.5 text-center sm:text-left"
                        >
                            <div
                                class="flex size-11 shrink-0 items-center justify-center rounded-full bg-[#25D366] text-white shadow-md shadow-emerald-500/20 sm:size-12"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    class="size-6 fill-white"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"
                                    />
                                </svg>
                            </div>
                            <div>
                                <h3
                                    class="text-sm font-semibold text-foreground sm:text-base"
                                >
                                    {{
                                        inquiry?.payment_status ===
                                        'comprobante_subido'
                                            ? '¿Subiste tu comprobante? Avísanos por WhatsApp'
                                            : '¿Deseas coordinar tu pedido o pago de inmediato?'
                                    }}
                                </h3>
                                <p class="text-xs text-muted-foreground">
                                    Envía un mensaje con el resumen de tu pedido
                                    #{{ inquiry?.id }} directamente al equipo de
                                    ventas.
                                </p>
                            </div>
                        </div>
                        <a
                            :href="whatsappUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex w-full shrink-0 items-center justify-center gap-2 rounded-lg bg-[#25D366] px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-[#25D366]/25 transition hover:bg-[#128C7E] active:scale-95 sm:w-auto"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                class="size-4 fill-white"
                                aria-hidden="true"
                            >
                                <path
                                    d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"
                                />
                            </svg>
                            <span>Avisar por WhatsApp</span>
                        </a>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Bloque de Pago con QR Bancario / Transferencia -->
        <Card
            v-if="paymentSettings?.bank_qr_enabled"
            class="overflow-hidden border-primary/20 shadow-xs"
        >
            <CardHeader class="border-b bg-muted/20 pb-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <div class="rounded-md bg-primary/10 p-2 text-primary">
                            <QrCode class="size-5" />
                        </div>
                        <div>
                            <CardTitle class="text-lg">
                                Pago con QR Simple / Transferencia
                            </CardTitle>
                            <p class="text-xs text-muted-foreground">
                                Realiza tu pago bancario y adjunta el
                                comprobante para acelerar la entrega
                            </p>
                        </div>
                    </div>
                    <!-- Estado actual del pago -->
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium"
                        :class="paymentStatusConfig.variant"
                    >
                        <component
                            :is="paymentStatusConfig.icon"
                            class="size-3.5"
                        />
                        {{ paymentStatusConfig.label }}
                    </span>
                </div>
            </CardHeader>

            <CardContent class="grid gap-6 p-6 md:grid-cols-2">
                <!-- Columna Izquierda: QR y Datos Bancarios -->
                <div
                    class="flex flex-col items-center justify-center space-y-4 rounded-xl border bg-card p-5 text-center"
                >
                    <div
                        v-if="paymentSettings.bank_qr_image_url"
                        class="group relative flex flex-col items-center"
                    >
                        <div
                            class="overflow-hidden rounded-xl border bg-white p-3 shadow-xs"
                        >
                            <img
                                :src="paymentSettings.bank_qr_image_url"
                                alt="Código QR Bancario"
                                class="size-48 object-contain md:size-56"
                            />
                        </div>
                        <a
                            :href="paymentSettings.bank_qr_image_url"
                            download="qr-pago.webp"
                            target="_blank"
                            class="mt-2 inline-flex items-center gap-1.5 text-xs text-muted-foreground hover:text-primary hover:underline"
                        >
                            <Download class="size-3.5" /> Descargar imagen QR
                        </a>
                    </div>
                    <div
                        v-else
                        class="flex flex-col items-center justify-center rounded-xl border border-dashed bg-muted/40 p-8 text-center"
                    >
                        <Building2 class="size-12 text-muted-foreground/60" />
                        <p class="mt-2 text-sm font-medium">
                            Transferencia Bancaria
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Utiliza los datos de la cuenta indicados al lado
                        </p>
                    </div>

                    <!-- Datos rápidos de la cuenta -->
                    <div
                        class="w-full space-y-2 rounded-lg bg-muted/40 p-3 text-left text-xs"
                    >
                        <div
                            v-if="paymentSettings.bank_name"
                            class="flex items-center justify-between"
                        >
                            <span class="text-muted-foreground">Banco:</span>
                            <span class="font-semibold text-foreground">
                                {{ paymentSettings.bank_name }}
                            </span>
                        </div>
                        <div
                            v-if="paymentSettings.bank_account_holder"
                            class="flex items-center justify-between"
                        >
                            <span class="text-muted-foreground">Titular:</span>
                            <span class="font-medium text-foreground">
                                {{ paymentSettings.bank_account_holder }}
                            </span>
                        </div>
                        <div
                            v-if="paymentSettings.bank_account_number"
                            class="flex items-center justify-between gap-1"
                        >
                            <span class="text-muted-foreground"
                                >N° Cuenta:</span
                            >
                            <div
                                class="flex items-center gap-1 font-mono font-bold text-foreground"
                            >
                                <span>{{
                                    paymentSettings.bank_account_number
                                }}</span>
                                <button
                                    type="button"
                                    title="Copiar número de cuenta"
                                    class="rounded p-1 hover:bg-muted"
                                    @click="
                                        copyToClipboard(
                                            paymentSettings.bank_account_number,
                                            'account',
                                        )
                                    "
                                >
                                    <Check
                                        v-if="copiedField === 'account'"
                                        class="size-3.5 text-emerald-600"
                                    />
                                    <Copy
                                        v-else
                                        class="size-3.5 text-muted-foreground"
                                    />
                                </button>
                            </div>
                        </div>
                        <div
                            v-if="inquiry?.total_estimated"
                            class="flex items-center justify-between border-t border-border/60 pt-1.5"
                        >
                            <span class="text-muted-foreground"
                                >Monto exacto:</span
                            >
                            <div
                                class="flex items-center gap-1 font-bold text-primary"
                            >
                                <span>
                                    {{
                                        formatPrice(
                                            inquiry.total_estimated,
                                            store.currency_symbol,
                                        )
                                    }}
                                </span>
                                <button
                                    type="button"
                                    title="Copiar monto"
                                    class="rounded p-1 hover:bg-muted"
                                    @click="
                                        copyToClipboard(
                                            String(inquiry.total_estimated),
                                            'amount',
                                        )
                                    "
                                >
                                    <Check
                                        v-if="copiedField === 'amount'"
                                        class="size-3.5 text-emerald-600"
                                    />
                                    <Copy
                                        v-else
                                        class="size-3.5 text-muted-foreground"
                                    />
                                </button>
                            </div>
                        </div>
                        <div
                            class="flex items-center justify-between text-muted-foreground"
                        >
                            <span>Concepto / Glosa:</span>
                            <span class="font-mono font-medium text-foreground">
                                Pedido #{{ inquiry?.id }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Columna Derecha: Instrucciones y Subida de Comprobante -->
                <div class="flex flex-col justify-between space-y-4">
                    <div class="space-y-3">
                        <h3 class="text-sm font-semibold">
                            Instrucciones para completar tu pago
                        </h3>
                        <p
                            class="text-xs leading-relaxed whitespace-pre-line text-muted-foreground"
                        >
                            {{
                                paymentSettings.bank_payment_instructions ||
                                '1. Abre tu aplicación bancaria móvil.\n2. Escanea el código QR o realiza la transferencia con los datos provistos.\n3. Guarda el comprobante (captura o PDF) y súbelo a continuación.'
                            }}
                        </p>

                        <!-- Mensaje de estado -->
                        <div
                            class="rounded-lg border p-3 text-xs"
                            :class="paymentStatusConfig.variant"
                        >
                            <p class="leading-relaxed font-medium">
                                {{ paymentStatusConfig.message }}
                            </p>
                        </div>

                        <!-- Comprobante actualmente cargado -->
                        <div
                            v-if="inquiry?.payment_receipt_url"
                            class="flex items-center justify-between rounded-lg border bg-muted/30 p-2.5 text-xs"
                        >
                            <div class="flex items-center gap-2">
                                <FileText class="size-4 text-primary" />
                                <span class="font-medium text-foreground">
                                    Comprobante adjunto
                                </span>
                            </div>
                            <Button size="sm" variant="outline" as-child>
                                <a
                                    :href="inquiry.payment_receipt_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <Eye class="mr-1 size-3" /> Ver archivo
                                </a>
                            </Button>
                        </div>
                    </div>

                    <!-- Formulario de subida -->
                    <form
                        v-if="inquiry?.payment_status !== 'verificado'"
                        @submit.prevent="submitReceipt"
                        class="space-y-3 border-t pt-4"
                    >
                        <p class="text-xs font-semibold">
                            {{
                                inquiry?.payment_status === 'comprobante_subido'
                                    ? '¿Deseas reemplazar el comprobante?'
                                    : 'Subir comprobante de transferencia / QR'
                            }}
                        </p>

                        <!-- Zona Drag & Drop / Input file -->
                        <div
                            class="relative flex flex-col items-center justify-center rounded-lg border-2 border-dashed border-border p-4 text-center transition-colors hover:border-primary/50"
                            :class="
                                uploadForm.receipt
                                    ? 'border-primary/40 bg-primary/5'
                                    : 'bg-muted/20'
                            "
                        >
                            <input
                                ref="fileInputRef"
                                type="file"
                                accept="image/jpeg,image/png,image/webp,application/pdf"
                                class="absolute inset-0 cursor-pointer opacity-0"
                                @change="onFileSelect"
                            />

                            <div v-if="!uploadForm.receipt" class="space-y-1">
                                <UploadCloud
                                    class="mx-auto size-8 text-muted-foreground"
                                />
                                <p class="text-xs font-medium text-foreground">
                                    Haz clic o arrastra tu comprobante aquí
                                </p>
                                <p class="text-[10px] text-muted-foreground">
                                    Formatos permitidos: JPG, PNG, WebP o PDF
                                    (máx. 5 MB)
                                </p>
                            </div>

                            <div
                                v-else
                                class="flex flex-col items-center space-y-2"
                            >
                                <img
                                    v-if="previewUrl"
                                    :src="previewUrl"
                                    alt="Vista previa"
                                    class="size-16 rounded object-cover shadow-xs"
                                />
                                <FileText v-else class="size-12 text-primary" />
                                <div class="text-xs">
                                    <p
                                        class="max-w-[200px] truncate font-medium text-foreground"
                                    >
                                        {{ selectedFileName }}
                                    </p>
                                    <p
                                        class="text-[10px] text-muted-foreground"
                                    >
                                        Listo para subir
                                    </p>
                                </div>
                            </div>
                        </div>

                        <p
                            v-if="uploadForm.errors.receipt"
                            class="text-xs font-medium text-destructive"
                        >
                            {{ uploadForm.errors.receipt }}
                        </p>

                        <Button
                            type="submit"
                            size="sm"
                            class="w-full"
                            :disabled="
                                !uploadForm.receipt || uploadForm.processing
                            "
                        >
                            <span v-if="uploadForm.processing"
                                >Subiendo comprobante...</span
                            >
                            <span v-else>
                                {{
                                    inquiry?.payment_status ===
                                    'comprobante_subido'
                                        ? 'Actualizar Comprobante'
                                        : 'Enviar Comprobante'
                                }}
                            </span>
                        </Button>

                        <!-- Mensaje de aviso rápido por WhatsApp tras subir comprobante -->
                        <div
                            v-if="
                                inquiry?.payment_status ===
                                    'comprobante_subido' && whatsappUrl
                            "
                            class="mt-3 rounded-lg border border-emerald-300/60 bg-emerald-50/60 p-3 text-center dark:border-emerald-800/60 dark:bg-emerald-950/30"
                        >
                            <p class="mb-2 text-xs text-muted-foreground">
                                ¿Deseas que validemos tu comprobante de
                                inmediato?
                            </p>
                            <a
                                :href="whatsappUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center justify-center gap-1.5 rounded-md bg-[#25D366] px-3.5 py-1.5 text-xs font-semibold text-white shadow-xs transition hover:bg-[#128C7E] active:scale-95"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24"
                                    class="size-3.5 fill-white"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"
                                    />
                                </svg>
                                <span>Avisar comprobante por WhatsApp</span>
                            </a>
                        </div>
                    </form>
                </div>
            </CardContent>
        </Card>

        <!-- Botones de Navegación Final -->
        <div class="flex flex-wrap justify-center gap-3 pt-2">
            <a
                v-if="whatsappUrl"
                :href="whatsappUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-2 rounded-md bg-[#25D366] px-4 py-2 text-sm font-semibold text-white shadow-xs transition hover:bg-[#128C7E] active:scale-95"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    class="size-4 fill-white"
                    aria-hidden="true"
                >
                    <path
                        d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"
                    />
                </svg>
                <span>Avisar por WhatsApp</span>
            </a>
            <Button v-if="isAuth" variant="default" as-child>
                <Link href="/mis-pedidos">Ver mis pedidos</Link>
            </Button>
            <Button :variant="isAuth ? 'outline' : 'default'" as-child>
                <Link href="/catalogo">Seguir explorando</Link>
            </Button>
            <Button variant="ghost" as-child>
                <Link href="/">Volver al inicio</Link>
            </Button>
        </div>
    </div>
</template>
