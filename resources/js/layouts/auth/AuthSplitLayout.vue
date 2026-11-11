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
    <!-- En escritorio ocupa exactamente el alto de la ventana (sin scroll):
         los tamaños del panel de marca se ajustan al alto disponible y los
         bloques secundarios se ocultan en pantallas bajas. -->
    <div
        class="auth-shell grid min-h-svh bg-background text-foreground lg:h-svh lg:grid-cols-[minmax(0,0.95fr)_minmax(30rem,1.05fr)] lg:overflow-hidden"
    >
        <!-- Panel de marca (oscuro): solo en pantallas grandes -->
        <aside
            class="relative hidden overflow-hidden bg-[linear-gradient(145deg,#050b18_0%,#081a33_46%,#161342_100%)] px-10 py-[clamp(1.5rem,4.5vh,3rem)] text-white lg:flex lg:flex-col xl:px-16"
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

            <Link
                :href="home()"
                class="relative z-10 flex w-fit items-center gap-3"
            >
                <span
                    class="grid size-11 place-items-center overflow-hidden rounded-xl bg-white font-display text-lg font-extrabold text-[#081a33] ring-1 ring-cyan-300/20"
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
                    <span
                        class="text-[10px] font-semibold tracking-[0.18em] text-cyan-200/60 uppercase"
                    >
                        Panel administrativo
                    </span>
                </span>
            </Link>

            <div
                class="relative z-10 my-auto max-w-xl py-[clamp(1rem,4vh,2.5rem)]"
            >
                <span
                    class="mb-[clamp(0.75rem,2.5vh,1.5rem)] inline-flex items-center gap-2 rounded-full border border-cyan-300/20 bg-cyan-400/10 px-3 py-1.5 text-xs font-semibold tracking-wide text-cyan-100 uppercase"
                >
                    <ShieldCheck class="size-3.5" />
                    Gestión comercial segura
                </span>
                <h2
                    class="font-display text-[clamp(2rem,5.6vh,3.25rem)] leading-[1.05] font-extrabold tracking-tight"
                >
                    Todo tu negocio,
                    <span class="block text-cyan-300">
                        organizado en un solo panel
                    </span>
                </h2>
                <p
                    class="mt-[clamp(0.75rem,2.2vh,1.25rem)] max-w-lg text-base leading-7 text-slate-300/80"
                >
                    {{ storeTagline }}
                </p>

                <ul
                    class="mt-[clamp(1rem,3.5vh,2rem)] grid gap-[clamp(0.4rem,1.3vh,0.75rem)]"
                >
                    <li
                        v-for="feature in features"
                        :key="feature"
                        class="flex items-center gap-3 text-sm text-slate-200/80"
                    >
                        <CheckCircle2 class="size-5 shrink-0 text-cyan-300" />
                        {{ feature }}
                    </li>
                </ul>
            </div>

            <div class="relative z-10">
                <!-- En ventanas bajas se ocultan para no forzar scroll -->
                <div
                    class="grid grid-cols-3 gap-3 [@media(max-height:760px)]:hidden"
                >
                    <div
                        v-for="item in highlights"
                        :key="item.label"
                        class="rounded-2xl border border-cyan-200/10 bg-white/[0.04] p-4"
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
                <p
                    class="mt-[clamp(0.75rem,2.5vh,1.5rem)] text-xs text-cyan-100/40"
                >
                    © {{ new Date().getFullYear() }} {{ storeName }}
                </p>
            </div>
        </aside>

        <!-- Panel de formulario -->
        <main
            class="flex flex-col justify-center px-4 py-8 sm:px-8 lg:overflow-y-auto lg:py-[clamp(1rem,4vh,2.5rem)]"
        >
            <div class="mx-auto w-full max-w-[440px]">
                <Link
                    :href="home()"
                    class="mb-8 flex w-fit items-center gap-2.5 lg:hidden"
                >
                    <span
                        class="grid size-10 place-items-center overflow-hidden rounded-xl bg-[#081a33] font-display text-base font-extrabold text-white"
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
                    class="rounded-3xl bg-card p-6 ring-1 elev-2 ring-border/70 sm:p-8"
                >
                    <div class="mb-6">
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
                    class="mt-5 flex flex-wrap items-center justify-between gap-3 px-1 text-xs text-muted-foreground"
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
