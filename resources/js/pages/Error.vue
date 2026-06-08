<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { AlertTriangle, Home, RefreshCcw, ArrowLeft } from 'lucide-vue-next';
import { computed } from 'vue';
import type { StoreSettings } from '@/lib/catalog';

// Sin layout: app.ts retorna null para name === 'Error' en el layout resolver
const { status } = defineProps<{ status: number }>();

const page = usePage();
const store = computed(() => page.props.store as StoreSettings | undefined);

// ── Contenido contextual por código de error ───────────────────────────────

type ErrorContent = {
    code: string;
    title: string;
    description: string;
    reload?: boolean;
};

const content = computed((): ErrorContent => {
    switch (status) {
        case 404:
            return {
                code: '404',
                title: 'Página no encontrada',
                description:
                    'La página que buscas no existe o fue movida. Verifica la URL o vuelve al inicio.',
            };
        case 403:
            return {
                code: '403',
                title: 'Acceso denegado',
                description:
                    'No tienes permiso para ver este contenido. Inicia sesión con una cuenta autorizada.',
            };
        case 419:
            return {
                code: '419',
                title: 'Sesión expirada',
                description:
                    'Tu sesión venció por inactividad. Recarga la página para continuar.',
                reload: true,
            };
        case 429:
            return {
                code: '429',
                title: 'Demasiadas solicitudes',
                description:
                    'Enviaste demasiadas solicitudes en poco tiempo. Espera unos segundos e inténtalo de nuevo.',
                reload: true,
            };
        case 500:
            return {
                code: '500',
                title: 'Error del servidor',
                description:
                    'Algo salió mal de nuestro lado. Ya estamos trabajando para resolverlo.',
                reload: true,
            };
        case 503:
            return {
                code: '503',
                title: 'Servicio no disponible',
                description:
                    'El sitio está en mantenimiento. Vuelve en unos minutos.',
                reload: true,
            };
        default:
            return {
                code: String(status),
                title: 'Algo salió mal',
                description:
                    'Ocurrió un error inesperado. Vuelve al inicio e intenta de nuevo.',
            };
    }
});

const storeName = computed(() => store.value?.name ?? '');
const logoUrl = computed(() => store.value?.logo_url ?? null);

const goHome = () => router.visit('/catalogo');
const reload = () => window.location.reload();
const goBack = () => window.history.back();
</script>

