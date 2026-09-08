<script setup lang="ts">
import { onClickOutside } from '@vueuse/core';
import { Check, ChevronsUpDown, Search, X } from 'lucide-vue-next';
import { computed, nextTick, ref } from 'vue';
import { cn } from '@/lib/utils';

/**
 * Combobox buscable genérico. La lógica vive acá una sola vez: la usan el
 * selector de productos y el de proveedores.
 */
export type ComboOption = {
    id: number;
    /** Texto principal de la opción */
    label: string;
    /** Línea secundaria opcional (código, stock, contacto...) */
    sublabel?: string;
    /** Texto extra que también se busca pero no se muestra */
    search?: string;
};

/** Tope de opciones renderizadas: con listas grandes, pintar cientos de nodos
 *  en cada tecla es lo que hace sentir lento al buscador. */
const MAX_VISIBLE = 50;

const props = withDefaults(
    defineProps<{
        options: ComboOption[];
        modelValue: number | null;
        placeholder?: string;
        disabled?: boolean;
        ariaInvalid?: boolean;
        class?: string;
    }>(),
    {
        placeholder: 'Buscar…',
        disabled: false,
        ariaInvalid: false,
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: number | null): void;
    (e: 'select', option: ComboOption): void;
}>();

const containerRef = ref<HTMLElement | null>(null);
// Apunta al <input> nativo: una instancia de componente Vue no tiene focus().
const inputRef = ref<HTMLInputElement | null>(null);
const open = ref(false);
const query = ref('');
const highlightIndex = ref(0);

const DIACRITICOS = new RegExp('[̀-ͯ]', 'g');

/** Quita acentos y pasa a minúsculas: buscar "camara" encuentra "Cámara". */
const normalize = (value: string) =>
    value.normalize('NFD').replace(DIACRITICOS, '').toLowerCase();

const selected = computed(
    () => props.options.find((o) => o.id === props.modelValue) ?? null,
);

// Índice precalculado: se rehace solo si cambia la lista, no en cada tecla.
const searchIndex = computed(() =>
    props.options.map((option) => ({
        option,
        haystack: normalize(
            [option.label, option.sublabel, option.search]
                .filter(Boolean)
                .join(' '),
        ),
    })),
);

const matches = computed(() => {
    const term = normalize(query.value.trim());

    if (!term) {
        return props.options;
    }

    return searchIndex.value
        .filter((entry) => entry.haystack.includes(term))
        .map((entry) => entry.option);
});

const filtered = computed(() => matches.value.slice(0, MAX_VISIBLE));
const hiddenCount = computed(
    () => matches.value.length - filtered.value.length,
);

const inputValue = computed(() =>
    open.value || !selected.value ? query.value : selected.value.label,
);

function openSearch() {
    if (props.disabled) {
        return;
    }

    open.value = true;

    // Al volver a enfocar un campo ya elegido se limpia para poder buscar otro.
    if (selected.value) {
        emit('update:modelValue', null);
        query.value = '';
    }

    highlightIndex.value = 0;
}

function onInput(event: Event) {
    query.value = (event.target as HTMLInputElement).value;
    open.value = true;
    highlightIndex.value = 0;
}

function select(option: ComboOption) {
    emit('update:modelValue', option.id);
    emit('select', option);
    query.value = option.label;
    open.value = false;
}

function clear() {
    emit('update:modelValue', null);
    query.value = '';
    open.value = true;
    highlightIndex.value = 0;
    nextTick(() => inputRef.value?.focus());
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        open.value = true;

        if (!filtered.value.length) {
            return;
        }

        highlightIndex.value = Math.min(
            highlightIndex.value + 1,
            filtered.value.length - 1,
        );
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        highlightIndex.value = Math.max(highlightIndex.value - 1, 0);
    } else if (event.key === 'Enter') {
        event.preventDefault();
        const option = filtered.value[highlightIndex.value];

        if (option) {
            select(option);
        }
    } else if (event.key === 'Escape') {
        open.value = false;
    }
}

onClickOutside(containerRef, () => {
    open.value = false;
});
</script>

<template>
    <div ref="containerRef" class="relative" :class="props.class">
        <div class="relative">
            <Search
                class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <!-- input nativo a proposito: el componente Input de shadcn controla
                 su valor con un v-model interno, y pasarle :value dejaba dos
                 mecanismos sobre el mismo campo. -->
            <input
                ref="inputRef"
                type="text"
                :value="inputValue"
                :placeholder="placeholder"
                :title="selected?.label"
                :disabled="disabled"
                :aria-invalid="ariaInvalid ? true : undefined"
                :aria-expanded="open ? 'true' : 'false'"
                aria-autocomplete="list"
                autocomplete="off"
                class="h-9 w-full min-w-0 rounded-md border border-input bg-card px-3 py-1 pr-8 pl-8 text-base shadow-xs transition-[color,box-shadow] outline-none selection:bg-primary selection:text-primary-foreground placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 md:text-sm dark:bg-input/30 dark:aria-invalid:ring-destructive/40"
                @focus="openSearch"
                @input="onInput"
                @keydown="onKeydown"
            />
            <button
                v-if="selected"
                type="button"
                :disabled="disabled"
                class="absolute top-1/2 right-2 -translate-y-1/2 rounded-sm p-0.5 text-muted-foreground transition-colors hover:text-foreground disabled:pointer-events-none disabled:opacity-50"
                :aria-label="`Quitar ${selected.label}`"
                @click="clear"
            >
                <X class="size-4" />
            </button>
            <ChevronsUpDown
                v-else
                class="pointer-events-none absolute top-1/2 right-2.5 size-4 -translate-y-1/2 text-muted-foreground"
            />
        </div>

        <div
            v-if="open"
            class="absolute top-full right-0 left-0 z-50 mt-1 max-h-64 overflow-y-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md"
            role="listbox"
        >
            <p
                v-if="!filtered.length"
                class="px-2 py-6 text-center text-sm text-muted-foreground"
            >
                No se encontraron resultados con «{{ query }}».
            </p>
            <template v-else>
                <button
                    v-for="(option, i) in filtered"
                    :key="option.id"
                    type="button"
                    role="option"
                    class="relative flex w-full cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm outline-hidden select-none [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0"
                    :class="
                        cn(
                            i === highlightIndex
                                ? 'bg-accent text-accent-foreground'
                                : 'text-popover-foreground',
                            option.id === modelValue && 'pr-8',
                        )
                    "
                    :title="
                        option.sublabel
                            ? `${option.label} — ${option.sublabel}`
                            : option.label
                    "
                    @mouseenter="highlightIndex = i"
                    @click="select(option)"
                >
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-medium">
                            {{ option.label }}
                        </span>
                        <span
                            v-if="option.sublabel"
                            class="block truncate text-xs text-muted-foreground"
                        >
                            {{ option.sublabel }}
                        </span>
                    </span>
                    <Check
                        v-if="option.id === modelValue"
                        class="absolute right-2 text-primary"
                    />
                </button>
                <p
                    v-if="hiddenCount > 0"
                    class="px-2 py-1.5 text-center text-xs text-muted-foreground"
                >
                    y {{ hiddenCount }} más — afiná la búsqueda
                </p>
            </template>
        </div>
    </div>
</template>
