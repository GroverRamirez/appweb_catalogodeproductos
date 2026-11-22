<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->dir = storage_path('framework/testing/backups');
    File::deleteDirectory($this->dir);
    config(['backup.path' => $this->dir, 'backup.connection' => 'mysql']);

    $this->owner = User::factory()->create();
    $this->owner->assignRole('propietario');
});

afterEach(function () {
    File::deleteDirectory($this->dir);
});

function fakeBackup(string $dir, string $name = 'db_catalogo_20261005_120000.sql.gz'): string
{
    File::ensureDirectoryExists($dir);
    File::put($dir.'/'.$name, gzencode('-- volcado de prueba'));

    return $name;
}

test('the owner generates a compressed backup with mysqldump', function () {
    Process::fake(function (PendingProcess $process) {
        // Simula mysqldump escribiendo el volcado en --result-file.
        $arg = collect($process->command)->first(fn ($a) => str_starts_with($a, '--result-file='));
        File::put(substr($arg, strlen('--result-file=')), 'CREATE TABLE productos (id int);');

        return Process::result();
    });

    $this->actingAs($this->owner)
        ->post(route('admin.backups.store'))
        ->assertRedirect()
        ->assertSessionHas('success');

    $files = File::files($this->dir);
    expect($files)->toHaveCount(1)
        ->and($files[0]->getFilename())->toMatch('/^db_.+_\d{8}_\d{6}\.sql\.gz$/')
        ->and(gzdecode(File::get($files[0]->getPathname())))->toContain('CREATE TABLE productos');

    // La contrasena nunca va en la linea de comandos.
    Process::assertRan(fn (PendingProcess $p) => collect($p->command)->doesntContain(fn ($a) => str_starts_with($a, '--password')));
});

test('a failed dump reports the error and leaves no files behind', function () {
    Process::fake(fn () => Process::result(errorOutput: 'Access denied', exitCode: 2));

    $this->actingAs($this->owner)
        ->post(route('admin.backups.store'))
        ->assertSessionHas('error', fn ($msg) => str_contains($msg, 'Access denied'));

    expect(File::files($this->dir))->toBeEmpty();
});

test('the owner lists and downloads backups', function () {
    $name = fakeBackup($this->dir);

    $this->actingAs($this->owner)
        ->get(route('admin.backups.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/settings/Backups')
            ->has('backups', 1)
            ->where('backups.0.name', $name));

    $this->actingAs($this->owner)
        ->get(route('admin.backups.download', $name))
        ->assertOk()
        ->assertDownload($name);
});

test('the owner deletes a backup', function () {
    $name = fakeBackup($this->dir);

    $this->actingAs($this->owner)
        ->delete(route('admin.backups.destroy', $name))
        ->assertSessionHas('success');

    expect(File::exists($this->dir.'/'.$name))->toBeFalse();
});

test('names outside the backup format are rejected', function () {
    fakeBackup($this->dir);
    File::put($this->dir.'/.env', 'APP_KEY=secreto');

    $this->actingAs($this->owner)
        ->get(route('admin.backups.download', '.env'))
        ->assertNotFound();

    $this->actingAs($this->owner)
        ->get('/admin/settings/backups/..%2F..%2F.env')
        ->assertNotFound();
});

test('staff without the permission cannot touch backups', function (string $role) {
    $name = fakeBackup($this->dir);
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)->get(route('admin.backups.index'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.backups.store'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.backups.download', $name))->assertForbidden();
    $this->actingAs($user)->delete(route('admin.backups.destroy', $name))->assertForbidden();

    expect(File::exists($this->dir.'/'.$name))->toBeTrue();
})->with(['encargado', 'vendedor']);

test('with sqlite the owner gets a compressed copy of the database file', function () {
    $file = storage_path('framework/testing/backup-origen.sqlite');
    File::ensureDirectoryExists(dirname($file));
    File::put($file, '');
    config([
        'database.connections.origen' => ['driver' => 'sqlite', 'database' => $file, 'prefix' => '', 'foreign_key_constraints' => true],
        'backup.connection' => 'origen',
    ]);
    DB::connection('origen')->statement('create table productos (id integer, nombre text)');
    DB::connection('origen')->table('productos')->insert(['id' => 1, 'nombre' => 'Monitor']);

    $this->actingAs($this->owner)
        ->post(route('admin.backups.store'))
        ->assertSessionHas('success');

    $files = File::files($this->dir);
    expect($files)->toHaveCount(1)
        ->and($files[0]->getFilename())->toMatch('/^db_backup-origen_\d{8}_\d{6}\.sqlite\.gz$/');

    // La copia es una base SQLite que se abre y tiene los datos.
    $restored = $this->dir.'/restaurada.sqlite';
    File::put($restored, gzdecode(File::get($files[0]->getPathname())));
    config(['database.connections.restaurada' => ['driver' => 'sqlite', 'database' => $restored, 'prefix' => '']]);
    expect(DB::connection('restaurada')->table('productos')->value('nombre'))->toBe('Monitor');

    DB::purge('origen');
    DB::purge('restaurada');
    File::delete($file);

    // El panel la lista; el archivo descomprimido de la prueba no cuenta.
    $this->actingAs($this->owner)
        ->get(route('admin.backups.index'))
        ->assertInertia(fn ($page) => $page->has('backups', 1)
            ->where('backups.0.name', $files[0]->getFilename()));
});
