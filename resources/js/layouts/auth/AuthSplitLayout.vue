<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Boxes, CheckCircle2, ShieldCheck, UsersRound } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
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
const storeTagline = computed(
    () =>
        store.value?.tagline ||
        'Administrá tu catálogo, inventario y equipo desde un solo lugar.',
);
const logoUrl = computed(() => store.value?.logo_url ?? null);

const highlights = [
    { icon: Boxes, label: 'Catálogo', detail: 'Siempre organizado' },
    { icon: ShieldCheck, label: 'Seguridad', detail: 'Acceso protegido' },
    { icon: UsersRound, label: 'Equipo', detail: 'Permisos por rol' },
];
</script>

<template>
    <div
        class="grid min-h-svh lg:grid-cols-[minmax(0,0.92fr)_minmax(30rem,1.08fr)]"
    >
        <!-- Panel de marca: solo en pantallas grandes -->
        <div
            class="relative hidden overflow-hidden bg-[linear-gradient(145deg,#050b18_0%,#081a33_46%,#161342_100%)] px-10 py-10 text-white lg:flex lg:flex-col xl:px-16 xl:py-12"
        >
            <div
                class="pointer-events-none absolute inset-0 bg-[linear-gradient(rgba(56,189,248,0.055)_1px,transparent_1px),linear-gradient(90deg,rgba(56,189,248,0.055)_1px,transparent_1px)] [mask-image:linear-gradient(to_bottom,black_0%,transparent_78%)] bg-[size:42px_42px]"
                aria-hidden="true"
            />
            <div
                class="pointer-events-none absolute top-[18%] -left-28 size-80 rounded-full bg-cyan-400/10 blur-3xl"
                aria-hidden="true"
            />
            <div
                class="pointer-events-none absolute right-[4%] bottom-[8%] size-96 rounded-full bg-violet-500/10 blur-3xl"
                aria-hidden="true"
            />
            <div
                class="pointer-events-none absolute -top-24 -left-24 size-72 rounded-full border border-cyan-300/[0.12]"
                aria-hidden="true"
            />
            <div
                class="pointer-events-none absolute -top-12 -left-12 size-72 rounded-full border border-violet-300/[0.08]"
                aria-hidden="true"
            />
            <AppLogoIcon
                class-name="pointer-events-none absolute -right-24 -bottom-24 size-[28rem] text-cyan-200/[0.04]"
                aria-hidden="true"
            />

            <Link
                :href="home()"
                class="relative z-10 flex w-fit items-center gap-3"
            >
                <span
                    class="flex size-12 items-center justify-center overflow-hidden rounded-xl bg-white/95 text-slate-950 shadow-lg ring-1 shadow-cyan-950/40 ring-cyan-300/20"
                >
                    <img
                        v-if="logoUrl"
                        :src="logoUrl"
                        :alt="storeName"
                        class="size-9 object-contain"
                    />
                    <AppLogoIcon v-else class-name="size-7" />
                </span>
                <span class="grid gap-0.5">
                    <span class="text-lg leading-tight font-bold">{{
                        storeName
                    }}</span>
                    <span
                        class="text-[10px] font-semibold tracking-[0.18em] text-cyan-200/60 uppercase"
                    >
                        Panel administrativo
                    </span>
                </span>
            </Link>

            <div class="relative z-10 my-auto max-w-xl py-10">
                <span
                    class="mb-5 inline-flex items-center gap-2 rounded-full border border-cyan-300/20 bg-cyan-400/10 px-3 py-1.5 text-xs font-semibold tracking-wide text-cyan-100 uppercase shadow-[0_0_30px_-12px_rgba(34,211,238,0.8)]"
                >
                    <ShieldCheck class="size-3.5" />
                    Gestión comercial segura
                </span>
                <h2
                    class="max-w-lg font-display text-4xl leading-[1.08] font-bold tracking-tight xl:text-5xl"
                >
                    Todo tu negocio,
                    <span class="block text-cyan-300">
                        organizado en un solo panel
                    </span>
                </h2>
                <p class="mt-5 max-w-lg text-base leading-7 text-slate-300/80">
                    {{ storeTagline }}
                </p>

                <ul class="mt-8 grid gap-3">
                    <li
                        v-for="feature in features"
                        :key="feature"
                        class="flex items-center gap-3 text-sm leading-relaxed text-slate-200/80"
                    >
                        <CheckCircle2 class="size-5 shrink-0 text-cyan-300" />
                        {{ feature }}
                    </li>
                </ul>
            </div>

            <div class="relative z-10">
                <div class="grid grid-cols-3 gap-3">
                    <div
                        v-for="item in highlights"
                        :key="item.label"
                        class="rounded-xl border border-cyan-200/10 bg-slate-950/25 p-3.5 shadow-[inset_0_1px_0_rgba(255,255,255,0.04)] backdrop-blur-sm"
                    >
                        <component
                            :is="item.icon"
                            class="mb-3 size-5 text-cyan-300"
                        />
                        <p class="text-sm font-semibold">{{ item.label }}</p>
                        <p class="mt-0.5 text-xs text-slate-400">
                            {{ item.detail }}
                        </p>
                    </div>
                </div>
                <p class="mt-5 text-xs text-cyan-100/35">
                    © {{ new Date().getFullYear() }} {{ storeName }}
                </p>
            </div>
        </div>

        <!-- Panel de formulario -->
        <div
            class="relative flex min-h-svh flex-col justify-center overflow-hidden bg-muted/40 px-5 py-8 text-foreground sm:px-8 lg:py-10"
        >
            <div
                class="pointer-events-none absolute top-0 right-0 size-80 translate-x-1/2 -translate-y-1/2 rounded-full border border-primary/[0.08]"
                aria-hidden="true"
            />
            <div
                class="pointer-events-none absolute top-8 right-8 size-80 translate-x-1/2 -translate-y-1/2 rounded-full border border-primary/[0.05]"
                aria-hidden="true"
            />

            <div class="relative mx-auto w-full max-w-[460px]">
                <Link
                    :href="home()"
                    class="mb-6 flex w-fit items-center gap-3 lg:hidden"
                >
                    <span
                        class="flex size-10 items-center justify-center overflow-hidden rounded-lg bg-primary text-primary-foreground shadow-sm"
                    >
                        <img
                            v-if="logoUrl"
                            :src="logoUrl"
                            :alt="storeName"
                            class="size-8 object-contain"
                        />
                        <AppLogoIcon v-else class-name="size-6" />
                    </span>
                    <span class="font-bold">{{ storeName }}</span>
                </Link>

                <section
                    class="rounded-2xl border border-border/80 bg-card p-6 shadow-[0_24px_70px_-36px_hsl(222_33%_14%/0.45)] sm:p-8"
                >
                    <div class="mb-7 text-center">
                        <h1
                            v-if="title"
                            class="text-3xl font-bold tracking-tight"
                        >
                            {{ title }}
                        </h1>
                        <p
                            v-if="description"
                            class="mx-auto mt-2 max-w-sm text-sm leading-6 text-muted-foreground"
                        >
                            {{ description }}
                        </p>
                    </div>

                    <slot />
                </section>

                <p
                    class="mt-5 flex items-center justify-center gap-2 text-center text-xs text-muted-foreground"
                >
                    <ShieldCheck class="size-3.5" />
                    Conexión protegida para el personal autorizado
                </p>
            </div>
        </div>
    </div>
</template>
