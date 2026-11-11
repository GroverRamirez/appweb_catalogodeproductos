<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Filter, X } from 'lucide-vue-next';
import { reactive, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Category = {
    id: number;
    name: string;
    slug: string;
    parent_id: number | null;
};
type Brand = { id: number; name: string; slug: string };

const props = defineProps<{
    categories: Category[];
    brands: Brand[];
    filters: {
        q?: string;
        category?: string;
        brand?: string;
        min_price?: string;
        max_price?: string;
        in_stock?: string;
        sort?: string;
    };
}>();

// Sin valor por defecto los <select> quedan en blanco en vez de mostrar
// "Todas" / "Relevancia".
const local = reactive({
    category: '',
    brand: '',
    min_price: '',
    max_price: '',
    in_stock: '',
    sort: '',
    ...props.filters,
});

let timer: ReturnType<typeof setTimeout> | null = null;
const debouncedApply = () => {
    if (timer) {
        clearTimeout(timer);
    }

    timer = setTimeout(() => {
        // Solo los filtros con valor, para no ensuciar la URL con "brand=&…"
        const params = Object.fromEntries(
            Object.entries(local).filter(([, v]) => v !== '' && v != null),
        );
        router.get('/catalogo', params, { preserveState: true, replace: true });
    }, 400);
};

watch(local, debouncedApply, { deep: true });

const clear = () => {
    Object.keys(local).forEach((k) => ((local as any)[k] = ''));
    router.get('/catalogo', {}, { preserveState: false });
};

const rootCategories = props.categories.filter((c) => !c.parent_id);
</script>

<template>
    <aside
        class="space-y-5 rounded-2xl bg-card p-5 text-sm ring-1 elev-1 ring-border/70"
    >
        <div
            class="flex items-center justify-between border-b border-border/70 pb-3"
        >
            <h3
                class="inline-flex items-center gap-2 font-display font-semibold"
            >
                <Filter class="size-4 text-brand" />
                Filtros
            </h3>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                @click="clear"
                class="h-7 rounded-full px-2 text-xs text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
            >
                <X class="size-3" /> Limpiar
            </Button>
        </div>

        <div>
            <Label class="mb-2 block text-xs font-semibold text-foreground">
                Categoría
            </Label>
            <select
                v-model="local.category"
                class="h-10 w-full rounded-xl border border-input bg-card px-3 text-sm transition outline-none focus:border-brand focus:ring-3 focus:ring-brand/20"
            >
                <option value="">Todas</option>
                <option v-for="c in rootCategories" :key="c.id" :value="c.slug">
                    {{ c.name }}
                </option>
            </select>
        </div>

        <div>
            <Label class="mb-2 block text-xs font-semibold text-foreground">
                Marca
            </Label>
            <select
                v-model="local.brand"
                class="h-10 w-full rounded-xl border border-input bg-card px-3 text-sm transition outline-none focus:border-brand focus:ring-3 focus:ring-brand/20"
            >
                <option value="">Todas</option>
                <option v-for="b in brands" :key="b.id" :value="b.slug">
                    {{ b.name }}
                </option>
            </select>
        </div>

        <div>
            <Label class="mb-2 block text-xs font-semibold text-foreground">
                Precio
            </Label>
            <div class="flex gap-2">
                <Input
                    v-model="local.min_price"
                    type="number"
                    min="0"
                    placeholder="Min"
                    class="h-10 rounded-xl"
                />
                <Input
                    v-model="local.max_price"
                    type="number"
                    min="0"
                    placeholder="Max"
                    class="h-10 rounded-xl"
                />
            </div>
        </div>

        <label
            class="flex cursor-pointer items-center gap-3 rounded-xl border border-input px-3 py-2.5 transition hover:bg-accent"
        >
            <input
                id="in_stock"
                type="checkbox"
                v-model="local.in_stock"
                true-value="1"
                false-value=""
                class="accent-brand"
            />
            <span class="text-sm font-medium"> Solo en stock </span>
        </label>

        <div>
            <Label class="mb-2 block text-xs font-semibold text-foreground">
                Ordenar por
            </Label>
            <select
                v-model="local.sort"
                class="h-10 w-full rounded-xl border border-input bg-card px-3 text-sm transition outline-none focus:border-brand focus:ring-3 focus:ring-brand/20"
            >
                <option value="">Relevancia</option>
                <option value="newest">Más nuevos</option>
                <option value="price_asc">Precio: menor a mayor</option>
                <option value="price_desc">Precio: mayor a menor</option>
                <option value="most_viewed">Más visto</option>
                <option value="discount">Con descuento</option>
            </select>
        </div>
    </aside>
</template>