<template>
    <Head :title="`${content.code} — ${content.title}`" />

    <!--
        Página de error completamente standalone.
        Usa las variables CSS del tema (--background, --foreground, --primary, etc.)
        definidas en app.css para que respete el modo oscuro automáticamente.
    -->
    <div
        class="error-page"
        style="
            min-height: 100dvh;
            background-color: var(--background);
            color: var(--foreground);
            font-family: var(--font-sans, ui-sans-serif, system-ui, sans-serif);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        "
    >
        <!-- Decoración de fondo sutil -->
        <div
            aria-hidden="true"
            style="
                position: absolute;
                inset: 0;
                background: radial-gradient(
                    ellipse 80% 60% at 50% -20%,
                    hsl(155 60% 50% / 0.08) 0%,
                    transparent 70%
                );
                pointer-events: none;
            "
        />

        <!-- Contenido principal -->
        <div
            style="
                position: relative;
                z-index: 1;
                max-width: 480px;
                width: 100%;
            "
        >
            <!-- Logo de la tienda (si está disponible) -->
            <div style="margin-bottom: 2rem">
                <a href="/catalogo" aria-label="Ir al catálogo">
                    <img
                        v-if="logoUrl"
                        :src="logoUrl"
                        :alt="storeName"
                        style="
                            height: 2.5rem;
                            margin: 0 auto;
                            object-fit: contain;
                        "
                    />
                    <span
                        v-else-if="storeName"
                        style="
                            font-size: 1.125rem;
                            font-weight: 600;
                            color: var(--foreground);
                            opacity: 0.7;
                        "
                    >
                        {{ storeName }}
                    </span>
                </a>
            </div>

            <!-- Código de error grande -->
            <div
                style="
                    font-size: clamp(5rem, 20vw, 8rem);
                    font-weight: 800;
                    line-height: 1;
                    margin-bottom: 0.5rem;
                    background: linear-gradient(
                        135deg,
                        hsl(155 60% 45%) 0%,
                        hsl(160 50% 35%) 100%
                    );
                    -webkit-background-clip: text;
                    background-clip: text;
                    color: transparent;
                    letter-spacing: -0.04em;
                "
                aria-hidden="true"
            >
                {{ content.code }}
            </div>

            <!-- Icono de advertencia (solo para errores de servidor) -->
            <div
                v-if="status >= 500"
                style="
                    display: flex;
                    justify-content: center;
                    margin-bottom: 1rem;
                "
            >
                <AlertTriangle
                    style="
                        width: 2rem;
                        height: 2rem;
                        color: hsl(38 95% 55%);
                        opacity: 0.9;
                    "
                />
            </div>

            <!-- Título -->
            <h1
                style="
                    font-size: clamp(1.25rem, 4vw, 1.75rem);
                    font-weight: 700;
                    margin-bottom: 0.75rem;
                    color: var(--foreground);
                "
            >
                {{ content.title }}
            </h1>

            <!-- Descripción -->
            <p
                style="
                    font-size: 1rem;
                    line-height: 1.625;
                    color: var(--muted-foreground, hsl(160 15% 45%));
                    margin-bottom: 2rem;
                    max-width: 38ch;
                    margin-left: auto;
                    margin-right: auto;
                "
            >
                {{ content.description }}
            </p>

            <!-- Acciones -->
            <div
                style="
                    display: flex;
                    flex-wrap: wrap;
                    gap: 0.75rem;
                    justify-content: center;
                "
            >
                <!-- Recargar (419 / 429 / 500 / 503) -->
                <button
                    v-if="content.reload"
                    type="button"
                    @click="reload"
                    style="
                        display: inline-flex;
                        align-items: center;
                        gap: 0.5rem;
                        padding: 0.625rem 1.25rem;
                        font-size: 0.9375rem;
                        font-weight: 600;
                        border-radius: 0.5rem;
                        cursor: pointer;
                        border: none;
                        background: hsl(155 55% 40%);
                        color: #fff;
                        transition: background 0.15s;
                    "
                    onmouseenter="this.style.background = 'hsl(155,55%,35%)'"
                    onmouseleave="this.style.background = 'hsl(155,55%,40%)'"
                >
                    <RefreshCcw style="width: 1rem; height: 1rem" />
                    Recargar página
                </button>

                <!-- Ir al catálogo -->
                <button
                    type="button"
                    @click="goHome"
                    style="
                        display: inline-flex;
                        align-items: center;
                        gap: 0.5rem;
                        padding: 0.625rem 1.25rem;
                        font-size: 0.9375rem;
                        font-weight: 600;
                        border-radius: 0.5rem;
                        cursor: pointer;
                        transition:
                            background 0.15s,
                            color 0.15s;
                        background: transparent;
                        border: 1.5px solid var(--border, hsl(155 20% 80%));
                        color: var(--foreground);
                    "
                    onmouseenter="
                        this.style.background = 'var(--muted, hsl(155 15% 92%))'
                    "
                    onmouseleave="this.style.background = 'transparent'"
                >
                    <Home style="width: 1rem; height: 1rem" />
                    Ir al catálogo
                </button>

                <!-- Volver (solo si no es reload el primario) -->
                <button
                    v-if="!content.reload"
                    type="button"
                    @click="goBack"
                    style="
                        display: inline-flex;
                        align-items: center;
                        gap: 0.5rem;
                        padding: 0.625rem 1.25rem;
                        font-size: 0.9375rem;
                        font-weight: 600;
                        border-radius: 0.5rem;
                        cursor: pointer;
                        background: transparent;
                        border: 1.5px solid var(--border, hsl(155 20% 80%));
                        color: var(--foreground);
                        transition: background 0.15s;
                    "
                    onmouseenter="
                        this.style.background = 'var(--muted, hsl(155 15% 92%))'
                    "
                    onmouseleave="this.style.background = 'transparent'"
                >
                    <ArrowLeft style="width: 1rem; height: 1rem" />
                    Volver
                </button>
            </div>

            <!-- Código de error en texto para accesibilidad (visualmente oculto) -->
            <p class="sr-only">Código de error HTTP: {{ status }}</p>
        </div>

        <!-- Pie de página discreto -->
        <p
            v-if="storeName"
            style="
                position: absolute;
                bottom: 1.5rem;
                left: 0;
                right: 0;
                text-align: center;
                font-size: 0.75rem;
                color: var(--muted-foreground, hsl(160 15% 50%));
                opacity: 0.6;
            "
        >
            © {{ storeName }}
        </p>
    </div>
</template>
