<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ventas registradas desde el panel admin. Al confirmarse (estado por
     * defecto) descuentan stock de cada producto de sus líneas; al anularse lo
     * devuelven. `subtotal` y `total` SIEMPRE se calculan en servidor desde la
     * base, nunca se aceptan del cliente.
     *
     * Una venta puede nacer en el mostrador (`origen = mostrador`) o de una
     * consulta del catálogo marcada como vendida (`origen = consulta`), y en
     * ese caso queda enlazada por `consulta_id`. Es la única fuente de verdad
     * de salidas de stock e ingresos: los reportes leen de acá.
     */
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            // Correlativo del recibo. El índice único es la garantía dura: si
            // el bloqueo que reserva el número fallara, el insert revienta en
            // vez de duplicar en silencio.
            $table->unsignedInteger('numero_recibo')->unique();
            $table->foreignId('consulta_id')->nullable()->constrained('consultas')->nullOnDelete();
            $table->string('origen', 20)->default('mostrador')->comment('mostrador, consulta');
            $table->string('cliente_nombre')->nullable();
            $table->string('cliente_telefono', 30)->nullable();
            $table->string('metodo_pago', 20)->default('efectivo')->comment('efectivo, qr, transferencia, tarjeta');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('descuento_monto', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('estado', 20)->default('confirmada')->comment('confirmada, anulada');
            $table->text('notas')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('anulado_en')->nullable();
            $table->timestamps();

            $table->index('estado');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
