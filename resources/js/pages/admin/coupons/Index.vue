<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Ticket, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

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
    used_count: number;
    starts_at: string | null;
    ends_at: string | null;
    is_active: boolean;
};

const props = defineProps<{
    coupons: {
        data: Coupon[];
        links: { url: string | null; label: string; active: boolean }[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: { q?: string };
}>();

const q = ref(props.filters.q ?? '');
let timer: ReturnType<typeof setTimeout> | null = null;
watch(q, () => {
    if (timer) {
clearTimeout(timer);
}

    timer = setTimeout(() => {
        router.get('/admin/coupons', { q: q.value }, { preserveState: true, replace: true });
    }, 300);
});

const destroy = (c: Coupon) => {
    if (!confirm(`¿Eliminar el cupón "${c.code}"?`)) {
return;
}

    router.delete(`/admin/coupons/${c.id}`, { preserveScroll: true });
};

const valueText = (c: Coupon) =>
    c.type === 'percent' ? `${parseFloat(c.value)}%` : `S/ ${c.value}`;
</script>

<template>
    <Head title="Cupones" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            :icon="Ticket"
            eyebrow="Promociones"
            title="Cupones"
            description="Códigos de descuento aplicables en el checkout."
        >
            <template #actions>
                <Button as-child class="rounded-full gradient-brand glow-brand border-transparent text-white">
                    <Link href="/admin/coupons/create">
                        <Plus class="size-4" /> Nuevo cupón
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="relative max-w-sm">
            <Search class="absolute left-2 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input v-model="q" placeholder="Buscar por código..." class="pl-8" />
        </div>

        <div class="overflow-x-auto rounded-md border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-xs uppercase text-muted-foreground">
                    <tr>
                        <th class="px-3 py-2">Código</th>
                        <th class="px-3 py-2">Tipo / valor</th>
                        <th class="px-3 py-2">Mín. subtotal</th>
                        <th class="px-3 py-2">Usos</th>
                        <th class="px-3 py-2">Vigencia</th>
                        <th class="px-3 py-2 w-24">Estado</th>
                        <th class="px-3 py-2 w-32"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="c in coupons.data" :key="c.id" class="hover:bg-muted/30">
                        <td class="px-3 py-2">
                            <div class="font-mono font-semibold">{{ c.code }}</div>
                            <div v-if="c.description" class="text-xs text-muted-foreground">
                                {{ c.description }}
                            </div>
                        </td>
                        <td class="px-3 py-2">
                            <Badge variant="outline">{{ valueText(c) }}</Badge>
                            <span class="ml-1 text-xs text-muted-foreground">
                                {{ c.type === 'percent' ? '(porcentaje)' : '(fijo)' }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-muted-foreground">
                            {{ c.min_subtotal ?? '—' }}
                        </td>
                        <td class="px-3 py-2">
                            {{ c.used_count }} / {{ c.max_uses ?? '∞' }}
                        </td>
                        <td class="px-3 py-2 text-xs text-muted-foreground">
                            <div v-if="c.starts_at">Desde: {{ c.starts_at.substring(0, 10) }}</div>
                            <div v-if="c.ends_at">Hasta: {{ c.ends_at.substring(0, 10) }}</div>
                            <span v-if="!c.starts_at && !c.ends_at">Siempre</span>
                        </td>
                        <td class="px-3 py-2">
                            <Badge :variant="c.is_active ? 'default' : 'secondary'">
                                {{ c.is_active ? 'Activo' : 'Inactivo' }}
                            </Badge>
                        </td>
                        <td class="px-3 py-2 text-right">
                            <Button variant="ghost" size="icon-sm" as-child>
                                <Link :href="`/admin/coupons/${c.id}/edit`">
                                    <Pencil class="size-4" />
                                </Link>
                            </Button>
                            <Button variant="ghost" size="icon-sm" @click="destroy(c)">
                                <Trash2 class="size-4 text-destructive" />
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="!coupons.data.length">
                        <td colspan="7" class="px-3 py-6 text-center text-muted-foreground">
                            Aún no hay cupones. ¡Crea el primero!
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :links="coupons.links" :from="coupons.from" :to="coupons.to" :total="coupons.total" />
    </div>
</template>
