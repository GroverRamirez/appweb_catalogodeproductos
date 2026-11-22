<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Limpia los alt de imágenes que quedaron con un nombre de producto viejo.
     *
     * El alt se guarda como copia del nombre del producto en el momento de
     * subir la imagen, así que renombrar el producto dejaba el texto anterior
     * servido al público —a lectores de pantalla y a Google— para siempre. De
     * acá en adelante el modelo Product lo mantiene al día; esta migración
     * arregla lo que ya está guardado.
     *
     * Solo se tocan los alt que terminan en el nombre actual del producto y
     * traen texto de más adelante (el caso real encontrado: "preview preview
     * NOMBRE"). Un alt escrito a mano describe la imagen en vez de repetir el
     * nombre completo, no cumple esa forma, y queda intacto.
     */
    public function up(): void
    {
        DB::table('producto_imagenes as i')
            ->join('productos as p', 'p.id', '=', 'i.producto_id')
            ->select('i.id as imagen_id', 'i.texto_alternativo as alt', 'p.nombre as nombre')
            ->orderBy('i.id')
            ->chunk(200, function ($filas) {
                foreach ($filas as $fila) {
                    $nombre = trim((string) $fila->nombre);
                    $alt = trim((string) $fila->alt);

                    if ($nombre === '' || $alt === '' || $alt === $nombre) {
                        continue;
                    }

                    if (! str_ends_with($alt, $nombre)) {
                        continue;
                    }

                    DB::table('producto_imagenes')
                        ->where('id', $fila->imagen_id)
                        ->update([
                            'texto_alternativo' => $nombre,
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    /**
     * Sin vuelta atrás a propósito: lo que se reemplazó era el dato corrupto, y
     * conservarlo para poder restaurarlo no tendría ningún uso.
     */
    public function down(): void
    {
        //
    }
};
