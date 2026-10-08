<?php

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->datos = storage_path('framework/testing/propietario.json');
    File::ensureDirectoryExists(dirname($this->datos));
});

afterEach(function () {
    File::delete($this->datos);
});

function escribirDatos(string $archivo, array $datos): void
{
    // Con BOM, como lo escribe el instalador de Windows.
    File::put($archivo, "\xEF\xBB\xBF".json_encode($datos));
}

test('a fresh install loads roles, settings and creates the owner', function () {
    escribirDatos($this->datos, [
        'tienda' => 'Compu Center',
        'nombre' => 'Ana Rojas',
        'email' => 'Ana@Tienda.bo',
        'password' => 'clave-segura-1',
    ]);

    $this->artisan('app:instalar', ['--datos' => $this->datos])->assertSuccessful();

    $owner = User::where('email', 'ana@tienda.bo')->firstOrFail();
    expect($owner->hasRole('propietario'))->toBeTrue()
        ->and(Hash::check('clave-segura-1', $owner->password))->toBeTrue()
        ->and($owner->email_verified_at)->not->toBeNull()
        ->and(Role::pluck('name')->all())->toContain('encargado', 'vendedor', 'cliente')
        ->and(Setting::get('store_name'))->toBe('Compu Center')
        ->and(User::count())->toBe(1);

    // El archivo con la contrasena no queda en disco.
    expect(File::exists($this->datos))->toBeFalse();
});

test('running it again keeps the settings the owner already changed', function () {
    escribirDatos($this->datos, ['nombre' => 'Ana', 'email' => 'ana@tienda.bo', 'password' => 'clave-segura-1']);
    $this->artisan('app:instalar', ['--datos' => $this->datos])->assertSuccessful();

    Setting::set('whatsapp_number', '+59170000000', 'string', 'contacto');
    Role::findByName('vendedor')->givePermissionTo('sales.void');

    $this->artisan('app:instalar')->assertSuccessful();

    expect(Setting::get('whatsapp_number'))->toBe('+59170000000')
        ->and(Role::findByName('vendedor')->hasPermissionTo('sales.void'))->toBeTrue();
});

test('invalid owner data fails without creating anyone', function () {
    escribirDatos($this->datos, ['nombre' => 'Ana', 'email' => 'no-es-correo', 'password' => 'corta']);

    $this->artisan('app:instalar', ['--datos' => $this->datos])->assertFailed();

    expect(User::count())->toBe(0);
});

test('the store contact comes from the install data, or falls back to the owner email', function () {
    escribirDatos($this->datos, [
        'nombre' => 'Ana',
        'email' => 'ana@tienda.bo',
        'password' => 'clave-segura-1',
        'whatsapp' => '+591 70000000',
    ]);

    $this->artisan('app:instalar', ['--datos' => $this->datos])->assertSuccessful();

    expect(Setting::get('whatsapp_number'))->toBe('+591 70000000')
        ->and(Setting::get('email_contact'))->toBe('ana@tienda.bo');
});

test('an invalid whatsapp number is rejected', function () {
    escribirDatos($this->datos, [
        'nombre' => 'Ana',
        'email' => 'ana@tienda.bo',
        'password' => 'clave-segura-1',
        'whatsapp' => 'llamame',
    ]);

    $this->artisan('app:instalar', ['--datos' => $this->datos])->assertFailed();

    expect(User::count())->toBe(0);
});

test('on a server it asks for the first owner instead of using demo users', function () {
    $this->artisan('app:instalar')
        ->expectsQuestion('Contrasena (minimo 8 caracteres)', 'clave-segura-1')
        ->expectsQuestion('Repeti la contrasena', 'clave-segura-1')
        ->expectsQuestion('Nombre de la tienda', 'Compu Center')
        ->expectsQuestion('Nombre del propietario', 'Ana Rojas')
        ->expectsQuestion('Correo del propietario (es su usuario para entrar)', 'ana@tienda.bo')
        ->expectsQuestion('WhatsApp de la tienda, con codigo de pais (ej. +59170000000)', '+59170000000')
        ->expectsQuestion('Correo donde llegan los avisos de consultas (vacio = el del propietario)', 'ventas@tienda.bo')
        ->assertSuccessful();

    $owner = User::where('email', 'ana@tienda.bo')->firstOrFail();

    expect($owner->hasRole('propietario'))->toBeTrue()
        ->and(Hash::check('clave-segura-1', $owner->password))->toBeTrue()
        ->and(Setting::get('store_name'))->toBe('Compu Center')
        ->and(Setting::get('whatsapp_number'))->toBe('+59170000000')
        ->and(Setting::get('email_contact'))->toBe('ventas@tienda.bo');
});

test('mismatched passwords typed on the server create nobody', function () {
    $this->artisan('app:instalar')
        ->expectsQuestion('Contrasena (minimo 8 caracteres)', 'clave-segura-1')
        ->expectsQuestion('Repeti la contrasena', 'otra-clave-22')
        ->assertFailed();

    expect(User::count())->toBe(0);
});

test('a fresh install has no example contact data', function () {
    $this->artisan('app:instalar', ['--no-interaction' => true])->assertSuccessful();

    expect(Setting::get('whatsapp_number'))->toBe('')
        ->and(Setting::get('email_contact'))->toBe('')
        ->and(Setting::get('address'))->toBe('');
});

test('the demo users seeder does nothing in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('db:seed', ['--class' => 'AdminUserSeeder', '--force' => true])
        ->assertSuccessful();

    expect(User::count())->toBe(0);
});
