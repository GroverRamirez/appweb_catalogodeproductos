<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { login, logout, register } from '@/routes';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const roles = computed(() => (page.props.auth?.roles ?? []) as string[]);
const isStaff = computed(
    () => roles.value.includes('admin') || roles.value.includes('vendedor'),
);

const handleLogout = () => {
    router.post(logout().url, {}, { onSuccess: () => router.flushAll() });
};
</script>

<template>
    <Head title="Catálogo de Productos">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>
    <div
        class="flex min-h-screen flex-col items-center bg-[#FDFDFC] p-6 text-[#1b1b18] lg:justify-center lg:p-8 dark:bg-[#0a0a0a]"
    >
        <header
            class="mb-6 w-full max-w-[335px] text-sm not-has-[nav]:hidden lg:max-w-4xl"
        >
            <nav class="flex items-center justify-end gap-3">
                <template v-if="user">
                    <span class="text-sm text-muted-foreground">
                        Hola, {{ user.name }}
                    </span>
                    <Link
                        v-if="isStaff"
                        href="/admin"
                        class="inline-block rounded-sm border border-[#19140035] px-4 py-1.5 text-sm leading-normal text-[#1b1b18] hover:border-[#1915014a] dark:border-[#3E3E3A] dark:text-[#EDEDEC] dark:hover:border-[#62605b]"
                    >
                        Panel
                    </Link>
                    <button
                        type="button"
                        @click="handleLogout"
                        class="inline-block rounded-sm border border-[#19140035] px-4 py-1.5 text-sm leading-normal text-[#1b1b18] hover:border-[#1915014a] dark:border-[#3E3E3A] dark:text-[#EDEDEC] dark:hover:border-[#62605b]"
                    >
                        Cerrar sesión
                    </button>
                </template>
                <template v-else>
                    <Link
                        :href="login()"
                        class="inline-block rounded-sm border border-transparent px-5 py-1.5 text-sm leading-normal text-[#1b1b18] hover:border-[#19140035] dark:text-[#EDEDEC] dark:hover:border-[#3E3E3A]"
                    >
                        Iniciar sesión
                    </Link>
                    <Link
                        :href="register()"
                        class="inline-block rounded-sm border border-[#19140035] px-5 py-1.5 text-sm leading-normal text-[#1b1b18] hover:border-[#1915014a] dark:border-[#3E3E3A] dark:text-[#EDEDEC] dark:hover:border-[#62605b]"
                    >
                        Registrarme
                    </Link>
                </template>
            </nav>
        </header>

        <main
            class="flex w-full max-w-3xl flex-col items-center text-center"
        >
            <h1 class="mb-3 text-3xl font-semibold lg:text-5xl">
                Mi Catálogo
            </h1>
            <p class="mb-8 max-w-xl text-[#706f6c] dark:text-[#A1A09A]">
                Aplicación web de catálogo de productos. La base de datos está
                lista (Laravel 13 + Inertia + Vue 3 + MySQL + Spatie
                Permission). El catálogo público se construye en la siguiente
                iteración.
            </p>

            <div class="flex flex-wrap items-center justify-center gap-3">
                <Link
                    v-if="!user"
                    :href="login()"
                    class="inline-block rounded-md bg-[#1b1b18] px-5 py-2 text-sm font-medium text-white hover:bg-black dark:bg-[#eeeeec] dark:text-[#1C1C1A] dark:hover:bg-white"
                >
                    Entrar al panel
                </Link>
                <Link
                    v-else-if="isStaff"
                    href="/admin"
                    class="inline-block rounded-md bg-[#1b1b18] px-5 py-2 text-sm font-medium text-white hover:bg-black dark:bg-[#eeeeec] dark:text-[#1C1C1A] dark:hover:bg-white"
                >
                    Ir al panel
                </Link>
            </div>
        </main>
    </div>
</template>
