<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Consultas/pedidos que llegan del catálogo público. No hay pagos online:
     * el cliente arma el pedido y el staff lo atiende por WhatsApp o email.
     *
     * `token_publico` es el identificador de la URL firmada de "gracias por tu
     * pedido", para que el cliente vea su consulta sin necesitar cuenta.
     */
    public function up(): void
    {
        Schema::create('consultas', function (Blueprint $table) {
            $table->id();
            $table->string('token_publico', 64)->nullable();
            $table->string('cliente_nombre');
            $table->string('cliente_telefono', 30);
            $table->string('cliente_email')->nullable();
            $table->text('mensaje')->nullable();
            $table->string('origen', 30)->default('whatsapp')->comment('whatsapp, web, telefono, otro');
            $table->string('estado', 30)->default('pendiente')->comment('pendiente, contactado, vendido, cerrado');
            $table->decimal('total_estimado', 12, 2)->nullable();
            $table->string('cupon_codigo', 40)->nullable();
            $table->decimal('descuento_monto', 12, 2)->nullable();
            $table->text('notas_admin')->nullable();
            $table->foreignId('atendido_por')->nullable();
            $table->timestamp('contactado_en')->nullable();
            $table->timestamps();

            $table->unique('token_publico', 'inquiries_public_token_unique');
            $table->index('estado', 'inquiries_status_index');
            $table->index('created_at', 'inquiries_created_at_index');
            $table->foreign('atendido_por', 'inquiries_handled_by_foreign')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultas');
    }
};
