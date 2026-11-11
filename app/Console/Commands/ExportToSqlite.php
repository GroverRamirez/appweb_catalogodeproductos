<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Copia la base actual (MySQL) a un archivo SQLite.
 *
 * Es el primer paso para armar la version portable: la PC de prueba se lleva
 * un solo archivo con el catalogo real —productos, categorias, precios,
 * usuarios y configuracion— en vez de una base vacia con datos de ejemplo.
 *
 * El esquema no se copia: se levanta corriendo las mismas migraciones sobre el
 * archivo nuevo. Asi el portable arranca con la estructura que el proyecto
 * declara hoy, no con la que tenga la base de desarrollo.
 */
class ExportToSqlite extends Command
{
    protected $signature = 'demo:exportar-sqlite
                            {--file=database/demo.sqlite : Archivo destino, relativo a la raiz del proyecto}
                            {--force : Sobrescribe el archivo si ya existe}';

    protected $description = 'Copia la base actual a un archivo SQLite para la version portable';

    /**
     * Tablas que no viajan: son estado de esta instalacion (colas, cache,
     * sesiones abiertas), no datos del negocio. `migrations` la llena el
     * propio migrate.
     *
     * @var list<string>
     */
    private const SKIP = [
        'migrations',
        'jobs',
        'job_batches',
        'failed_jobs',
        'cache',
        'cache_locks',
        'sessions',
        'password_reset_tokens',
    ];

    public function handle(): int
    {
        $path = base_path($this->option('file'));

        if (File::exists($path) && ! $this->option('force')) {
            if (! $this->confirm("Ya existe {$path}. Lo reemplazo?")) {
                return self::FAILURE;
            }
        }

        File::ensureDirectoryExists(dirname($path));
        File::delete($path);
        File::put($path, '');

        // Conexion temporal al archivo destino. Sin claves foraneas: las filas
        // se copian tabla por tabla y el orden no tiene por que respetarlas.
        config(['database.connections.demo' => [
            'driver' => 'sqlite',
            'database' => $path,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);

        $this->info('Creando el esquema en el archivo SQLite...');
        Artisan::call('migrate', ['--database' => 'demo', '--force' => true], $this->getOutput());

        $source = DB::connection();
        $target = DB::connection('demo');

        $tables = collect($source->getSchemaBuilder()->getTableListing())
            // Segun el driver, el listado puede venir calificado (base.tabla).
            ->map(fn (string $table) => str_contains($table, '.') ? last(explode('.', $table)) : $table)
            ->reject(fn (string $table) => in_array($table, self::SKIP, true))
            ->values();

        $copied = 0;

        foreach ($tables as $table) {
            if (! $target->getSchemaBuilder()->hasTable($table)) {
                $this->warn("  {$table}: no existe en el esquema nuevo, se omite.");

                continue;
            }

            $rows = $source->table($table)->get();

            if ($rows->isEmpty()) {
                continue;
            }

            $target->table($table)->delete();

            // De a 200 filas: SQLite tiene un tope de parametros por sentencia
            // y una insercion unica con miles de filas lo cruza.
            foreach ($rows->chunk(200) as $chunk) {
                $target->table($table)->insert(
                    $chunk->map(fn ($row) => (array) $row)->all()
                );
            }

            $this->line("  {$table}: {$rows->count()} filas");
            $copied += $rows->count();
        }

        $this->newLine();
        $this->info("Listo: {$copied} filas copiadas a {$path}");
        $this->comment('Las imagenes no viajan en la base: el script de armado copia storage/app/public aparte.');

        return self::SUCCESS;
    }
}
