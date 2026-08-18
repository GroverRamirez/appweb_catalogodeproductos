<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ingresos de mercadería registrados desde el panel admin. Al confirmarse
     * (estado por defecto) suman stock a cada producto de sus líneas; al
     * anularse lo revierten. `costo_total` siempre se calcula en servidor
     * sumando las líneas, nunca se acepta del cliente.
     */
    public function up(): void
    {
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $table->string('numero_referencia')->nullable()->comment('Remito o factura del proveedor');
            $table->string('estado')->default('confirmada');
            $table->decimal('costo_total', 12, 2)->default(0);
            $table->text('notas')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('anulado_en')->nullable();
            $table->timestamps();

            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras');
    }
};
