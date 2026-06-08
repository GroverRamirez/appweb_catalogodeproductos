<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['key' => 'store_name', 'value' => 'Mi Catálogo', 'type' => 'string', 'group' => 'general', 'label' => 'Nombre de la tienda'],
            ['key' => 'store_tagline', 'value' => 'Productos seleccionados para ti', 'type' => 'string', 'group' => 'general', 'label' => 'Frase corta'],
            ['key' => 'whatsapp_number', 'value' => '+51999999999', 'type' => 'string', 'group' => 'contacto', 'label' => 'Número de WhatsApp (formato internacional)'],
            ['key' => 'whatsapp_message_template', 'value' => 'Hola, me interesa el producto: {producto} (código {codigo}).', 'type' => 'text', 'group' => 'contacto', 'label' => 'Mensaje base para WhatsApp'],
            ['key' => 'email_contact', 'value' => 'contacto@catalogo.test', 'type' => 'string', 'group' => 'contacto', 'label' => 'Email de contacto'],
            ['key' => 'address', 'value' => 'Av. Demo 123, Lima', 'type' => 'string', 'group' => 'contacto', 'label' => 'Dirección'],
            ['key' => 'business_hours', 'value' => 'Lun-Sáb 9:00-19:00', 'type' => 'string', 'group' => 'contacto', 'label' => 'Horario'],
            ['key' => 'currency', 'value' => 'PEN', 'type' => 'string', 'group' => 'general', 'label' => 'Moneda (ISO)'],
            ['key' => 'currency_symbol', 'value' => 'S/', 'type' => 'string', 'group' => 'general', 'label' => 'Símbolo de moneda'],
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
