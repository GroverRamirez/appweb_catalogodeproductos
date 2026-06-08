<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    label: string;
    value: number | string;
    hint?: string;
    icon?: Component;
    tone?: 'default' | 'warn' | 'danger' | 'success';
}>();

const toneClasses: Record<string, string> = {
    default: 'bg-primary text-primary-foreground shadow-sm shadow-primary/20',
    warn: 'bg-amber-600 text-white shadow-sm shadow-amber-600/20 dark:bg-amber-500/20 dark:text-amber-200',
    danger: 'bg-red-700 text-white shadow-sm shadow-red-700/20 dark:bg-red-500/20 dark:text-red-200',
    success:
        'bg-emerald-700 text-white shadow-sm shadow-emerald-700/20 dark:bg-emerald-500/20 dark:text-emerald-200',
};

const cardBorder: Record<string, string> = {
    default: 'border-border/90',
    warn: 'border-amber-500/45 bg-amber-50/45 dark:border-amber-900/40 dark:bg-card',
    danger: 'border-red-500/40 bg-red-50/40 dark:border-red-900/40 dark:bg-card',
    success:
        'border-emerald-600/35 bg-emerald-50/45 dark:border-emerald-900/40 dark:bg-card',
};
</script>

<template>
    <div
        class="admin-card group relative overflow-hidden rounded-xl border p-5 transition-all duration-300 hover:-translate-y-0.5 hover:border-primary/35 hover:shadow-lg"
        :class="cardBorder[tone ?? 'default']"
    >
        <!-- Glow decorativo en hover -->
        <div
            aria-hidden="true"
            class="pointer-events-none absolute -top-12 -right-12 size-32 rounded-full opacity-0 blur-2xl transition-opacity duration-500 group-hover:opacity-100"
            :class="{
                'bg-primary/20': !tone || tone === 'default',
                'bg-amber-500/24': tone === 'warn',
                'bg-red-600/20': tone === 'danger',
                'bg-emerald-600/20': tone === 'success',
            }"
        ></div>

        <div class="relative flex items-start justify-between">
            <div>
                <p
                    class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                >
                    {{ label }}
                </p>
                <p
                    class="mt-2 font-display text-3xl leading-tight font-extrabold tracking-tight text-foreground md:text-4xl"
                >
                    {{ value }}
                </p>
                <p v-if="hint" class="mt-1 text-sm text-muted-foreground">
                    {{ hint }}
                </p>
            </div>
            <span
                v-if="icon"
                class="grid size-11 shrink-0 place-items-center rounded-xl transition-transform duration-300 group-hover:scale-105"
                :class="toneClasses[tone ?? 'default']"
            >
                <component :is="icon" class="size-5" />
            </span>
        </div>
    </div>
</template>
