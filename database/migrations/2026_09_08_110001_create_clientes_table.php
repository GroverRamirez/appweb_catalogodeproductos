<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Clientes del negocio: a quién le vendo. Es distinto de `users`, que
     * responde quién puede entrar al sistema — la mayoría de los clientes de
     * mostrador nunca inician sesión, y `users` exige email único y contraseña.
     *
     * `user_id` enlaza al cliente con su cuenta web cuando la tiene (login con
     * Google); queda null para el cliente que solo compra en la tienda.
     *
     * `telefono` es la clave de identidad: en la tienda es el dato que todos
     * dan y por donde se los contacta. Se guarda normalizado (solo dígitos, sin
     * el 591) desde el modelo, porque si no "70000000" y "+591 70000000" serían
     * dos clientes distintos para el índice único.
     */
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('nombre');
            // Nullable para permitir la venta rápida sin datos: MySQL admite
            // varios NULL en una columna única, así que no chocan entre sí.
            $table->string('telefono', 30)->nullable()->unique();
            $table->string('carnet_identidad', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('direccion')->nullable();
            $table->text('notas')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('nombre');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
