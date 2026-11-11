<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

type PageLink = { url: string | null; label: string; active: boolean };

defineProps<{
    links: PageLink[];
    from?: number | null;
    to?: number | null;
    total?: number;
}>();

const paginationLabel = (label: string) =>
    label
        .replaceAll('&laquo;', '«')
        .replaceAll('&raquo;', '»')
        .replaceAll('&amp;', '&');
</script>

<template>
    <div
        v-if="links && links.length > 3"
        class="flex flex-wrap items-center justify-between gap-3 border-t pt-3 text-sm"
    >
        <p class="text-muted-foreground">
            Mostrando {{ from ?? 0 }}–{{ to ?? 0 }} de {{ total ?? 0 }}
        </p>
        <nav class="flex flex-wrap gap-1">
            <template v-for="(link, i) in links" :key="i">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    preserve-state
                    class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border bg-card px-3 transition hover:bg-accent"
                    :class="{
                        'border-primary bg-primary text-primary-foreground hover:bg-primary/90':
                            link.active,
                    }"
                >
                    {{ paginationLabel(link.label) }}
                </Link>
                <span
                    v-else
                    class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border px-3 text-muted-foreground opacity-60"
                >
                    {{ paginationLabel(link.label) }}
                </span>
            </template>
        </nav>
    </div>
</template>
