/** Helpers compartidos por las paginas del catalogo publico */

export type CatalogProduct = {
    id: number;
    slug: string;
    name: string;
    code: string;
    price: string;
    sale_price: string | null;
    stock: number;
    short_description: string | null;
    is_featured: boolean;
    created_at?: string;
    main_image?: { path: string; url?: string } | null;
    mainImage?: { path: string; url?: string } | null; // alias camelCase (menos común)
    images?: { path: string; is_main: boolean }[];
    category?: { id?: number; name: string; slug?: string } | null;
    brand?: { id?: number; name: string; slug?: string } | null;
};

export type StoreSettings = {
    name: string;
    tagline: string;
    whatsapp: string;
    whatsapp_template: string;
    email: string;
    address: string;
    hours: string;
    currency: string;
    currency_symbol: string;
    show_prices: boolean;
    show_stock: boolean;
    logo_url: string | null;
    favicon_url: string | null;
};

export const imageUrl = (path?: string | null): string | null => {
    if (!path) {
        return null;
    }

    return path.startsWith('http') ? path : `/storage/${path}`;
};

export const productMainImage = (product: CatalogProduct): string | null => {
    // Laravel serializa mainImage() como main_image (snake_case) en JSON — HasOne → objeto o null
    const fromMain = (product.main_image ?? product.mainImage)?.path;

    if (fromMain) {
        return imageUrl(fromMain);
    }

    // Fallback: buscar en la galería completa
    const fromList =
        product.images?.find((image) => image.is_main)?.path ??
        product.images?.[0]?.path;

    return imageUrl(fromList);
};

/**
 * Formatea un monto con separador de miles. Usa la convencion boliviana
 * (es-BO): 165.668,72 — punto para miles, coma para decimales. Es la misma
 * que ya usaba el comprobante de compra, asi que todo el sistema queda igual.
 */
export const formatPrice = (value: string | number, symbol = 'S/'): string => {
    const amount = typeof value === 'string' ? parseFloat(value) : value;
    const safe = Number.isNaN(amount) ? 0 : amount;

    return `${symbol} ${safe.toLocaleString('es-BO', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
};

export const buildWhatsAppLink = (
    phone: string,
    template: string,
    product?: { name: string; code: string },
): string => {
    const cleanPhone = phone.replace(/[^0-9]/g, '');
    let message = template;

    if (product) {
        message = message
            .replace(/\{producto\}/gi, product.name)
            .replace(/\{codigo\}/gi, product.code);
    }

    return `https://wa.me/${cleanPhone}?text=${encodeURIComponent(message)}`;
};
