<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';

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
    <section
        v-if="banners.length"
        class="relative overflow-hidden border-b border-border bg-[hsl(222_33%_13%)]"
        @mouseenter="stop"
        @mouseleave="start"
    >
        <div
            class="relative min-h-[200px] w-full md:min-h-[260px] lg:min-h-[320px]"
        >
            <transition-group name="fade">
                <div
                    v-for="(b, i) in banners"
                    v-show="i === current"
                    :key="b.id"
                    class="absolute inset-0"
                >
                    <img
                        :src="b.image_url"
                        :alt="b.title ?? ''"
                        class="h-full min-h-[200px] w-full object-cover md:min-h-[260px] lg:min-h-[320px]"
                    />
                    <!-- Velo en degradado: oscurece solo donde va el texto (izquierda) y
                         deja la imagen del producto a la derecha sin opacar. -->
                    <div
                        class="absolute inset-0 bg-gradient-to-r from-[hsl(222_33%_8%)]/85 via-[hsl(222_33%_10%)]/40 to-transparent md:via-30% md:to-55%"
                    ></div>

                    <div
                        class="absolute inset-0 mx-auto flex max-w-[1400px] flex-col items-start justify-center gap-3 px-6 text-white md:px-10 lg:px-12"
                    >
                        <h2
                            v-if="b.title"
                            class="max-w-3xl font-display text-2xl leading-[1.1] font-bold tracking-tight md:text-4xl"
                        >
                            {{ b.title }}
                        </h2>
                        <p
                            v-if="b.subtitle"
                            class="max-w-2xl text-sm leading-relaxed text-white/80 md:text-base"
                        >
                            {{ b.subtitle }}
                        </p>
                        <Button
                            v-if="b.link"
                            as-child
                            size="lg"
                            class="mt-2 rounded-md bg-white font-semibold text-[hsl(222_33%_13%)] hover:bg-white/90"
                        >
                            <a
                                v-if="isExternal(b.link)"
                                :href="b.link"
                                target="_blank"
                            >
                                {{ b.cta_text ?? 'Ver más' }}
                                <ArrowRight class="size-4" />
                            </a>
                            <Link v-else :href="b.link">
                                {{ b.cta_text ?? 'Ver más' }}
                                <ArrowRight class="size-4" />
                            </Link>
                        </Button>
                    </div>
                </div>
            </transition-group>
        </div>

        <button
            v-if="banners.length > 1"
            type="button"
            class="absolute top-1/2 left-4 grid size-10 -translate-y-1/2 place-items-center rounded-md border border-white/20 bg-black/30 text-white transition hover:bg-black/50 md:left-6"
            @click="prev"
            aria-label="Anterior"
        >
            <ChevronLeft class="size-5" />
        </button>
        <button
            v-if="banners.length > 1"
            type="button"
            class="absolute top-1/2 right-4 grid size-10 -translate-y-1/2 place-items-center rounded-md border border-white/20 bg-black/30 text-white transition hover:bg-black/50 md:right-6"
            @click="next"
            aria-label="Siguiente"
        >
            <ChevronRight class="size-5" />
        </button>

        <div
            v-if="banners.length > 1"
            class="absolute bottom-5 left-1/2 flex -translate-x-1/2 gap-2"
        >
            <button
                v-for="(_, i) in banners"
                :key="i"
                type="button"
                @click="goTo(i)"
                class="h-1.5 rounded-full bg-white/40 transition-all hover:bg-white/70"
                :class="i === current ? 'w-8 bg-white' : 'w-4'"
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
