<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const storeName = computed(
    () => (page.props.store as any)?.name ?? 'Mi Catálogo',
);
const logoUrl = computed(() => (page.props.store as any)?.logo_url ?? null);
</script>

<template>
    <div
        class="flex aspect-square size-9 items-center justify-center rounded-md bg-primary text-sm font-bold text-primary-foreground"
    >
        <img
            v-if="logoUrl"
            :src="logoUrl"
            :alt="storeName"
            class="h-6 w-6 object-contain"
        />
        <span v-else>{{ storeName.charAt(0).toUpperCase() }}</span>
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span
            class="mb-0 truncate font-display leading-tight font-semibold text-sidebar-foreground"
        >
            {{ storeName }}
        </span>
        <span
            class="truncate text-[10px] font-medium tracking-wider text-sidebar-foreground/60 uppercase"
        >
            Panel admin
        </span>
    </div>
</template>
