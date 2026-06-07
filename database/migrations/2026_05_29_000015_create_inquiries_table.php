<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Consultas/pedidos recibidos (vía WhatsApp, formulario, etc.)
        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('customer_name');
            $table->string('customer_phone', 30);
            $table->string('customer_email')->nullable();
            $table->text('message')->nullable();
            $table->string('source', 30)->default('whatsapp')->comment('whatsapp, web, telefono, otro');
            $table->string('status', 30)->default('pendiente')->comment('pendiente, contactado, vendido, cerrado');
            $table->decimal('total_estimated', 12, 2)->nullable();
            $table->string('coupon_code', 50)->nullable();
            $table->decimal('discount_amount', 12, 2)->nullable();
            $table->text('admin_notes')->nullable();
            $table->foreignId('handled_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('contacted_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiries');
    }
};
