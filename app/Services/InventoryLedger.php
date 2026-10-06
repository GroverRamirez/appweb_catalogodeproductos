<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

/**
 * Único lugar por donde se mueve el stock.
 *
 * El stock del producto y su línea de kardex se escriben en la misma llamada:
 * mientras nadie toque `productos.stock` por fuera de acá, la suma de los
 * movimientos de un producto es su stock, y esa igualdad es lo que hace
 * auditable al inventario.
 */
final class InventoryLedger
{
    /**
     * Aplica un movimiento y lo registra.
     *
     * `$quantity` va con signo: positivo entra, negativo sale. El stock no se
     * recorta en 0 —el negativo es la señal honesta de que el inventario
     * cargado no coincide con la realidad, mismo criterio que ya usaban la
     * venta y la anulación de compra—.
     *
     * El producto debe venir con su fila ya bloqueada por la transacción que
     * llama, que es como lo hacen las ventas y las compras.
     */
    public static function record(
        Product $product,
        int $quantity,
        string $type,
        ?Model $source = null,
        ?string $reason = null,
    ): InventoryMovement {
        $previous = (int) $product->stock;
        $resulting = $previous + $quantity;

        $product->update(['stock' => $resulting]);

        return InventoryMovement::create([
            'product_id' => $product->id,
            'product_name_snapshot' => $product->name,
            'product_code_snapshot' => $product->code,
            'type' => $type,
            'quantity' => $quantity,
            'previous_stock' => $previous,
            'resulting_stock' => $resulting,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'reason' => $reason,
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Lleva el stock de un producto a un valor concreto, registrando la
     * diferencia. Es lo que hace el ajuste manual del panel: el usuario piensa
     * en "quedan 12", no en "sumá 3".
     *
     * Devuelve null cuando no hay diferencia: un ajuste que no mueve nada no
     * merece una línea en el kardex.
     */
    public static function adjustTo(Product $product, int $newStock, string $reason): ?InventoryMovement
    {
        $difference = $newStock - (int) $product->stock;

        if ($difference === 0) {
            return null;
        }

        return self::record(
            $product,
            $difference,
            InventoryMovement::TYPE_ADJUSTMENT,
            null,
            $reason,
        );
    }
}
