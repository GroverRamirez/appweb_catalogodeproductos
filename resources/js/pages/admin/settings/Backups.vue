<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { DatabaseBackup, Download, Info, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Configuración', href: '/admin/settings' },
            { title: 'Copias de seguridad', href: '/admin/settings/backups' },
        ],
    }),
});

type Backup = {
    name: string;
    size: number;
    created_at: string;
};

defineProps<{ backups: Backup[] }>();

const creating = ref(false);

const create = () => {
    router.post(
        '/admin/settings/backups',
        {},
        {
            preserveScroll: true,
            onStart: () => (creating.value = true),
            onFinish: () => (creating.value = false),
        },
    );
};

const destroy = (b: Backup) => {
    if (!confirm(`¿Eliminar la copia "${b.name}"? No se puede deshacer.`)) {
        return;
    }

    router.delete(`/admin/settings/backups/${encodeURIComponent(b.name)}`, {
        preserveScroll: true,
    });
};

const formatSize = (bytes: number): string => {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
};

const formatDate = (iso: string): string =>
    new Date(iso).toLocaleString('es-BO', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
</script>

<template>
    <Head title="Copias de seguridad" />

    <div class="mx-auto max-w-6xl space-y-3 p-3 md:p-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Copias de seguridad</h1>
                <p class="text-sm text-muted-foreground">
                    Respaldo de la base de datos (productos, clientes, ventas,
                    compras y configuración).
                </p>
            </div>
            <div class="flex gap-2">
                <Button variant="outline" as-child>
                    <Link href="/admin/settings">Volver a configuración</Link>
                </Button>
                <Button :disabled="creating" @click="create">
                    <DatabaseBackup class="size-4" />
                    {{ creating ? 'Generando…' : 'Generar copia ahora' }}
                </Button>
            </div>
        </div>

        <Card class="gap-3 py-4">
            <CardHeader class="pb-0">
                <CardTitle>Copias guardadas</CardTitle>
                <CardDescription>
                    Se guardan en una carpeta privada del servidor. Descarga las
                    importantes y guárdalas fuera del equipo (nube, disco
                    externo).
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div
                    v-if="backups.length === 0"
                    class="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground"
                >
                    Todavía no hay copias. Genera la primera con el botón de
                    arriba.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-b text-left text-xs text-muted-foreground uppercase"
                            >
                                <th class="py-2 pr-3 font-medium">Archivo</th>
                                <th class="py-2 pr-3 font-medium">Fecha</th>
                                <th class="py-2 pr-3 text-right font-medium">
                                    Tamaño
                                </th>
                                <th class="py-2 text-right font-medium">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="b in backups"
                                :key="b.name"
                                class="border-b last:border-0"
                            >
                                <td class="py-2 pr-3 font-mono text-xs">
                                    {{ b.name }}
                                </td>
                                <td class="py-2 pr-3 whitespace-nowrap">
                                    {{ formatDate(b.created_at) }}
                                </td>
                                <td
                                    class="py-2 pr-3 text-right whitespace-nowrap"
                                >
                                    {{ formatSize(b.size) }}
                                </td>
                                <td class="py-2 text-right whitespace-nowrap">
                                    <div class="inline-flex gap-1">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            as-child
                                        >
                                            <a
                                                :href="`/admin/settings/backups/${encodeURIComponent(b.name)}`"
                                            >
                                                <Download class="size-4" />
                                                Descargar
                                            </a>
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="text-destructive"
                                            @click="destroy(b)"
                                        >
                                            <Trash2 class="size-4" />
                                            <span class="sr-only"
                                                >Eliminar</span
                                            >
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <div
            class="flex gap-2 rounded-md border bg-muted/40 p-3 text-xs text-muted-foreground"
        >
            <Info class="mt-0.5 size-4 shrink-0" />
            <p>
                La restauración no se hace desde el panel a propósito: reemplaza
                todos los datos y conviene hacerla con la tienda detenida. Los
                pasos están en DEPLOY.md, sección 6 (en la versión instalada en
                Windows, en el LEEME de su carpeta).
            </p>
        </div>
    </div>
</template>
