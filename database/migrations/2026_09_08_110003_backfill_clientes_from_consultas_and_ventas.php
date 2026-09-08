<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Convierte el historial que ya existe en una cartera de clientes: agrupa
     * consultas y ventas por teléfono normalizado, crea un cliente por cada uno
     * y deja ambas tablas apuntando al suyo.
     *
     * La normalización está duplicada acá a propósito, en vez de llamar a
     * Client::normalizePhone(): una migración tiene que seguir haciendo lo
     * mismo dentro de un año, aunque el modelo cambie de criterio.
     */
    public function up(): void
    {
        $normalize = function (?string $value): ?string {
            $digits = preg_replace('/\D/', '', (string) $value);

            if ($digits === '') {
                return null;
            }

            if (strlen($digits) > 8 && str_starts_with($digits, '591')) {
                $digits = substr($digits, 3);
            }

            return $digits;
        };

        // Se recorren las consultas primero porque traen más datos (email), y
        // las ventas después solo completan lo que falte.
        $fuentes = [
            ['tabla' => 'consultas', 'email' => true],
            ['tabla' => 'ventas', 'email' => false],
        ];

        /** @var array<string, int> mapa telefono normalizado => cliente_id */
        $clientes = [];

        foreach ($fuentes as $fuente) {
            $columnas = ['id', 'cliente_nombre', 'cliente_telefono'];

            if ($fuente['email']) {
                $columnas[] = 'cliente_email';
            }

            DB::table($fuente['tabla'])
                ->select($columnas)
                ->orderBy('id')
                ->chunk(200, function ($filas) use (&$clientes, $normalize, $fuente) {
                    foreach ($filas as $fila) {
                        $telefono = $normalize($fila->cliente_telefono ?? null);

                        // Sin teléfono no hay forma de identificar a nadie: se
                        // deja la fila sin cliente en vez de inventar uno.
                        if ($telefono === null) {
                            continue;
                        }

                        if (! isset($clientes[$telefono])) {
                            $existente = DB::table('clientes')
                                ->where('telefono', $telefono)
                                ->value('id');

                            $clientes[$telefono] = $existente ?? DB::table('clientes')->insertGetId([
                                'nombre' => $fila->cliente_nombre ?: 'Cliente '.$telefono,
                                'telefono' => $telefono,
                                'email' => $fuente['email'] ? ($fila->cliente_email ?: null) : null,
                                'activo' => true,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }

                        DB::table($fuente['tabla'])
                            ->where('id', $fila->id)
                            ->update(['cliente_id' => $clientes[$telefono]]);
                    }
                });
        }

        // Enlaza con la cuenta web al cliente cuyo email coincide con el de un
        // usuario: es el mismo humano comprando por los dos canales.
        DB::table('users')
            ->select('id', 'email')
            ->orderBy('id')
            ->chunk(200, function ($usuarios) {
                foreach ($usuarios as $usuario) {
                    if (! $usuario->email) {
                        continue;
                    }

                    DB::table('clientes')
                        ->where('email', $usuario->email)
                        ->whereNull('user_id')
                        ->limit(1)
                        ->update(['user_id' => $usuario->id]);
                }
            });
    }

    public function down(): void
    {
        DB::table('ventas')->update(['cliente_id' => null]);
        DB::table('consultas')->update(['cliente_id' => null]);
        DB::table('clientes')->delete();
    }
};
