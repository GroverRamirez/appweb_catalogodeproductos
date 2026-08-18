/**
 * Ícono representativo para una categoría cuando no tiene imagen propia
 * cargada. Se matchea por palabras clave contra el slug (ya sin acentos),
 * pensado para el rubro de esta tienda (PC, redes, seguridad, electrónica).
 * Si ninguna regla matchea, cae en un ícono genérico de caja.
 */
import {
    Battery,
    Camera,
    Cpu,
    Gamepad2,
    HardDrive,
    Headphones,
    Keyboard,
    Laptop,
    Mouse,
    Network,
    Package,
    Printer,
    Smartphone,
    Speaker,
    Usb,
    Video,
} from 'lucide-vue-next';
import type { LucideIcon } from 'lucide-vue-next';

const CATEGORY_ICON_RULES: [RegExp, LucideIcon][] = [
    [/camara|seguridad|vigilan|cctv/, Camera],
    [/grabador|dvr|nvr|video/, Video],
    [/almacen|disco|storage|ssd|hdd|nas/, HardDrive],
    [/red(es)?|network|router|switch|wifi/, Network],
    [/mouse|raton/, Mouse],
    [/teclado|keyboard/, Keyboard],
    [/accesorio|periferic|cable|adaptador|usb|hub/, Usb],
    [/electronic/, Cpu],
    [/celular|smartphone|movil/, Smartphone],
    [/laptop|notebook|computador|pc\b/, Laptop],
    [/parlante|bocina|sonido|speaker/, Speaker],
    [/audifono|headphone|auricular/, Headphones],
    [/impresor|printer/, Printer],
    [/bateria|power|energia|cargador/, Battery],
    [/gamer|gaming|videojuego/, Gamepad2],
];

export function categoryIcon(slug: string): LucideIcon {
    // Algunos slugs importados no pasaron por el slugger de Laravel (llegan con
    // mayúsculas o acentos), así que normalizamos antes de matchear.
    const normalized = slug
        .toLowerCase()
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '');
    const match = CATEGORY_ICON_RULES.find(([re]) => re.test(normalized));

    return match ? match[1] : Package;
}
