<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\ExchangeRate;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        // Los datos de contacto arrancan vacios a proposito: un numero o un
        // correo de ejemplo mandaria a los clientes y los avisos de consultas
        // a ninguna parte. Los carga `app:instalar` o el propietario en
        // Configuracion; mientras falten, el catalogo no muestra el boton.
        $defaults = [
            ['key' => 'store_name', 'value' => 'Mi Catálogo', 'type' => 'string', 'group' => 'general', 'label' => 'Nombre de la tienda'],
            ['key' => 'store_tagline', 'value' => 'Productos seleccionados para ti', 'type' => 'string', 'group' => 'general', 'label' => 'Frase corta'],
            ['key' => 'whatsapp_number', 'value' => '', 'type' => 'string', 'group' => 'contacto', 'label' => 'Número de WhatsApp (formato internacional)'],
            ['key' => 'whatsapp_message_template', 'value' => 'Hola, me interesa el producto: {producto} (código {codigo}).', 'type' => 'text', 'group' => 'contacto', 'label' => 'Mensaje base para WhatsApp'],
            ['key' => 'email_contact', 'value' => '', 'type' => 'string', 'group' => 'contacto', 'label' => 'Email de contacto'],
            ['key' => 'address', 'value' => '', 'type' => 'string', 'group' => 'contacto', 'label' => 'Dirección'],
            ['key' => 'business_hours', 'value' => 'Lun-Sáb 9:00-19:00', 'type' => 'string', 'group' => 'contacto', 'label' => 'Horario'],
            ['key' => 'currency', 'value' => 'BOB', 'type' => 'string', 'group' => 'general', 'label' => 'Moneda de cobro (ISO)'],
            ['key' => 'currency_symbol', 'value' => 'Bs', 'type' => 'string', 'group' => 'general', 'label' => 'Símbolo de la moneda de cobro'],
            // La lista de precios se carga en dólares y la tienda cobra en
            // bolivianos: el tipo de cambio es lo que une las dos cosas.
            ['key' => 'exchange_rate', 'value' => (string) ExchangeRate::FALLBACK, 'type' => 'number', 'group' => 'moneda', 'label' => 'Tipo de cambio: bolivianos por dólar (lo actualiza el BCB a diario)'],
            ['key' => 'exchange_rate_source', 'value' => 'manual', 'type' => 'string', 'group' => 'moneda', 'label' => 'Origen de la última cotización (bcb o manual) — lo escribe el sistema'],
            ['key' => 'exchange_rate_updated_at', 'value' => '', 'type' => 'string', 'group' => 'moneda', 'label' => 'Última actualización del tipo de cambio — lo escribe el sistema'],
            ['key' => 'price_list_currency', 'value' => 'USD', 'type' => 'string', 'group' => 'moneda', 'label' => 'Moneda de la lista de precios — la escribe el sistema'],
            ['key' => 'purchases_currency', 'value' => 'USD', 'type' => 'string', 'group' => 'moneda', 'label' => 'Moneda de las compras — la escribe el sistema'],
            ['key' => 'show_prices', 'value' => '1', 'type' => 'boolean', 'group' => 'catalogo', 'label' => 'Mostrar precios en el catálogo público'],
            ['key' => 'show_stock', 'value' => '1', 'type' => 'boolean', 'group' => 'catalogo', 'label' => 'Mostrar disponibilidad'],
            ['key' => 'products_per_page', 'value' => '15', 'type' => 'number', 'group' => 'catalogo', 'label' => 'Productos por página'],
            ['key' => 'logo_path', 'value' => '', 'type' => 'image', 'group' => 'general', 'label' => 'Logo de la tienda (PNG/SVG con fondo transparente)'],
            ['key' => 'favicon_path', 'value' => '', 'type' => 'image', 'group' => 'general', 'label' => 'Favicon (ico/png cuadrado)'],
        ];

        foreach ($defaults as $row) {
            Setting::set($row['key'], $row['value'], $row['type'], $row['group'], $row['label']);
        }
    }
}
