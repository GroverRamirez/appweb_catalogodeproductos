<script setup lang="ts">
import { Sparkles } from 'lucide-vue-next';
import type { Component } from 'vue';

defineProps<{
    icon?: Component;
    eyebrow?: string;
    title: string;
    description?: string;
}>();
</script>

<template>
    <div class="admin-card relative overflow-hidden rounded-xl border p-5 md:p-6">
        <div class="absolute inset-0 gradient-brand-soft opacity-60"></div>
        <div
            aria-hidden="true"
            class="pointer-events-none absolute -right-10 -top-10 size-32 rounded-full bg-brand/15 blur-3xl"
        ></div>
        <div class="relative flex flex-wrap items-end justify-between gap-4">
            <div class="flex items-start gap-3">
                <span
                    v-if="icon"
                    class="grid size-11 shrink-0 place-items-center rounded-xl gradient-brand text-white shadow-md glow-brand"
                >
                    <component :is="icon" class="size-5" />
                </span>
                <div>
                    <p
                        v-if="eyebrow"
                        class="mb-0.5 inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-widest text-brand"
                    >
                        <Sparkles class="size-3" /> {{ eyebrow }}
                    </p>
                    <h1 class="font-display text-2xl font-bold leading-tight md:text-3xl">
                        {{ title }}
                    </h1>
                    <p v-if="description" class="mt-1 text-sm text-muted-foreground">
                        {{ description }}
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <slot name="actions" />
            </div>
        </div>
    </div>
</template>
