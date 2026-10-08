<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Boxes,
    CheckCircle2,
    ShieldCheck,
    UsersRound,
} from 'lucide-vue-next';
import { computed } from 'vue';
import type { StoreSettings } from '@/lib/catalog';
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

const page = usePage();
const store = computed(() => page.props.store as StoreSettings | undefined);
const storeName = computed(() => store.value?.name || 'Mi Catálogo');
const storeInitial = computed(() => storeName.value.charAt(0).toUpperCase());
const storeTagline = computed(
    () =>
        store.value?.tagline ||
        'Administra tu catálogo, inventario y equipo desde un solo lugar.',
);
const logoUrl = computed(() => store.value?.logo_url ?? null);

const highlights = [
    { icon: Boxes, label: 'Catálogo', detail: 'Siempre organizado' },
    { icon: ShieldCheck, label: 'Seguridad', detail: 'Acceso protegido' },
    { icon: UsersRound, label: 'Equipo', detail: 'Permisos por rol' },
];
</script>

<template>
    <!-- Mismo lenguaje visual que el catálogo: superficies planas, azul de
         marca, esquinas redondeadas y sombras suaves. Sin degradados, brillos
         ni efectos de vidrio. -->
    <div class="auth-shell min-h-svh bg-background p-3 text-foreground sm:p-4">
        <div
            class="mx-auto grid min-h-[calc(100svh-1.5rem)] max-w-[1400px] gap-3 sm:min-h-[calc(100svh-2rem)] sm:gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(30rem,1fr)]"
        >
            <!-- Panel de marca: solo en pantallas grandes -->
            <aside
                class="hidden flex-col rounded-3xl bg-primary p-10 text-primary-foreground lg:flex xl:p-14"
            >
                <Link :href="home()" class="flex w-fit items-center gap-3">
                    <span
                        class="grid size-11 place-items-center overflow-hidden rounded-xl bg-white font-display text-lg font-extrabold text-primary"
                    >
                        <img
                            v-if="logoUrl"
                            :src="logoUrl"
                            :alt="storeName"
                            class="size-8 object-contain"
                        />
                        <template v-else>{{ storeInitial }}</template>
                    </span>
                    <span class="grid gap-0.5">
                        <span
                            class="font-display text-lg leading-tight font-extrabold"
                        >
                            {{ storeName }}
                        </span>
                        <span class="text-xs font-medium text-white/70">
                            Panel administrativo
                        </span>
                    </span>
                </Link>

                <div class="my-auto max-w-xl py-12">
                    <span
                        class="mb-6 inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1.5 text-xs font-semibold"
                    >
                        <ShieldCheck class="size-3.5" />
                        Gestión comercial segura
                    </span>
                    <h2
                        class="font-display text-4xl leading-[1.05] font-extrabold tracking-tight xl:text-[3.25rem]"
                    >
                        Todo tu negocio, organizado en un solo panel
                    </h2>
                    <p class="mt-5 max-w-lg text-base leading-7 text-white/75">
                        {{ storeTagline }}
                    </p>

                    <ul class="mt-8 grid gap-3">
                        <li
                            v-for="feature in features"
                            :key="feature"
                            class="flex items-center gap-3 text-sm text-white/90"
                        >
                            <CheckCircle2 class="size-5 shrink-0" />
                            {{ feature }}
                        </li>
                    </ul>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div
                        v-for="item in highlights"
                        :key="item.label"
                        class="rounded-2xl bg-white/10 p-4"
                    >
                        <component :is="item.icon" class="mb-3 size-5" />
                        <p class="text-sm font-semibold">{{ item.label }}</p>
                        <p class="mt-0.5 text-xs text-white/65">
                            {{ item.detail }}
                        </p>
                    </div>
                </div>
                <p class="mt-6 text-xs text-white/50">
                    © {{ new Date().getFullYear() }} {{ storeName }}
                </p>
            </aside>

            <!-- Panel de formulario -->
            <main class="flex flex-col justify-center px-2 py-8 sm:px-6">
                <div class="mx-auto w-full max-w-[440px]">
                    <Link
                        :href="home()"
                        class="mb-8 flex w-fit items-center gap-2.5 lg:hidden"
                    >
                        <span
                            class="grid size-10 place-items-center overflow-hidden rounded-xl bg-primary font-display text-base font-extrabold text-primary-foreground"
                        >
                            <img
                                v-if="logoUrl"
                                :src="logoUrl"
                                :alt="storeName"
                                class="size-7 object-contain"
                            />
                            <template v-else>{{ storeInitial }}</template>
                        </span>
                        <span class="font-display text-lg font-extrabold">{{
                            storeName
                        }}</span>
                    </Link>

                    <section
                        class="rounded-3xl bg-card p-6 ring-1 elev-2 ring-border/70 sm:p-9"
                    >
                        <div class="mb-7">
                            <h1
                                v-if="title"
                                class="font-display text-3xl font-extrabold tracking-tight"
                            >
                                {{ title }}
                            </h1>
                            <p
                                v-if="description"
                                class="mt-2 text-sm leading-6 text-muted-foreground"
                            >
                                {{ description }}
                            </p>
                        </div>

                        <slot />
                    </section>

                    <div
                        class="mt-6 flex flex-wrap items-center justify-between gap-3 px-1 text-xs text-muted-foreground"
                    >
                        <Link
                            :href="home()"
                            class="inline-flex items-center gap-1.5 font-medium transition hover:text-primary"
                        >
                            <ArrowLeft class="size-3.5" />
                            Volver al catálogo
                        </Link>
                        <span class="inline-flex items-center gap-1.5">
                            <ShieldCheck class="size-3.5" />
                            Conexión protegida
                        </span>
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>

<style>
/* Las pantallas de acceso usan los colores claros del catálogo, aunque el
   panel esté en modo oscuro. */
.auth-shell {
    --background: hsl(220 23% 97%);
    --foreground: hsl(224 32% 12%);
    --card: hsl(0 0% 100%);
    --card-foreground: hsl(224 32% 12%);
    --popover: hsl(0 0% 100%);
    --popover-foreground: hsl(224 32% 12%);
    --primary: hsl(221 83% 50%);
    --primary-foreground: hsl(0 0% 100%);
    --secondary: hsl(220 20% 94%);
    --secondary-foreground: hsl(224 30% 18%);
    --muted: hsl(220 20% 96%);
    --muted-foreground: hsl(220 9% 44%);
    --accent: hsl(221 85% 96%);
    --accent-foreground: hsl(221 83% 38%);
    --border: hsl(220 16% 91%);
    --input: hsl(220 14% 86%);
    --ring: hsl(221 83% 50%);
    --shadow-lift:
        0 2px 4px hsl(224 32% 12% / 0.04),
        0 16px 32px -12px hsl(224 32% 12% / 0.16);
}
</style>
