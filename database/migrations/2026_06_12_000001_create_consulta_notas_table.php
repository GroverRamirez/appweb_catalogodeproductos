<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historial de seguimiento de cada consulta: notas internas del staff y
     * registros automáticos (p. ej. cambios de estado). `user_id` es null
     * para notas de sistema o cuando el autor fue eliminado.
     */
    public function up(): void
    {
        Schema::create('consulta_notas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consulta_id')->constrained('consultas')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cuerpo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consulta_notas');
    }
};
