<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { formatRate } from '@/lib/catalog';
import type { StoreSettings } from '@/lib/catalog';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();
const store = computed(() => page.props.store as StoreSettings);

// Los precios del catálogo se publican convertidos: el staff necesita ver con
// qué cotización se está vendiendo sin entrar a Configuración.
const rateTitle = computed(() =>
    store.value.exchange_rate_source === 'bcb'
        ? `Cotización oficial del BCB${store.value.exchange_rate_updated_at ? ` · actualizada el ${store.value.exchange_rate_updated_at}` : ''}`
        : 'Cotización cargada a mano en Configuración',
);
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-border/80 bg-card/95 px-6 shadow-xs transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex flex-1 items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <span
            v-if="store?.exchange_rate"
            class="hidden text-xs text-muted-foreground sm:inline-flex sm:items-center sm:gap-1"
            :title="rateTitle"
        >
            TC
            <strong class="font-semibold text-foreground">
                {{ formatRate(store.exchange_rate) }} Bs
            </strong>
            / USD
        </span>
    </header>
</template>
