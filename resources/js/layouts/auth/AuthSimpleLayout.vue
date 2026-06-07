<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="min-h-svh bg-background text-foreground">
        <main
            class="gradient-brand-soft flex min-h-svh items-center justify-center px-6 py-10 sm:px-8"
        >
            <div class="w-full max-w-[420px]">
                <Link
                    :href="home()"
                    class="mx-auto mb-7 flex w-fit items-center gap-3 font-semibold"
                >
                    <span
                        class="gradient-brand flex size-11 items-center justify-center rounded-[8px] text-white shadow-sm"
                    >
                        <AppLogoIcon class="size-7 fill-current" />
                    </span>
                    <span>Mi Catálogo</span>
                </Link>

                <section
                    class="rounded-[8px] border border-border bg-card p-6 shadow-sm sm:p-8 dark:bg-card/95"
                >
                    <div class="mb-7 space-y-2 text-center">
                        <h1
                            class="text-2xl font-bold tracking-normal"
                            v-if="title"
                        >
                            {{ title }}
                        </h1>
                        <p
                            class="text-sm leading-6 text-muted-foreground"
                            v-if="description"
                        >
                            {{ description }}
                        </p>
                    </div>

                    <slot />
                </section>
            </div>
        </main>
    </div>
</template>
