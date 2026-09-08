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
            { title: 'Cupones', href: '/admin/coupons' },
        ],
    }),
});

type Coupon = {
    id: number;
    code: string;
    description: string | null;
    type: 'percent' | 'fixed';
    value: string;
    min_subtotal: string | null;
    max_uses: number | null;
    starts_at: string | null;
    ends_at: string | null;
    is_active: boolean;
};

const props = defineProps<{ coupon: Coupon | null }>();
const isEdit = !!props.coupon;

const form = useForm({
    code: props.coupon?.code ?? '',
    description: props.coupon?.description ?? '',
    type: (props.coupon?.type ?? 'percent') as 'percent' | 'fixed',
    value: props.coupon?.value ?? '10',
    min_subtotal: props.coupon?.min_subtotal ?? '',
    max_uses: props.coupon?.max_uses ?? '',
    starts_at: props.coupon?.starts_at?.substring(0, 10) ?? '',
    ends_at: props.coupon?.ends_at?.substring(0, 10) ?? '',
    is_active: props.coupon?.is_active ?? true,
});

const submit = () => {
    if (isEdit) {
        form.patch(`/admin/coupons/${props.coupon!.id}`);
    } else {
        form.post('/admin/coupons');
    }
};
</script>

<template>
    <Head :title="isEdit ? 'Editar cupón' : 'Nuevo cupón'" />

    <div class="mx-auto max-w-2xl space-y-3 p-3 md:p-4">
        <div class="flex items-center gap-2">
            <Button variant="ghost" size="icon-sm" as-child>
                <Link href="/admin/coupons"><ArrowLeft class="size-4" /></Link>
            </Button>
            <h1 class="text-xl font-semibold">
                {{ isEdit ? 'Editar cupón' : 'Nuevo cupón' }}
            </h1>
        </div>

        <Card class="py-5">
            <CardContent class="pt-0">
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label for="code">Código</Label>
                            <Input
                                id="code"
                                v-model="form.code"
                                placeholder="DESCUENTO10"
                                class="font-mono uppercase"
                                required
                            />
                            <p
                                v-if="form.errors.code"
                                class="mt-1 text-xs text-destructive"
                            >
                                {{ form.errors.code }}
                            </p>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="type">Tipo</Label>
                            <select
                                id="type"
                                v-model="form.type"
                                class="h-9 w-full rounded-md border bg-card px-3 text-sm"
                            >
                                <option value="percent">Porcentaje (%)</option>
                                <option value="fixed">Monto fijo</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label for="value">
                                Valor
                                <span class="text-muted-foreground">
                                    ({{ form.type === 'percent' ? '%' : 'S/' }})
                                </span>
                            </Label>
                            <Input
                                id="value"
                                type="number"
                                step="0.01"
                                min="0"
                                v-model="form.value"
                                required
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="min_subtotal"
                                >Subtotal mínimo (opcional)</Label
                            >
                            <Input
                                id="min_subtotal"
                                type="number"
                                step="0.01"
                                min="0"
                                v-model="form.min_subtotal"
                            />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="description">Descripción (opcional)</Label>
                        <Input id="description" v-model="form.description" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="space-y-1.5">
                            <Label for="max_uses">Máx. usos</Label>
                            <Input
                                id="max_uses"
                                type="number"
                                min="1"
                                v-model.number="form.max_uses"
                                placeholder="∞"
                            />
                        </div>
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

                    <div class="flex items-center gap-2">
                        <Checkbox id="is_active" v-model="form.is_active" />
                        <Label for="is_active">Activo</Label>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <Button variant="outline" as-child>
                            <Link href="/admin/coupons">Cancelar</Link>
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ isEdit ? 'Guardar' : 'Crear' }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
