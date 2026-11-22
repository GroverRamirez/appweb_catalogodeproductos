<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('consultas', function (Blueprint $table) {
            $table->string('comprobante_pago')->nullable()->after('descuento_monto');
            $table->string('estado_pago', 30)->default('sin_pago')->after('comprobante_pago')->comment('sin_pago, comprobante_subido, verificado, rechazado');
            $table->timestamp('pago_verificado_en')->nullable()->after('estado_pago');
            $table->foreignId('pago_verificado_por')->nullable()->after('pago_verificado_en')
                ->constrained('users')->nullOnDelete();
            $table->text('notas_pago')->nullable()->after('pago_verificado_por');

            $table->index('estado_pago', 'inquiries_estado_pago_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consultas', function (Blueprint $table) {
            $table->dropForeign(['pago_verificado_por']);
            $table->dropIndex('inquiries_estado_pago_index');
            $table->dropColumn([
                'comprobante_pago',
                'estado_pago',
                'pago_verificado_en',
                'pago_verificado_por',
                'notas_pago',
            ]);
        });
    }
};
