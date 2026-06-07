import { router } from '@inertiajs/vue3';
import { nextTick, onMounted, onUnmounted } from 'vue';

/**
 * Adds `class="in"` to `.reveal` elements when they enter the viewport.
 */
export function useReveal(rootSelector: string = 'body') {
    let observer: IntersectionObserver | null = null;
    let removeFinishListener: (() => void) | null = null;

    const init = () => {
        if (
            typeof window === 'undefined' ||
            !('IntersectionObserver' in window)
        ) {
            return;
        }

        observer?.disconnect();

        observer = new IntersectionObserver(
            (entries) => {
                for (const entry of entries) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('in');
                        observer?.unobserve(entry.target);
                    }
                }
            },
            { threshold: 0.12, rootMargin: '0px 0px -40px 0px' },
        );

        const root = document.querySelector(rootSelector) ?? document.body;

        root.querySelectorAll('.reveal:not(.in)').forEach((el) => {
            observer?.observe(el);
        });
    };

    const rescan = async () => {
        await nextTick();
        requestAnimationFrame(init);
    };

    onMounted(() => {
        void rescan();

        removeFinishListener = router.on('finish', () => {
            void rescan();
        });
    });

    onUnmounted(() => {
        removeFinishListener?.();
        observer?.disconnect();
        removeFinishListener = null;
        observer = null;
    });

    return { rescan };
}
