<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    icon?: Component;
    eyebrow?: string;
    title: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex flex-wrap items-end justify-between gap-4 border-b border-border pb-5"
    >
        <div class="flex items-start gap-3">
            <span
                v-if="icon"
                class="grid size-10 shrink-0 place-items-center rounded-md bg-accent text-primary"
            >
                <component :is="icon" class="size-5" />
            </span>
            <div>
                <p
                    v-if="eyebrow"
                    class="mb-0.5 text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                >
                    {{ eyebrow }}
                </p>
                <h1
                    class="font-display text-xl leading-tight font-bold md:text-2xl"
                >
                    {{ title }}
                </h1>
                <p
                    v-if="description"
                    class="mt-1 text-sm text-muted-foreground"
                >
                    {{ description }}
                </p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
