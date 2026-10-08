<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\User;
use App\Support\AdminGuard;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;
use Throwable;

/**
 * Deja lista una instalacion nueva (o actualizada) de la tienda.
 *
 * Lo corre el instalador de Windows despues de copiar los archivos, pero sirve
 * en cualquier servidor. Es seguro repetirlo: en una instalacion que ya tiene
 * datos solo aplica las migraciones pendientes y no toca nada mas.
 *
 * En una instalacion nueva ademas:
 * - genera la clave de la app (cada instalacion la suya, nunca la de desarrollo),
 * - carga los roles con sus permisos y la configuracion por defecto,
 * - crea al propietario con los datos que se pasen en --datos,
 * - enlaza public/storage para que se vean las imagenes que se suban.
 *
 * --datos apunta a un JSON {"tienda", "nombre", "email", "password"}. Va en un
 * archivo y no en la linea de comandos para que la contrasena no quede a la
 * vista de quien liste los procesos, y el archivo se borra apenas se lee.
 */
class InstallApp extends Command
{
    protected $signature = 'app:instalar
                            {--datos= : JSON con la tienda y el propietario a crear}';

    protected $description = 'Prepara la base, los roles y el usuario propietario de una instalacion';

    public function handle(): int
    {
        try {
            $datos = $this->leerDatos();

            $this->asegurarClave();
            $this->asegurarBaseSqlite();

            $this->info('Aplicando migraciones...');
            $this->call('migrate', ['--force' => true]);

            $nueva = Role::query()->doesntExist();

            if ($nueva) {
                // Solo la primera vez: en una actualizacion pisarian los
                // permisos y la configuracion que el propietario ya ajusto.
                $this->info('Cargando roles y configuracion inicial...');
                $this->callSilently('db:seed', ['--class' => RoleSeeder::class, '--force' => true]);
                $this->callSilently('db:seed', ['--class' => SettingsSeeder::class, '--force' => true]);
            }

            if ($datos !== null) {
                $this->crearPropietario($datos);
            } elseif (AdminGuard::ownerCount() === 0) {
                $this->warn('Todavia no hay propietario: volve a correr el comando con --datos.');
            }

            $this->enlazarImagenes();
            $this->call('optimize:clear');
        } catch (Throwable $e) {
            $this->registrar('ERROR: '.$e->getMessage());
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->registrar('Instalacion lista.');
        $this->info('Instalacion lista.');

        return self::SUCCESS;
    }

    /**
     * @return array{tienda: string, nombre: string, email: string, password: string}|null
     */
    private function leerDatos(): ?array
    {
        $archivo = $this->option('datos');

        if (! $archivo) {
            return null;
        }

        if (! File::isFile($archivo)) {
            throw new \RuntimeException("No se encontro el archivo de datos: {$archivo}");
        }

        $contenido = File::get($archivo);
        File::delete($archivo);

        // El instalador de Windows escribe UTF-8 con BOM; json_decode no lo acepta.
        $datos = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', $contenido), true);

        if (! is_array($datos)) {
            throw new \RuntimeException('El archivo de datos no es un JSON valido.');
        }

        $validador = Validator::make($datos, [
            'tienda' => ['nullable', 'string', 'max:120'],
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ], [], [
            'nombre' => 'nombre del propietario',
            'email' => 'correo del propietario',
            'password' => 'contrasena',
        ]);

        if ($validador->fails()) {
            throw new \RuntimeException(implode(' ', $validador->errors()->all()));
        }

        return [
            'tienda' => trim((string) ($datos['tienda'] ?? '')),
            'nombre' => trim($datos['nombre']),
            'email' => mb_strtolower(trim($datos['email'])),
            'password' => $datos['password'],
        ];
    }

    private function asegurarClave(): void
    {
        if (config('app.key')) {
            return;
        }

        // Ni migrar ni sembrar cifran nada, asi que alcanza con dejarla
        // escrita en el .env para cuando arranque la tienda.
        $this->info('Generando la clave de la aplicacion...');
        $this->callSilently('key:generate', ['--force' => true]);
    }

    /** SQLite necesita que el archivo exista antes de migrar. */
    private function asegurarBaseSqlite(): void
    {
        $conexion = config('database.default');

        if (config("database.connections.{$conexion}.driver") !== 'sqlite') {
            return;
        }

        $archivo = config("database.connections.{$conexion}.database");

        if ($archivo !== ':memory:' && ! File::exists($archivo)) {
            File::ensureDirectoryExists(dirname($archivo));
            File::put($archivo, '');
        }
    }

    /**
     * @param  array{tienda: string, nombre: string, email: string, password: string}  $datos
     */
    private function crearPropietario(array $datos): void
    {
        DB::transaction(function () use ($datos) {
            $usuario = User::query()->where('email', $datos['email'])->first() ?? new User;

            $usuario->forceFill([
                'name' => $datos['nombre'],
                'email' => $datos['email'],
                'password' => Hash::make($datos['password']),
                'email_verified_at' => $usuario->email_verified_at ?? now(),
            ])->save();

            $usuario->assignRole(AdminGuard::OWNER_ROLE);

            if ($datos['tienda'] !== '') {
                Setting::set('store_name', $datos['tienda'], 'string', 'general', 'Nombre de la tienda');
            }
        });

        $this->info("Propietario listo: {$datos['email']}");
    }

    /**
     * public/storage apunta a storage/app/public. En Windows Laravel usa una
     * union de carpetas (mklink /J), que no pide permisos de administrador.
     */
    private function enlazarImagenes(): void
    {
        File::ensureDirectoryExists(storage_path('app/public'));

        if (! file_exists(public_path('storage'))) {
            $this->callSilently('storage:link');
        }
    }

    /** Deja rastro en storage/logs/instalacion.log para diagnosticar desde el instalador. */
    private function registrar(string $mensaje): void
    {
        try {
            File::ensureDirectoryExists(storage_path('logs'));
            File::append(storage_path('logs/instalacion.log'), '['.date('Y-m-d H:i:s').'] '.$mensaje.PHP_EOL);
        } catch (Throwable) {
            // Sin log no se frena la instalacion.
        }
    }
}
