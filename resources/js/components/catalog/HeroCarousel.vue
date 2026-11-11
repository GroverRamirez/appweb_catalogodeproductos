<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref } from 'vue';

type Banner = {
    id: number;
    title: string | null;
    subtitle: string | null;
    image_url: string;
    link: string | null;
    cta_text: string | null;
};

const props = withDefaults(
    defineProps<{
        banners: Banner[];
        intervalMs?: number;
    }>(),
    { intervalMs: 7000 },
);

const current = ref(0);
let timer: ReturnType<typeof setInterval> | null = null;

const next = () => {
    current.value = (current.value + 1) % props.banners.length;
};
const prev = () => {
    current.value =
        (current.value - 1 + props.banners.length) % props.banners.length;
};
const goTo = (i: number) => {
    current.value = i;
};

const start = () => {
    if (props.banners.length > 1) {
        timer = setInterval(next, props.intervalMs);
    }
};
const stop = () => {
    if (timer) {
        clearInterval(timer);
        timer = null;
    }
};

onMounted(start);
onBeforeUnmount(stop);

const isExternal = (url: string) => /^https?:\/\//.test(url);
</script>

<template>
    <!-- Banner a todo el ancho de la ventana, como en Digicorp: la imagen se
     muestra completa (sin recortes) y la altura sigue su proporción. -->
    <section
        v-if="banners.length"
        class="relative w-full overflow-hidden bg-[hsl(224_36%_10%)]"
        @mouseenter="stop"
        @mouseleave="start"
    >
        <!-- Todas las diapositivas comparten la misma celda de la grilla: la
         altura la marca la imagen visible. -->
        <div class="grid">
            <transition-group name="fade">
                <div
                    v-for="(b, i) in banners"
                    v-show="i === current"
                    :key="b.id"
                    class="col-start-1 row-start-1"
                >
                    <a
                        v-if="b.link && isExternal(b.link)"
                        :href="b.link"
                        target="_blank"
                        class="block"
                    >
                        <img
                            :src="b.image_url"
                            :alt="b.title ?? b.cta_text ?? ''"
                            class="block h-auto w-full"
                        />
                    </a>
                    <Link v-else-if="b.link" :href="b.link" class="block">
                        <img
                            :src="b.image_url"
                            :alt="b.title ?? b.cta_text ?? ''"
                            class="block h-auto w-full"
                        />
                    </Link>
                    <img
                        v-else
                        :src="b.image_url"
                        :alt="b.title ?? ''"
                        class="block h-auto w-full"
                    />
                </div>
            </transition-group>
        </div>

        <button
            v-if="banners.length > 1"
            type="button"
            class="absolute top-1/2 left-2 hidden size-8 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-[hsl(224_36%_10%)] shadow-sm ring-1 ring-black/10 transition hover:bg-white sm:grid sm:left-4 md:left-6 md:size-11"
            @click="prev"
            aria-label="Anterior"
        >
            <ChevronLeft class="size-4 md:size-5" />
        </button>
        <button
            v-if="banners.length > 1"
            type="button"
            class="absolute top-1/2 right-2 hidden size-8 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-[hsl(224_36%_10%)] shadow-sm ring-1 ring-black/10 transition hover:bg-white sm:grid sm:right-4 md:right-6 md:size-11"
            @click="next"
            aria-label="Siguiente"
        >
            <ChevronRight class="size-4 md:size-5" />
        </button>

        <div
            v-if="banners.length > 1"
            class="absolute bottom-2 left-1/2 flex -translate-x-1/2 gap-1.5 md:bottom-4 md:gap-2"
        >
            <button
                v-for="(_, i) in banners"
                :key="i"
                type="button"
                @click="goTo(i)"
                class="h-1 rounded-full bg-white/50 transition-all hover:bg-white/80 md:h-1.5"
                :class="i === current ? 'w-6 bg-white md:w-8' : 'w-3 md:w-4'"
                :aria-label="`Ir al banner ${i + 1}`"
            />
        </div>
    </section>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.6s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
