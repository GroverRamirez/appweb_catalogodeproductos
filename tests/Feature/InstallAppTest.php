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
