<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Bell, Volume2, VolumeX } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';

const page = usePage();
const { can } = usePermissions();

const pendingCount = computed<number>(() => {
    return Number(page.props.pendingInquiries ?? 0);
});

const isMuted = ref(
    typeof window !== 'undefined' &&
        localStorage.getItem('admin_orders_alert_muted') === 'true',
);

const toggleMute = () => {
    isMuted.value = !isMuted.value;

    if (typeof window !== 'undefined') {
        localStorage.setItem(
            'admin_orders_alert_muted',
            isMuted.value ? 'true' : 'false',
        );
    }
};

const hasNewAlert = ref(false);
const prevCount = ref<number | null>(null);

function playChime() {
    if (isMuted.value) {
        return;
    }

    try {
        const AudioCtx =
            window.AudioContext ||
            (window as unknown as { webkitAudioContext: typeof AudioContext })
                .webkitAudioContext;

        if (!AudioCtx) {
            return;
        }

        const ctx = new AudioCtx();
        const now = ctx.currentTime;

        const osc1 = ctx.createOscillator();
        const gain1 = ctx.createGain();
        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(587.33, now); // D5
        gain1.gain.setValueAtTime(0.2, now);
        gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.35);
        osc1.connect(gain1);
        gain1.connect(ctx.destination);
        osc1.start(now);
        osc1.stop(now + 0.35);

        const osc2 = ctx.createOscillator();
        const gain2 = ctx.createGain();
        osc2.type = 'sine';
        osc2.frequency.setValueAtTime(880, now + 0.12); // A5
        gain2.gain.setValueAtTime(0.25, now + 0.12);
        gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.55);
        osc2.connect(gain2);
        gain2.connect(ctx.destination);
        osc2.start(now + 0.12);
        osc2.stop(now + 0.55);
    } catch {
        // Ignorar si el navegador bloquea audio antes de interacción
    }
}

watch(pendingCount, (newVal, oldVal) => {
    if (oldVal !== undefined && newVal > oldVal) {
        hasNewAlert.value = true;
        playChime();
        toast.info('¡Nuevo pedido / consulta web recibido!', {
            description: `Hay ${newVal} consulta(s) pendiente(s) por atender en el panel.`,
        });
        setTimeout(() => {
            hasNewAlert.value = false;
        }, 8000);
    }

    prevCount.value = newVal;
});

let pollTimer: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    prevCount.value = pendingCount.value;

    if (can('inquiries.view')) {
        // Polling cada 30 segundos
        pollTimer = setInterval(() => {
            router.reload({
                only: ['pendingInquiries'],
            });
        }, 30000);
    }
});

onBeforeUnmount(() => {
    if (pollTimer) {
        clearInterval(pollTimer);
    }
});
</script>

<template>
    <div v-if="can('inquiries.view')" class="flex items-center gap-1">
        <!-- Botón de silenciar/activar sonido -->
        <button
            type="button"
            class="grid size-8 place-items-center rounded-lg text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
            :title="
                isMuted
                    ? 'Alertas de sonido silenciadas (clic para activar)'
                    : 'Alertas de sonido activas (clic para silenciar)'
            "
            @click="toggleMute"
        >
            <VolumeX v-if="isMuted" class="size-4 text-muted-foreground/70" />
            <Volume2
                v-else
                class="size-4 text-emerald-600 dark:text-emerald-400"
            />
        </button>

        <!-- Campana de notificaciones con enlace a consultas pendientes -->
        <Button
            variant="ghost"
            size="icon"
            as-child
            class="relative size-8 rounded-lg"
            :class="{ 'animate-bounce': hasNewAlert }"
            title="Ver pedidos y consultas pendientes"
        >
            <Link href="/admin/inquiries?status=pendiente">
                <Bell
                    class="size-4.5"
                    :class="
                        hasNewAlert ? 'text-primary' : 'text-muted-foreground'
                    "
                />

                <!-- Indicador de alerta o badge de conteo -->
                <span
                    v-if="pendingCount > 0"
                    class="absolute -top-1 -right-1 flex size-4 items-center justify-center rounded-full bg-destructive font-mono text-[10px] font-bold text-white shadow-xs"
                >
                    {{ pendingCount > 9 ? '9+' : pendingCount }}
                </span>

                <!-- Anillo pulsante si acaba de entrar una orden -->
                <span
                    v-if="hasNewAlert"
                    class="absolute -top-1 -right-1 size-4 animate-ping rounded-full bg-destructive/75"
                />
            </Link>
        </Button>
    </div>
</template>
