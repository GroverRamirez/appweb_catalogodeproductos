<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega las columnas de cupón/descuento a la tabla consultas.
 * Estas columnas fueron omitidas en la migración original y son necesarias
 * para que InquiryController::checkout() guarde correctamente los datos de cupón.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Para instalaciones existentes (tabla ya renombrada a 'consultas')
        if (Schema::hasTable('consultas') && ! Schema::hasColumn('consultas', 'cupon_codigo')) {
            Schema::table('consultas', function (Blueprint $table) {
                $table->string('cupon_codigo', 50)->nullable()->after('total_estimado');
                $table->decimal('descuento_monto', 12, 2)->nullable()->after('cupon_codigo');
            });
        }

        // Para instalaciones nuevas (tabla todavía en inglés, antes del rename migration)
        if (Schema::hasTable('inquiries') && ! Schema::hasColumn('inquiries', 'coupon_code')) {
            Schema::table('inquiries', function (Blueprint $table) {
                $table->string('coupon_code', 50)->nullable()->after('total_estimated');
                $table->decimal('discount_amount', 12, 2)->nullable()->after('coupon_code');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('consultas')) {
            Schema::table('consultas', function (Blueprint $table) {
                $table->dropColumn(['cupon_codigo', 'descuento_monto']);
            });
        }

        if (Schema::hasTable('inquiries')) {
            Schema::table('inquiries', function (Blueprint $table) {
                $table->dropColumn(['coupon_code', 'discount_amount']);
            });
        }
    }
};
