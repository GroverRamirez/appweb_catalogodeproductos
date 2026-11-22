<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Asiento de apertura del kardex: una fila por producto con el stock que
     * tiene hoy.
     *
     * No se reconstruye la historia de compras y ventas anteriores a propósito.
     * El saldo corrido de cada movimiento viejo no quedó registrado en su
     * momento y calcularlo hacia atrás daría números inventados en la única
     * tabla del sistema que existe para ser auditada; esas operaciones siguen
     * consultables en `compras` y `ventas`.
     *
     * Con esto vale la invariante desde el día uno: la suma de
     * `movimientos_inventario.cantidad` de un producto es su `productos.stock`.
     */
    public function up(): void
    {
        DB::table('productos')
            ->select('id', 'nombre', 'codigo', 'stock')
            ->orderBy('id')
            ->chunk(200, function ($productos) {
                $ahora = now();
                $filas = [];

                foreach ($productos as $producto) {
                    $stock = (int) $producto->stock;

                    $filas[] = [
                        'producto_id' => $producto->id,
                        'producto_nombre_copia' => $producto->nombre,
                        'producto_codigo_copia' => $producto->codigo,
                        'tipo' => 'ajuste',
                        'cantidad' => $stock,
                        'stock_anterior' => 0,
                        'stock_resultante' => $stock,
                        'origen_type' => null,
                        'origen_id' => null,
                        'motivo' => 'Saldo inicial al implantar el kardex',
                        'user_id' => null,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ];
                }

                if ($filas !== []) {
                    DB::table('movimientos_inventario')->insert($filas);
                }
            });
    }

    public function down(): void
    {
        DB::table('movimientos_inventario')
            ->where('motivo', 'Saldo inicial al implantar el kardex')
            ->delete();
    }
};
