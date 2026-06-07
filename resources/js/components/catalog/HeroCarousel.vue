<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    ChevronLeft,
    ChevronRight,
    Sparkles,
} from 'lucide-vue-next';
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
        class="relative overflow-hidden border-b border-border/70 bg-emerald-950"
        @mouseenter="stop"
        @mouseleave="start"
    >
        <div
            class="relative min-h-[420px] w-full bg-emerald-950 md:min-h-[500px] lg:min-h-[540px]"
        >
            <transition-group name="kenburns">
                <div
                    v-for="(b, i) in banners"
                    v-show="i === current"
                    :key="b.id"
                    class="absolute inset-0"
                >
                    <img
                        :src="b.image_url"
                        :alt="b.title ?? ''"
                        class="h-full min-h-[420px] w-full object-cover md:min-h-[500px] lg:min-h-[540px]"
                        :class="{ 'animate-kenburns': i === current }"
                    />
                    <!-- Gradient overlay multi-stop, más dramático -->
                    <div
                        class="absolute inset-y-0 left-1/2 flex w-full max-w-7xl -translate-x-1/2 flex-col items-start justify-center gap-5 px-6 py-14 text-white md:px-10 lg:px-12"
                        style="
                            background: linear-gradient(
                                105deg,
                                hsl(160 60% 7% / 0.92) 0%,
                                hsl(160 48% 9% / 0.76) 38%,
                                hsl(160 30% 8% / 0.26) 72%,
                                hsl(160 30% 8% / 0.36) 100%
                            );
                        "
                    />
                    <div
                        class="absolute inset-0 bg-gradient-to-t from-emerald-950/45 via-transparent to-black/10"
                    ></div>

                    <div class="absolute inset-0">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full border border-white/35 bg-white/14 px-3.5 py-1.5 text-[11px] font-extrabold tracking-widest text-white uppercase shadow-sm backdrop-blur"
                        >
                            <Sparkles class="size-3" /> Edición especial
                        </span>
                        <h2
                            v-if="b.title"
                            class="max-w-3xl font-display text-4xl leading-[1.02] font-extrabold tracking-tight drop-shadow-lg md:text-6xl lg:text-7xl"
                        >
                            {{ b.title }}
                        </h2>
                        <p
                            v-if="b.subtitle"
                            class="max-w-2xl text-base leading-relaxed text-white/88 drop-shadow md:text-lg"
                        >
                            {{ b.subtitle }}
                        </p>
                        <Button
                            v-if="b.link"
                            as-child
                            size="lg"
                            class="mt-2 rounded-full border-transparent bg-white px-6 font-bold text-emerald-950 shadow-lg shadow-black/20 hover:bg-white/92"
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
            class="absolute top-1/2 left-4 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full border border-white/25 bg-white/16 text-white shadow-lg backdrop-blur-md transition hover:bg-white hover:text-foreground md:left-6"
            @click="prev"
            aria-label="Anterior"
        >
            <ChevronLeft class="size-5" />
        </button>
        <button
            v-if="banners.length > 1"
            type="button"
            class="absolute top-1/2 right-4 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full border border-white/25 bg-white/16 text-white shadow-lg backdrop-blur-md transition hover:bg-white hover:text-foreground md:right-6"
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
                class="h-1.5 rounded-full bg-white/40 transition-all hover:bg-white/80"
                :class="i === current ? 'w-10 bg-white shadow-lg' : 'w-4'"
                :aria-label="`Ir al banner ${i + 1}`"
            />
        </div>

        <!-- Indicador de auto-play -->
        <div
            v-if="banners.length > 1"
            :key="current"
            class="absolute bottom-0 left-0 h-[3px] bg-accent2 shadow-lg"
            :style="{
                animation: `progress-bar ${intervalMs}ms linear forwards`,
            }"
        ></div>
    </section>
</template>

<style scoped>
.kenburns-enter-active,
.kenburns-leave-active {
    transition: opacity 0.9s ease;
}
.kenburns-enter-from,
.kenburns-leave-to {
    opacity: 0;
}

@keyframes ken-burns {
    0% {
        transform: scale(1) translate(0, 0);
    }
    100% {
        transform: scale(1.1) translate(-1%, -1%);
    }
}

.animate-kenburns {
    animation: ken-burns 8s ease-out both;
}

@keyframes progress-bar {
    from {
        width: 0;
    }
    to {
        width: 100%;
    }
}
</style>
