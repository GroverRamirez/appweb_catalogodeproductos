<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Download, Eye, MessageSquare, Search, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Consultas', href: '/admin/inquiries' },
        ],
    }),
});

type Inquiry = {
    id: number;
    customer_name: string;
    customer_phone: string;
    customer_email: string | null;
    source: string;
    status: string;
    items_count: number;
    created_at: string;
};

const props = defineProps<{
    inquiries: {
        data: Inquiry[];
        links: { url: string | null; label: string; active: boolean }[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: { q?: string; status?: string };
    statuses: string[];
}>();

const q = ref(props.filters.q ?? '');
const status = ref(props.filters.status ?? '');

let timer: ReturnType<typeof setTimeout> | null = null;
watch([q, status], () => {
    if (timer) {
        clearTimeout(timer);
    }

    timer = setTimeout(() => {
        router.get(
            '/admin/inquiries',
            { q: q.value, status: status.value },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

const destroy = (inquiry: Inquiry) => {
    if (!confirm(`Eliminar la consulta de "${inquiry.customer_name}"?`)) {
        return;
    }

    router.delete(`/admin/inquiries/${inquiry.id}`, { preserveScroll: true });
};

const statusVariant = (statusName: string) => {
    if (statusName === 'pendiente') {
        return 'secondary';
    }

    if (statusName === 'vendido') {
        return 'default';
    }

    if (statusName === 'cerrado') {
        return 'outline';
    }

    return 'default';
};

const formatDate = (date: string) => new Date(date).toLocaleString();

const exportUrl = computed(() => {
    const params = new URLSearchParams({
        q: props.filters.q ?? '',
        status: props.filters.status ?? '',
    });
    const query = params.toString();

    return query
        ? `/admin/inquiries/export.csv?${query}`
        : '/admin/inquiries/export.csv';
});
</script>

<template>
    <Head title="Consultas" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            :icon="MessageSquare"
            eyebrow="Operación"
            title="Consultas / pedidos"
            description="Mensajes recibidos de clientes (WhatsApp, web, teléfono)."
        >
            <template #actions>
                <Button variant="outline" as-child class="rounded-full">
                    <a :href="exportUrl">
                        <Download class="size-4" /> Exportar CSV
                    </a>
                </Button>
            </template>
        </PageHeader>

        <div class="flex flex-wrap items-center gap-3">
            <div class="relative max-w-sm flex-1">
                <Search
                    class="absolute top-1/2 left-2 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="q"
                    placeholder="Buscar por nombre o telefono..."
                    class="pl-8"
                />
            </div>
            <select
                v-model="status"
                class="h-9 rounded-md border bg-background px-3 text-sm"
            >
                <option value="">Todos los estados</option>
                <option
                    v-for="statusName in statuses"
                    :key="statusName"
                    :value="statusName"
                >
                    {{ statusName }}
                </option>
            </select>
        </div>

        <div class="overflow-x-auto rounded-md border">
            <table class="w-full text-sm">
                <thead
                    class="bg-muted/50 text-left text-xs text-muted-foreground uppercase"
                >
                    <tr>
                        <th class="px-3 py-2">Cliente</th>
                        <th class="px-3 py-2">Telefono</th>
                        <th class="px-3 py-2">Origen</th>
                        <th class="px-3 py-2 text-right">Items</th>
                        <th class="px-3 py-2">Estado</th>
                        <th class="px-3 py-2">Fecha</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr
                        v-for="inquiry in inquiries.data"
                        :key="inquiry.id"
                        class="hover:bg-muted/30"
                    >
                        <td class="px-3 py-2">
                            <div class="font-medium">
                                {{ inquiry.customer_name }}
                            </div>
                            <div
                                v-if="inquiry.customer_email"
                                class="text-xs text-muted-foreground"
                            >
                                {{ inquiry.customer_email }}
                            </div>
                        </td>
                        <td class="px-3 py-2">{{ inquiry.customer_phone }}</td>
                        <td class="px-3 py-2 text-xs">{{ inquiry.source }}</td>
                        <td class="px-3 py-2 text-right">
                            {{ inquiry.items_count }}
                        </td>
                        <td class="px-3 py-2">
                            <Badge
                                :variant="statusVariant(inquiry.status) as any"
                            >
                                {{ inquiry.status }}
                            </Badge>
                        </td>
                        <td class="px-3 py-2 text-xs text-muted-foreground">
                            {{ formatDate(inquiry.created_at) }}
                        </td>
                        <td class="px-3 py-2 text-right">
                            <Button variant="ghost" size="icon-sm" as-child>
                                <Link :href="`/admin/inquiries/${inquiry.id}`">
                                    <Eye class="size-4" />
                                </Link>
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                @click="destroy(inquiry)"
                            >
                                <Trash2 class="size-4 text-destructive" />
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="!inquiries.data.length">
                        <td
                            colspan="7"
                            class="px-3 py-6 text-center text-muted-foreground"
                        >
                            Sin consultas todavia.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination
            :links="inquiries.links"
            :from="inquiries.from"
            :to="inquiries.to"
            :total="inquiries.total"
        />
    </div>
</template>
