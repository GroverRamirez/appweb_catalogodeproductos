<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Trash2, Upload } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Configuración', href: '/admin/settings' },
        ],
    }),
});

type Setting = {
    key: string;
    value: string | null;
    group: string;
    label: string | null;
    type: string;
    url?: string | null;
};

const props = defineProps<{ settings: Setting[] }>();

const grouped = computed(() => {
    const groups: Record<string, Setting[]> = {};

    for (const s of props.settings) {
        if (!groups[s.group]) {
            groups[s.group] = [];
        }

        groups[s.group].push(s);
    }

    return groups;
});

const form = useForm({
    settings: props.settings.map((s) => ({
        key: s.key,
        type: s.type,
        value:
            s.type === 'boolean'
                ? s.value === '1' || s.value === 'true'
                : s.value,
        file: null as File | null,
        delete: false as boolean,
    })),
    _method: 'patch',
});

const valueAt = (key: string) => form.settings.find((s) => s.key === key)!;

const previews = ref<Record<string, string | null>>({});

const onFileChange = (key: string, e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null;
    valueAt(key).file = file;
    valueAt(key).delete = false;

    if (file) {
        const reader = new FileReader();
        reader.onload = (ev) => {
            previews.value[key] = ev.target?.result as string;
        };
        reader.readAsDataURL(file);
    } else {
        previews.value[key] = null;
    }
};

const markDelete = (key: string) => {
    valueAt(key).delete = true;
    valueAt(key).file = null;
    previews.value[key] = null;
};

const currentImage = (s: Setting): string | null => {
    if (previews.value[s.key]) {
        return previews.value[s.key]!;
    }

    if (valueAt(s.key).delete) {
        return null;
    }

    return s.url ?? null;
};

const submit = () => {
    router.post('/admin/settings', form.data() as any, {
        forceFormData: true,
        preserveScroll: true,
    });
};

const groupTitle = (g: string) => {
    const map: Record<string, string> = {
        general: 'General',
        contacto: 'Contacto',
        catalogo: 'Catálogo',
    };

    return map[g] ?? g;
};
</script>

<template>
    <Head title="Configuración" />

    <div class="mx-auto max-w-5xl space-y-3 p-3 md:p-4">
        <div>
            <h1 class="text-xl font-semibold">Configuración</h1>
            <p class="text-sm text-muted-foreground">
                Datos generales, contacto y opciones del catálogo público.
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-3">
            <Card
                v-for="(items, group) in grouped"
                :key="group"
                class="gap-3 py-4"
            >
                <CardHeader class="pb-0">
                    <CardTitle>{{ groupTitle(group) }}</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-x-4 gap-y-3 sm:grid-cols-2">
                    <div v-for="s in items" :key="s.key">
                        <Label :for="s.key">{{ s.label ?? s.key }}</Label>

                        <!-- Image / file -->
                        <div v-if="s.type === 'image'" class="space-y-2">
                            <div class="flex items-center gap-3">
                                <div
                                    class="grid h-20 w-20 place-items-center overflow-hidden rounded-md border bg-muted/40"
                                >
                                    <img
                                        v-if="currentImage(s)"
                                        :src="currentImage(s)!"
                                        :alt="s.label ?? s.key"
                                        class="h-full w-full object-contain"
                                    />
                                    <span
                                        v-else
                                        class="px-2 text-center text-[10px] text-muted-foreground"
                                    >
                                        Sin imagen
                                    </span>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <label
                                        :for="`file_${s.key}`"
                                        class="inline-flex cursor-pointer items-center gap-2 rounded-md border px-3 py-1.5 text-xs hover:bg-accent"
                                    >
                                        <Upload class="size-3" /> Subir imagen
                                    </label>
                                    <input
                                        :id="`file_${s.key}`"
                                        type="file"
                                        accept="image/*"
                                        class="hidden"
                                        @change="onFileChange(s.key, $event)"
                                    />
                                    <button
                                        v-if="currentImage(s)"
                                        type="button"
                                        class="inline-flex items-center gap-1 text-xs text-destructive hover:underline"
                                        @click="markDelete(s.key)"
                                    >
                                        <Trash2 class="size-3" /> Quitar
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div
                            v-else-if="s.type === 'boolean'"
                            class="flex items-center gap-2"
                        >
                            <Checkbox
                                :id="s.key"
                                v-model="valueAt(s.key).value as any"
                            />
                            <span class="text-sm text-muted-foreground">
                                {{ valueAt(s.key).value ? 'Sí' : 'No' }}
                            </span>
                        </div>

                        <textarea
                            v-else-if="s.type === 'text'"
                            :id="s.key"
                            v-model="valueAt(s.key).value as any"
                            rows="3"
                            class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                        ></textarea>

                        <Input
                            v-else
                            :id="s.key"
                            :type="s.type === 'number' ? 'number' : 'text'"
                            v-model="valueAt(s.key).value as any"
                        />
                    </div>
                </CardContent>
            </Card>

            <div class="flex justify-end">
                <Button type="submit" :disabled="form.processing">
                    Guardar cambios
                </Button>
            </div>
        </form>
    </div>
</template>
