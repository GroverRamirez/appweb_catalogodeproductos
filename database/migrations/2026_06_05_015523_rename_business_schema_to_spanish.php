<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private array $tables = [
        'categories' => 'categorias',
        'brands' => 'marcas',
        'products' => 'productos',
        'product_images' => 'producto_imagenes',
        'product_attributes' => 'producto_atributos',
        'product_views' => 'producto_visitas',
        'inquiries' => 'consultas',
        'inquiry_items' => 'consulta_items',
        'settings' => 'configuraciones',
        'coupons' => 'cupones',
        'banners' => 'banners_publicitarios',
    ];

    /**
     * @var array<string, array<string, string>>
     */
    private array $columns = [
        'categorias' => [
            'name' => 'nombre',
            'description' => 'descripcion',
            'parent_id' => 'categoria_padre_id',
            'image' => 'imagen',
            'sort_order' => 'orden',
            'is_active' => 'activo',
        ],
        'marcas' => [
            'name' => 'nombre',
            'description' => 'descripcion',
            'website' => 'sitio_web',
            'is_active' => 'activo',
        ],
        'productos' => [
            'code' => 'codigo',
            'name' => 'nombre',
            'short_description' => 'descripcion_corta',
            'description' => 'descripcion',
            'price' => 'precio',
            'sale_price' => 'precio_oferta',
            'cost' => 'costo',
            'min_stock' => 'stock_minimo',
            'unit' => 'unidad',
            'category_id' => 'categoria_id',
            'brand_id' => 'marca_id',
            'is_featured' => 'destacado',
            'is_active' => 'activo',
            'views_count' => 'visitas',
        ],
        'producto_imagenes' => [
            'product_id' => 'producto_id',
            'path' => 'ruta',
            'alt' => 'texto_alternativo',
            'sort_order' => 'orden',
            'is_main' => 'principal',
        ],
        'producto_atributos' => [
            'product_id' => 'producto_id',
            'key' => 'clave',
            'value' => 'valor',
            'sort_order' => 'orden',
        ],
        'producto_visitas' => [
            'product_id' => 'producto_id',
            'ip_address' => 'direccion_ip',
            'session_id' => 'sesion_id',
            'user_agent' => 'agente_usuario',
            'referrer' => 'referente',
            'viewed_at' => 'visto_en',
        ],
        'consultas' => [
            'customer_name' => 'cliente_nombre',
            'customer_phone' => 'cliente_telefono',
            'customer_email' => 'cliente_email',
            'message' => 'mensaje',
            'source' => 'origen',
            'status' => 'estado',
            'total_estimated' => 'total_estimado',
            'coupon_code' => 'cupon_codigo',
            'discount_amount' => 'descuento_monto',
            'admin_notes' => 'notas_admin',
            'handled_by' => 'atendido_por',
            'contacted_at' => 'contactado_en',
            'public_token' => 'token_publico',
        ],
        'consulta_items' => [
            'inquiry_id' => 'consulta_id',
            'product_id' => 'producto_id',
            'product_name_snapshot' => 'producto_nombre_copia',
            'product_code_snapshot' => 'producto_codigo_copia',
            'quantity' => 'cantidad',
            'unit_price' => 'precio_unitario',
        ],
        'configuraciones' => [
            'key' => 'clave',
            'value' => 'valor',
            'group' => 'grupo',
            'label' => 'etiqueta',
            'type' => 'tipo',
        ],
        'cupones' => [
            'code' => 'codigo',
            'description' => 'descripcion',
            'type' => 'tipo',
            'value' => 'valor',
            'min_subtotal' => 'subtotal_minimo',
            'max_uses' => 'usos_maximos',
            'used_count' => 'usos_realizados',
            'starts_at' => 'inicia_en',
            'ends_at' => 'termina_en',
            'is_active' => 'activo',
        ],
        'banners_publicitarios' => [
            'title' => 'titulo',
            'subtitle' => 'subtitulo',
            'image' => 'imagen',
            'link' => 'enlace',
            'cta_text' => 'texto_cta',
            'sort_order' => 'orden',
            'is_active' => 'activo',
            'starts_at' => 'inicia_en',
            'ends_at' => 'termina_en',
        ],
    ];

    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ($this->tables as $from => $to) {
            $this->renameTable($from, $to);
        }

        foreach ($this->columns as $table => $columns) {
            foreach ($columns as $from => $to) {
                $this->renameColumn($table, $from, $to);
            }
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach (array_reverse($this->columns) as $table => $columns) {
            foreach (array_reverse($columns) as $from => $to) {
                $this->renameColumn($table, $to, $from);
            }
        }

        foreach (array_reverse($this->tables) as $from => $to) {
            $this->renameTable($to, $from);
        }

        Schema::enableForeignKeyConstraints();
    }

    private function renameTable(string $from, string $to): void
    {
        if (Schema::hasTable($from) && ! Schema::hasTable($to)) {
            Schema::rename($from, $to);
        }
    }

    private function renameColumn(string $table, string $from, string $to): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        if (Schema::hasColumn($table, $from) && ! Schema::hasColumn($table, $to)) {
            Schema::table($table, function (Blueprint $table) use ($from, $to): void {
                $table->renameColumn($from, $to);
            });
        }
    }
};
