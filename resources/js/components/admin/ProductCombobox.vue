<script setup lang="ts">
import { computed } from 'vue';
import SearchCombobox from '@/components/admin/SearchCombobox.vue';
import type { ComboOption } from '@/components/admin/SearchCombobox.vue';

/**
 * Adaptador sobre SearchCombobox: traduce productos a opciones y devuelve el
 * producto completo al elegir (el formulario necesita su costo). La lógica del
 * buscador vive en SearchCombobox, para no mantenerla en dos lugares.
 */
export type ProductOption = {
    id: number;
    name: string;
    code: string;
    cost: string | null;
    stock: number;
};

const props = withDefaults(
    defineProps<{
        products: ProductOption[];
        modelValue: number | null;
        placeholder?: string;
        disabled?: boolean;
        ariaInvalid?: boolean;
        class?: string;
    }>(),
    {
        placeholder: 'Buscar producto por nombre o código…',
        disabled: false,
        ariaInvalid: false,
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: number | null): void;
    (e: 'select', product: ProductOption): void;
}>();

const options = computed<ComboOption[]>(() =>
    props.products.map((product) => ({
        id: product.id,
        label: product.name,
        sublabel: `${product.code} · stock: ${product.stock}`,
        search: product.code,
    })),
);

const onSelect = (option: ComboOption) => {
    const product = props.products.find((p) => p.id === option.id);

    if (product) {
        emit('select', product);
    }
};
</script>

<template>
    <SearchCombobox
        :options="options"
        :model-value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :aria-invalid="ariaInvalid"
        :class="props.class"
        @update:model-value="emit('update:modelValue', $event)"
        @select="onSelect"
    />
</template>
