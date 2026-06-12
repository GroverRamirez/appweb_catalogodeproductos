<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CheckCircle2,
    FileUp,
    Package,
    Pencil,
    Plus,
    Search,
    Star,
    Trash2,
    XCircle,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { xsrfToken } from '@/lib/xsrf';

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Productos', href: '/admin/products' },
        ],
    }),
});

type Product = {
    id: number;
    code: string;
    name: string;
    price: string;
    sale_price: string | null;
    stock: number;
    min_stock: number;
    is_active: boolean;
    is_featured: boolean;
    category?: { id: number; name: string } | null;
    brand?: { id: number; name: string } | null;
    main_image?: { id: number; path: string } | null;
};

const props = defineProps<{
    products: {
        data: Product[];
        links: { url: string | null; label: string; active: boolean }[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: {
        q?: string;
        category?: number | string;
        brand?: number | string;
        status?: string;
        low_stock?: boolean;
    };
    categories: { id: number; name: string }[];
    brands: { id: number; name: string }[];
}>();

const q = ref(props.filters.q ?? '');
const category = ref(props.filters.category ?? '');
const brand = ref(props.filters.brand ?? '');
const status = ref(props.filters.status ?? '');
const lowStock = ref(!!props.filters.low_stock);

let timer: ReturnType<typeof setTimeout> | null = null;
watch([q, category, brand, status, lowStock], () => {
    if (timer) {
        clearTimeout(timer);
    }

    timer = setTimeout(() => {
        router.get(
            '/admin/products',
            {
                q: q.value,
                category: category.value,
                brand: brand.value,
                status: status.value,
                low_stock: lowStock.value ? 1 : '',
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

const destroy = (p: Product) => {
    if (!confirm(`¿Eliminar "${p.name}"?`)) {
        return;
    }

    router.delete(`/admin/products/${p.id}`, { preserveScroll: true });
};

const imageUrl = (p: Product) => {
    const img = p.main_image?.path;

    if (!img) {
        return null;
    }

    return img.startsWith('http') ? img : `/storage/${img}`;
};

// ── CSV Import ────────────────────────────────────────────────────────────
const importOpen = ref(false);
const importFile = ref<File | null>(null);
const importing = ref(false);
const importResult = ref<{
    imported: number;
    updated: number;
    errors: { row: number; code: string; errors: string[] }[];
} | null>(null);

const CSV_TEMPLATE = [
    'codigo,nombre,precio,precio_oferta,stock,categoria,marca,activo',
    'PROD-001,Producto Ejemplo,99.90,79.90,50,Electrónica,Samsung,1',
].join('\n');

function downloadTemplate() {
    const blob = new Blob([CSV_TEMPLATE], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'plantilla_productos.csv';
    a.click();
    URL.revokeObjectURL(url);
}

function onFileChange(e: Event) {
    importFile.value = (e.target as HTMLInputElement).files?.[0] ?? null;
    importResult.value = null;
}

async function runImport() {
    if (!importFile.value) {
        return;
    }

    importing.value = true;
    importResult.value = null;

    const fd = new FormData();
    fd.append('file', importFile.value);

    try {
        const res = await fetch('/admin/products/import', {
            method: 'POST',
            headers: {
                'X-XSRF-TOKEN': xsrfToken(),
            },
            credentials: 'same-origin',
            body: fd,
        });
        const json = await res.json();

        if (!res.ok) {
            importResult.value = {
                imported: 0,
                updated: 0,
                errors: [
                    {
                        row: 0,
                        code: '',
                        errors: [json.message ?? 'Error desconocido'],
                    },
                ],
            };
        } else {
            importResult.value = json;

            // Refresh product table if something was imported/updated
            if (json.imported > 0 || json.updated > 0) {
                router.reload({ only: ['products'] });
            }
        }
    } catch {
        importResult.value = {
            imported: 0,
            updated: 0,
            errors: [
                {
                    row: 0,
                    code: '',
                    errors: ['Error de red al subir el archivo.'],
                },
            ],
        };
    } finally {
        importing.value = false;
    }
}
</script>

<template>
    <Head title="Productos" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            :icon="Package"
            eyebrow="Catálogo"
            title="Productos"
            :description="`${products.total} productos en total.`"
        >
            <template #actions>
                <Button
                    variant="outline"
                    class="rounded-full"
                    @click="importOpen = true"
                >
                    <FileUp class="size-4" /> Importar CSV
                </Button>
                <Button as-child class="rounded-md">
                    <Link href="/admin/products/create">
                        <Plus class="size-4" /> Nuevo producto
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-3 rounded-2xl border bg-card p-4 md:grid-cols-5">
            <div class="relative md:col-span-2">
                <Search
                    class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="q"
                    placeholder="Buscar por nombre o código..."
                    class="rounded-lg pl-9 focus-visible:border-brand focus-visible:ring-brand/20"
                />
            </div>
            <select
                v-model="category"
                class="h-10 rounded-lg border bg-background px-3 text-sm transition focus:border-brand focus:ring-2 focus:ring-brand/20"
            >
                <option value="">Todas las categorías</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">
                    {{ c.name }}
                </option>
            </select>
            <select
                v-model="brand"
                class="h-10 rounded-lg border bg-background px-3 text-sm transition focus:border-brand focus:ring-2 focus:ring-brand/20"
            >
                <option value="">Todas las marcas</option>
                <option v-for="b in brands" :key="b.id" :value="b.id">
                    {{ b.name }}
                </option>
            </select>
            <select
                v-model="status"
                class="h-10 rounded-lg border bg-background px-3 text-sm transition focus:border-brand focus:ring-2 focus:ring-brand/20"
            >
                <option value="">Estado</option>
                <option value="1">Activos</option>
                <option value="0">Inactivos</option>
            </select>

            <label class="col-span-full flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    v-model="lowStock"
                    class="accent-brand"
                />
                Solo stock bajo
            </label>
        </div>

        <div class="overflow-hidden rounded-2xl border bg-card shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead
                        class="bg-muted/40 text-left text-[11px] tracking-wider text-muted-foreground uppercase"
                    >
                        <tr>
                            <th class="w-12 px-3 py-2"></th>
                            <th class="px-3 py-2">Producto</th>
                            <th class="px-3 py-2">Categoría</th>
                            <th class="px-3 py-2">Marca</th>
                            <th class="px-3 py-2 text-right">Precio</th>
                            <th class="px-3 py-2 text-right">Stock</th>
                            <th class="px-3 py-2">Estado</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="p in products.data"
                            :key="p.id"
                            class="transition hover:bg-brand/5"
                        >
                            <td class="px-3 py-2">
                                <img
                                    v-if="imageUrl(p)"
                                    :src="imageUrl(p)!"
                                    :alt="p.name"
                                    class="size-11 rounded-lg object-cover shadow-sm ring-1 ring-border/60"
                                />
                                <div
                                    v-else
                                    class="size-11 rounded-lg bg-muted"
                                ></div>
                            </td>
                            <td class="px-3 py-2">
                                <div
                                    class="flex items-center gap-1 font-medium"
                                >
                                    {{ p.name }}
                                    <Star
                                        v-if="p.is_featured"
                                        class="size-3 text-amber-500"
                                    />
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ p.code }}
                                </div>
                            </td>
                            <td class="px-3 py-2 text-muted-foreground">
                                {{ p.category?.name ?? '—' }}
                            </td>
                            <td class="px-3 py-2 text-muted-foreground">
                                {{ p.brand?.name ?? '—' }}
                            </td>
                            <td class="px-3 py-2 text-right">
                                <span v-if="p.sale_price" class="font-semibold">
                                    {{ p.sale_price }}
                                </span>
                                <span v-else class="font-semibold">{{
                                    p.price
                                }}</span>
                                <div
                                    v-if="p.sale_price"
                                    class="text-xs text-muted-foreground line-through"
                                >
                                    {{ p.price }}
                                </div>
                            </td>
                            <td class="px-3 py-2 text-right">
                                <span
                                    :class="
                                        p.stock <= 0
                                            ? 'font-semibold text-destructive'
                                            : p.stock <= p.min_stock
                                              ? 'text-amber-600'
                                              : ''
                                    "
                                    >{{ p.stock }}</span
                                >
                                <AlertTriangle
                                    v-if="p.stock > 0 && p.stock <= p.min_stock"
                                    class="ml-1 inline size-3 text-amber-600"
                                />
                            </td>
                            <td class="px-3 py-2">
                                <Badge
                                    :variant="
                                        p.is_active ? 'default' : 'secondary'
                                    "
                                >
                                    {{ p.is_active ? 'Activo' : 'Inactivo' }}
                                </Badge>
                            </td>
                            <td class="px-3 py-2 text-right">
                                <Button variant="ghost" size="icon-sm" as-child>
                                    <Link
                                        :href="`/admin/products/${p.id}/edit`"
                                    >
                                        <Pencil class="size-4" />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    @click="destroy(p)"
                                >
                                    <Trash2 class="size-4 text-destructive" />
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="!products.data.length">
                            <td
                                colspan="8"
                                class="px-3 py-10 text-center text-muted-foreground"
                            >
                                Sin resultados.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Pagination
            :links="products.links"
            :from="products.from"
            :to="products.to"
            :total="products.total"
        />

        <!-- ── Import CSV Dialog ────────────────────────────────────────── -->
        <Dialog :open="importOpen" @update:open="importOpen = $event">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <FileUp class="size-5 text-brand" /> Importar productos
                        desde CSV
                    </DialogTitle>
                    <DialogDescription>
                        Sube un archivo CSV con los productos. Filas con código
                        existente se actualizarán.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <!-- Template download -->
                    <button
                        type="button"
                        class="flex w-full items-center justify-between rounded-xl border border-dashed border-brand/40 bg-brand/5 px-4 py-3 text-sm text-brand transition hover:bg-brand/10"
                        @click="downloadTemplate"
                    >
                        <span class="font-medium"
                            >📄 Descargar plantilla CSV</span
                        >
                        <span class="text-xs text-muted-foreground"
                            >codigo, nombre, precio…</span
                        >
                    </button>

                    <!-- File input -->
                    <div class="space-y-1">
                        <label class="text-sm font-medium">Archivo CSV</label>
                        <input
                            type="file"
                            accept=".csv,text/csv"
                            class="block w-full cursor-pointer rounded-lg border bg-background px-3 py-2 text-sm file:mr-3 file:cursor-pointer file:rounded-md file:border-0 file:bg-brand/10 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-brand"
                            @change="onFileChange"
                        />
                        <p class="text-xs text-muted-foreground">
                            Máximo 5 MB. Columnas: codigo, nombre, precio,
                            precio_oferta, stock, categoria, marca, activo
                        </p>
                    </div>

                    <!-- Result -->
                    <div
                        v-if="importResult"
                        class="space-y-2 rounded-xl border p-4 text-sm"
                    >
                        <div class="flex gap-4 font-semibold">
                            <span class="flex items-center gap-1 text-brand">
                                <CheckCircle2 class="size-4" />
                                {{ importResult.imported }} nuevos
                            </span>
                            <span
                                class="flex items-center gap-1 text-amber-600"
                            >
                                <CheckCircle2 class="size-4" />
                                {{ importResult.updated }} actualizados
                            </span>
                            <span
                                v-if="importResult.errors.length"
                                class="flex items-center gap-1 text-destructive"
                            >
                                <XCircle class="size-4" />
                                {{ importResult.errors.length }} errores
                            </span>
                        </div>
                        <ul
                            v-if="importResult.errors.length"
                            class="max-h-40 space-y-1 overflow-y-auto text-xs text-destructive"
                        >
                            <li
                                v-for="e in importResult.errors"
                                :key="e.row"
                                class="rounded bg-destructive/5 px-2 py-1"
                            >
                                <span class="font-semibold"
                                    >Fila {{ e.row
                                    }}{{ e.code ? ` (${e.code})` : '' }}:</span
                                >
                                {{ e.errors.join(' · ') }}
                            </li>
                        </ul>
                    </div>

                    <!-- Actions -->
                    <div class="flex justify-end gap-2">
                        <Button variant="ghost" @click="importOpen = false"
                            >Cancelar</Button
                        >
                        <Button
                            :disabled="!importFile || importing"
                            class="rounded-md"
                            @click="runImport"
                        >
                            <span v-if="importing">Importando…</span>
                            <span v-else>Importar</span>
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
