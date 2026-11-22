<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

/**
 * Copias de seguridad de la base de datos.
 *
 * Con MySQL/MariaDB genera `db_<base>_<AAAAMMDD_HHMMSS>.sql.gz` con mysqldump,
 * el mismo formato que el backup diario de Docker (docker/backup.sh), asi que
 * ambas copias se restauran igual (ver DEPLOY.md §6). Con SQLite (la version
 * instalable para Windows) genera `db_<base>_<AAAAMMDD_HHMMSS>.sqlite.gz`: el
 * archivo de la base entero, que se restaura descomprimiendolo en su lugar.
 *
 * La restauracion NO se hace desde la web a proposito: es una operacion
 * destructiva que conviene hacer a mano y con la app detenida.
 */
class DatabaseBackup
{
    /** Solo se aceptan nombres con este formato: evita salir de la carpeta. */
    public const FILENAME_PATTERN = '/^db_[A-Za-z0-9_\-]+_\d{8}_\d{6}\.(sql|sqlite)\.gz$/';

    /** Carpeta donde viven las copias (privada, fuera de public). */
    public function directory(): string
    {
        return rtrim(config('backup.path'), '/\\');
    }

    /**
     * Copias existentes, la mas reciente primero.
     *
     * @return Collection<int, array{name: string, size: int, created_at: string}>
     */
    public function list(): Collection
    {
        if (! File::isDirectory($this->directory())) {
            return collect();
        }

        return collect(File::files($this->directory()))
            ->filter(fn ($file) => $this->isValidName($file->getFilename()))
            ->map(fn ($file) => [
                'name' => $file->getFilename(),
                'size' => $file->getSize(),
                'created_at' => Carbon::createFromTimestamp($file->getMTime())->toIso8601String(),
            ])
            ->sortByDesc('name')
            ->values();
    }

    /** Ruta absoluta de una copia, o null si el nombre no es valido o no existe. */
    public function path(string $name): ?string
    {
        if (! $this->isValidName($name)) {
            return null;
        }

        $path = $this->directory().DIRECTORY_SEPARATOR.$name;

        return File::isFile($path) ? $path : null;
    }

    public function delete(string $name): bool
    {
        $path = $this->path($name);

        return $path !== null && File::delete($path);
    }

    /**
     * Vuelca la base y devuelve el nombre del archivo generado.
     *
     * @throws RuntimeException si la conexion no es MySQL, MariaDB o SQLite, o el volcado falla
     */
    public function create(): string
    {
        $connection = config('backup.connection');
        $db = config("database.connections.{$connection}");
        $driver = is_array($db) ? ($db['driver'] ?? null) : null;

        if ($driver === 'sqlite') {
            return $this->createSqlite($connection, (string) $db['database']);
        }

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Las copias desde el panel solo funcionan con MySQL, MariaDB o SQLite.');
        }

        File::ensureDirectoryExists($this->directory());

        $database = (string) $db['database'];
        $name = $this->newName($database, 'sql');
        $target = $this->directory().DIRECTORY_SEPARATOR.$name;
        $sql = $target.'.tmp';

        // Las credenciales van en un archivo temporal y no en la linea de
        // comandos, donde cualquiera que liste procesos podria verlas.
        $credentials = $this->directory().DIRECTORY_SEPARATOR.'.my-'.bin2hex(random_bytes(6)).'.cnf';
        File::put($credentials, implode("\n", [
            '[client]',
            'user="'.addcslashes((string) $db['username'], '"\\').'"',
            'password="'.addcslashes((string) ($db['password'] ?? ''), '"\\').'"',
            '',
        ]));

        try {
            $command = [
                $this->binary(),
                '--defaults-extra-file='.$credentials,
                '--host='.($db['host'] ?? '127.0.0.1'),
                '--port='.($db['port'] ?? 3306),
                '--single-transaction',
                '--quick',
                '--no-tablespaces',
                '--routines',
                '--triggers',
                '--default-character-set=utf8mb4',
                '--result-file='.$sql,
                $database,
            ];

            $result = Process::timeout((int) config('backup.timeout', 300))->run($command);

            if ($result->failed() || ! File::exists($sql) || File::size($sql) === 0) {
                throw new RuntimeException('mysqldump fallo: '.trim($result->errorOutput() ?: $result->output() ?: 'no genero el volcado'));
            }

            $this->gzip($sql, $target);
        } catch (Throwable $e) {
            File::delete($target);

            throw $e;
        } finally {
            File::delete([$credentials, $sql]);
        }

        return $name;
    }

    /**
     * Copia la base SQLite con VACUUM INTO: sale una foto consistente aunque
     * la tienda este escribiendo, cosa que copiar el archivo no garantiza.
     */
    private function createSqlite(string $connection, string $database): string
    {
        if ($database === '' || $database === ':memory:') {
            throw new RuntimeException('La base SQLite en memoria no se puede copiar.');
        }

        File::ensureDirectoryExists($this->directory());

        $name = $this->newName(pathinfo($database, PATHINFO_FILENAME), 'sqlite');
        $target = $this->directory().DIRECTORY_SEPARATOR.$name;
        $copy = $target.'.tmp';

        try {
            DB::connection($connection)->statement('VACUUM INTO ?', [$copy]);
            $this->gzip($copy, $target);
        } catch (Throwable $e) {
            File::delete($target);

            throw new RuntimeException('No se pudo copiar la base SQLite: '.$e->getMessage(), previous: $e);
        } finally {
            File::delete($copy);
        }

        return $name;
    }

    private function newName(string $database, string $extension): string
    {
        return 'db_'.preg_replace('/[^A-Za-z0-9_\-]/', '_', $database).'_'.now()->format('Ymd_His').".{$extension}.gz";
    }

    public function isValidName(string $name): bool
    {
        return preg_match(self::FILENAME_PATTERN, $name) === 1;
    }

    /** Comprime por bloques para no cargar el volcado entero en memoria. */
    private function gzip(string $source, string $target): void
    {
        $in = fopen($source, 'rb');
        $out = gzopen($target, 'wb6');

        if ($in === false || $out === false) {
            throw new RuntimeException('No se pudo escribir la copia comprimida.');
        }

        while (! feof($in)) {
            gzwrite($out, (string) fread($in, 1024 * 512));
        }

        fclose($in);
        gzclose($out);
    }

    /**
     * Ruta de mysqldump: la configurada, la de Laragon si corre ahi, o la del PATH.
     */
    private function binary(): string
    {
        if ($configured = config('backup.mysqldump')) {
            return $configured;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            // Laragon: <raiz>\bin\php\php-8.x\php.exe -> <raiz>\bin\mysql\*\bin\mysqldump.exe
            $laragonBin = dirname(PHP_BINARY, 3);
            $found = glob($laragonBin.DIRECTORY_SEPARATOR.'mysql'.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'mysqldump.exe') ?: [];

            if ($found !== []) {
                rsort($found, SORT_NATURAL);

                return $found[0];
            }

            return 'mysqldump.exe';
        }

        return 'mysqldump';
    }
}
