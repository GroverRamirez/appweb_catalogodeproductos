<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Sparkles } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage();
const storeName = computed(
    () => (page.props.store as any)?.name ?? 'Mi Catálogo',
);
const logoUrl = computed(() => (page.props.store as any)?.logo_url ?? null);
</script>

<template>
    <div
        class="gradient-brand glow-brand flex aspect-square size-9 items-center justify-center rounded-xl text-white shadow-md"
    >
        <img
            v-if="logoUrl"
            :src="logoUrl"
            :alt="storeName"
            class="h-6 w-6 object-contain"
        />
        <Sparkles v-else class="size-4.5" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span
            class="mb-0 truncate font-display font-bold leading-tight text-sidebar-foreground"
        >
            {{ storeName }}
        </span>
        <span
            class="truncate text-[10px] font-semibold uppercase tracking-wider text-sidebar-foreground/65"
        >
            Panel admin
        </span>
    </div>
</template>
