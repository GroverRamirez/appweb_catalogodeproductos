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
 * - crea al propietario con los datos que se pasen en --datos (o que se
 *   escriban al correrlo a mano en un servidor),
 * - deja el correo de contacto y el WhatsApp de la tienda, para que los avisos
 *   de consultas y el boton de WhatsApp no apunten a datos de ejemplo,
 * - enlaza public/storage para que se vean las imagenes que se suban.
 *
 * --datos apunta a un JSON {"tienda", "nombre", "email", "password", "whatsapp",
 * "email_contacto"} (los dos ultimos son opcionales). Va en un archivo y no en
 * la linea de comandos para que la contrasena no quede a la vista de quien
 * liste los procesos, y el archivo se borra apenas se lee.
 *
 * Sin --datos y en una terminal interactiva, si todavia no hay propietario, el
 * comando pregunta los datos: es la forma de crear el primero en un servidor
 * (`docker compose exec app php artisan app:instalar`).
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

            if ($datos === null && AdminGuard::ownerCount() === 0 && $this->input->isInteractive()) {
                $datos = $this->preguntarDatos();
            }

            if ($datos !== null) {
                $this->crearPropietario($datos);
            } elseif (AdminGuard::ownerCount() === 0) {
                $this->warn('Todavia no hay propietario: volve a correr el comando con --datos.');
            }

            $this->avisarContactoFaltante();

            $this->enlazarImagenes();

            // En Docker el arranque ya cacheo config y rutas: se limpian para
            // que tomen lo nuevo y se vuelven a cachear, en vez de dejar la app
            // corriendo sin cache.
            $estabaCacheada = $this->laravel->configurationIsCached();
            $this->call('optimize:clear');

            if ($estabaCacheada) {
                $this->call('optimize');
            }
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
     * @return array{tienda: string, nombre: string, email: string, password: string, whatsapp: string, email_contacto: string}|null
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

        return $this->validarDatos($datos);
    }

    /**
     * Pregunta los datos en la terminal: la contrasena no se ve al escribirla
     * ni queda en el historial de comandos.
     *
     * @return array{tienda: string, nombre: string, email: string, password: string, whatsapp: string, email_contacto: string}
     */
    private function preguntarDatos(): array
    {
        $this->info('Todavia no hay propietario. Completa sus datos:');

        $password = (string) $this->secret('Contrasena (minimo 8 caracteres)');

        if ($password !== (string) $this->secret('Repeti la contrasena')) {
            throw new \RuntimeException('Las dos contrasenas no coinciden.');
        }

        return $this->validarDatos([
            'tienda' => $this->ask('Nombre de la tienda', (string) Setting::get('store_name', '')),
            'nombre' => $this->ask('Nombre del propietario'),
            'email' => $this->ask('Correo del propietario (es su usuario para entrar)'),
            'password' => $password,
            'whatsapp' => $this->ask('WhatsApp de la tienda, con codigo de pais (ej. +59170000000)'),
            'email_contacto' => $this->ask('Correo donde llegan los avisos de consultas (vacio = el del propietario)'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array{tienda: string, nombre: string, email: string, password: string, whatsapp: string, email_contacto: string}
     */
    private function validarDatos(array $datos): array
    {
        $validador = Validator::make($datos, [
            'tienda' => ['nullable', 'string', 'max:120'],
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
            'whatsapp' => ['nullable', 'string', 'regex:/^\+?(?:\s*[0-9]){7,20}\s*$/'],
            'email_contacto' => ['nullable', 'email', 'max:255'],
        ], [], [
            'nombre' => 'nombre del propietario',
            'email' => 'correo del propietario',
            'password' => 'contrasena',
            'whatsapp' => 'WhatsApp de la tienda',
            'email_contacto' => 'correo de contacto',
        ]);

        if ($validador->fails()) {
            throw new \RuntimeException(implode(' ', $validador->errors()->all()));
        }

        return [
            'tienda' => trim((string) ($datos['tienda'] ?? '')),
            'nombre' => trim($datos['nombre']),
            'email' => mb_strtolower(trim($datos['email'])),
            'password' => $datos['password'],
            'whatsapp' => trim((string) ($datos['whatsapp'] ?? '')),
            'email_contacto' => mb_strtolower(trim((string) ($datos['email_contacto'] ?? ''))),
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
     * @param  array{tienda: string, nombre: string, email: string, password: string, whatsapp: string, email_contacto: string}  $datos
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

            if ($datos['whatsapp'] !== '') {
                Setting::set('whatsapp_number', $datos['whatsapp'], 'string', 'contacto', 'Número de WhatsApp (formato internacional)');
            }

            // Los avisos de consultas nuevas van a este correo: si no se dio
            // otro y todavia no hay ninguno, el del propietario.
            $contacto = $datos['email_contacto'] ?: (Setting::get('email_contact') ?: $datos['email']);
            Setting::set('email_contact', $contacto, 'string', 'contacto', 'Email de contacto');
        });

        $this->info("Propietario listo: {$datos['email']}");
    }

    /** Recuerda lo que falta para que los clientes puedan escribirle a la tienda. */
    private function avisarContactoFaltante(): void
    {
        if (! Setting::get('whatsapp_number')) {
            $this->warn('Falta el WhatsApp de la tienda: cargalo en Configuracion para que aparezca el boton en el catalogo.');
        }

        if (! Setting::get('email_contact')) {
            $this->warn('Falta el correo de contacto: sin el, los avisos de consultas nuevas no llegan a nadie.');
        }
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
