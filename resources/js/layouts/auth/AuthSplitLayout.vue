<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CheckCircle2 } from 'lucide-vue-next';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const features = [
    'Control total de productos, precios y stock',
    'Pedidos y consultas directo por WhatsApp',
    'Roles y permisos a medida para tu equipo',
];
</script>

<template>
    <div class="grid min-h-svh lg:grid-cols-2">
        <!-- Panel de marca: solo en pantallas grandes -->
        <div
            class="relative hidden flex-col justify-between overflow-hidden bg-[hsl(222_33%_13%)] px-12 py-12 text-white lg:flex xl:px-16"
        >
            <!-- Marca de agua tipográfica, muy sutil: aporta textura sin degradados -->
            <AppLogoIcon
                class="pointer-events-none absolute -right-24 -bottom-24 size-[26rem] text-white/[0.04]"
                aria-hidden="true"
            />

            <Link
                :href="home()"
                class="relative flex w-fit items-center gap-3 font-semibold"
            >
                <span
                    class="flex size-11 items-center justify-center rounded-[8px] bg-white/10 text-white ring-1 ring-white/15"
                >
                    <AppLogoIcon class="size-7 fill-current" />
                </span>
                <span class="text-lg">Mi Catálogo</span>
            </Link>

            <div class="relative max-w-md space-y-8">
                <h2
                    class="font-display text-3xl leading-[1.15] font-bold tracking-tight xl:text-4xl"
                >
                    Gestioná tu catálogo con todo bajo control
                </h2>
                <ul class="space-y-4">
                    <li
                        v-for="feature in features"
                        :key="feature"
                        class="flex items-start gap-3 text-sm leading-relaxed text-white/75"
                    >
                        <CheckCircle2
                            class="mt-0.5 size-5 shrink-0 text-white/60"
                        />
                        {{ feature }}
                    </li>
                </ul>
            </div>

            <p class="relative text-xs text-white/40">
                © {{ new Date().getFullYear() }} Mi Catálogo — Panel
                administrativo
            </p>
        </div>

        <!-- Panel de formulario -->
        <div
            class="flex min-h-svh flex-col justify-center bg-background px-6 py-10 text-foreground sm:px-8"
        >
            <div class="mx-auto w-full max-w-[380px]">
                <Link
                    :href="home()"
                    class="mb-8 flex w-fit items-center gap-3 font-semibold lg:hidden"
                >
                    <span
                        class="flex size-10 items-center justify-center rounded-[8px] bg-brand text-brand-foreground"
                    >
                        <AppLogoIcon class="size-6 fill-current" />
                    </span>
                    <span>Mi Catálogo</span>
                </Link>

                <div class="mb-8 space-y-2">
                    <h1
                        v-if="title"
                        class="text-2xl font-bold tracking-tight"
                    >
                        {{ title }}
                    </h1>
                    <p
                        v-if="description"
                        class="text-sm leading-6 text-muted-foreground"
                    >
                        {{ description }}
                    </p>
                </div>

                <slot />
            </div>
        </div>
    </div>
</template>
