<?php

use Illuminate\Database\Migrations\Migration;

// Catalog refactor: Teams removed. Kept as no-op so historical migration ordering is preserved.
return new class extends Migration
{
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};
