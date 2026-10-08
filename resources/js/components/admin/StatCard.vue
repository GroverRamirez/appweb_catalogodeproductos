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
        class="admin-card group relative min-h-36 overflow-hidden rounded-2xl border p-4 transition-all duration-200 hover:-translate-y-0.5 hover:[box-shadow:var(--shadow-lift)] xl:p-5"
        :class="cardBorder[tone ?? 'default']"
    >
        <div class="flex items-center justify-between gap-3">
            <p
                class="min-w-0 truncate text-xs font-semibold tracking-wide text-muted-foreground uppercase"
            >
                {{ label }}
            </p>
            <span
                v-if="icon"
                class="grid size-9 shrink-0 place-items-center rounded-xl transition-transform duration-200 group-hover:scale-105"
                :class="toneClasses[tone ?? 'default']"
            >
                <component :is="icon" class="size-[18px]" />
            </span>
        </div>
        <!-- El valor ocupa todo el ancho: los montos grandes en Bs ya no
             chocan con el ícono. -->
        <p
            class="mt-2 font-display text-[clamp(1.35rem,1.75vw,2rem)] leading-tight font-extrabold tracking-tight whitespace-nowrap text-foreground tabular-nums"
        >
            {{ value }}
        </p>
        <p
            v-if="hint"
            class="mt-1 text-sm leading-relaxed text-muted-foreground"
        >
            {{ hint }}
        </p>
    </div>
</template>
