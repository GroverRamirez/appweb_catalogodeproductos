<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Phone } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Consultas', href: '/admin/inquiries' },
        ],
    }),
});

type Item = {
    id: number;
    product_name_snapshot: string;
    product_code_snapshot: string | null;
    quantity: number;
    unit_price: string;
    product?: { id: number; name: string; code: string } | null;
};

type Inquiry = {
    id: number;
    customer_name: string;
    customer_phone: string;
    customer_email: string | null;
    message: string | null;
    source: string;
    status: string;
    total_estimated: string | null;
    admin_notes: string | null;
    contacted_at: string | null;
    created_at: string;
    items: Item[];
    handler?: { id: number; name: string } | null;
};

const props = defineProps<{
    inquiry: Inquiry;
    statuses: string[];
}>();

const form = useForm({
    status: props.inquiry.status,
    admin_notes: props.inquiry.admin_notes ?? '',
});

const submit = () => form.patch(`/admin/inquiries/${props.inquiry.id}`);

const whatsappLink = (phone: string) =>
    `https://wa.me/${phone.replace(/[^0-9]/g, '')}`;
</script>

<template>
    <Head :title="`Consulta #${inquiry.id}`" />

    <div class="mx-auto max-w-4xl space-y-4 p-4 md:p-6">
        <div class="flex items-center gap-2">
            <Button variant="ghost" size="icon-sm" as-child>
                <Link href="/admin/inquiries"
                    ><ArrowLeft class="size-4"
                /></Link>
            </Button>
            <h1 class="text-2xl font-semibold">Consulta #{{ inquiry.id }}</h1>
            <Badge class="ml-2">{{ inquiry.status }}</Badge>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <Card class="md:col-span-2">
                <CardHeader><CardTitle>Cliente</CardTitle></CardHeader>
                <CardContent class="space-y-2 text-sm">
                    <p>
                        <span class="text-muted-foreground">Nombre: </span>
                        <span class="font-medium">{{
                            inquiry.customer_name
                        }}</span>
                    </p>
                    <p class="flex items-center gap-2">
                        <span class="text-muted-foreground">Teléfono: </span>
                        <span class="font-medium">{{
                            inquiry.customer_phone
                        }}</span>
                        <Button size="sm" variant="outline" as-child>
                            <a
                                :href="whatsappLink(inquiry.customer_phone)"
                                target="_blank"
                            >
                                <Phone class="size-3" /> WhatsApp
                            </a>
                        </Button>
                    </p>
                    <p v-if="inquiry.customer_email">
                        <span class="text-muted-foreground">Email: </span>
                        <span>{{ inquiry.customer_email }}</span>
                    </p>
                    <p>
                        <span class="text-muted-foreground">Origen: </span>
                        <span>{{ inquiry.source }}</span>
                    </p>
                    <div v-if="inquiry.message" class="mt-3">
                        <p class="text-xs text-muted-foreground">Mensaje:</p>
                        <p
                            class="mt-1 rounded-md bg-muted p-3 whitespace-pre-line"
                        >
                            {{ inquiry.message }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader><CardTitle>Actualizar</CardTitle></CardHeader>
                <CardContent>
                    <form class="space-y-3" @submit.prevent="submit">
                        <div>
                            <Label for="status">Estado</Label>
                            <select
                                id="status"
                                v-model="form.status"
                                class="h-9 w-full rounded-md border bg-background px-3 text-sm"
                            >
                                <option
                                    v-for="s in statuses"
                                    :key="s"
                                    :value="s"
                                >
                                    {{ s }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <Label for="admin_notes">Notas internas</Label>
                            <textarea
                                id="admin_notes"
                                v-model="form.admin_notes"
                                rows="4"
                                class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                            ></textarea>
                        </div>
                        <Button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full"
                        >
                            Guardar
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader
                ><CardTitle>Productos solicitados</CardTitle></CardHeader
            >
            <CardContent>
                <table v-if="inquiry.items.length" class="w-full text-sm">
                    <thead
                        class="text-left text-xs text-muted-foreground uppercase"
                    >
                        <tr>
                            <th class="py-2">Producto</th>
                            <th class="py-2 text-right">Cantidad</th>
                            <th class="py-2 text-right">Precio</th>
                            <th class="py-2 text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="it in inquiry.items" :key="it.id">
                            <td class="py-2">
                                <div class="font-medium">
                                    {{ it.product_name_snapshot }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ it.product_code_snapshot }}
                                </div>
                            </td>
                            <td class="py-2 text-right">{{ it.quantity }}</td>
                            <td class="py-2 text-right">{{ it.unit_price }}</td>
                            <td class="py-2 text-right">
                                {{
                                    (
                                        Number(it.unit_price) * it.quantity
                                    ).toFixed(2)
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="text-sm text-muted-foreground">
                    Esta consulta no tiene items asociados.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
