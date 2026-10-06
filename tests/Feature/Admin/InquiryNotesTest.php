<?php

use App\Models\Inquiry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->vendedor = User::factory()->create();
    $this->vendedor->assignRole('vendedor');

    $this->inquiry = Inquiry::create([
        'customer_name' => 'Cliente Test',
        'customer_phone' => '+59170000000',
        'status' => 'pendiente',
        'source' => 'web',
    ]);
});

test('staff with inquiries.update can add a note', function () {
    $this->actingAs($this->vendedor)
        ->from(route('admin.inquiries.show', $this->inquiry))
        ->post(route('admin.inquiries.notes.store', $this->inquiry), [
            'body' => 'Llamé al cliente, pidió cotización formal.',
        ])
        ->assertRedirect(route('admin.inquiries.show', $this->inquiry));

    $this->assertDatabaseHas('consulta_notas', [
        'consulta_id' => $this->inquiry->id,
        'user_id' => $this->vendedor->id,
        'cuerpo' => 'Llamé al cliente, pidió cotización formal.',
    ]);
});

test('note body is required and limited to 2000 characters', function () {
    $this->actingAs($this->vendedor)
        ->post(route('admin.inquiries.notes.store', $this->inquiry), ['body' => ''])
        ->assertSessionHasErrors('body');

    $this->actingAs($this->vendedor)
        ->post(route('admin.inquiries.notes.store', $this->inquiry), [
            'body' => str_repeat('a', 2001),
        ])
        ->assertSessionHasErrors('body');

    expect($this->inquiry->notes()->count())->toBe(0);
});

test('staff without inquiries.update cannot add notes', function () {
    $viewer = User::factory()->create();
    $role = Role::create(['name' => 'solo-lectura', 'guard_name' => 'web']);
    $role->givePermissionTo('inquiries.view');
    $viewer->assignRole($role);

    $this->actingAs($viewer)
        ->post(route('admin.inquiries.notes.store', $this->inquiry), [
            'body' => 'No debería guardarse.',
        ])
        ->assertForbidden();

    expect($this->inquiry->notes()->count())->toBe(0);
});

test('changing the status records an automatic note in the history', function () {
    $this->actingAs($this->vendedor)
        ->patch(route('admin.inquiries.update', $this->inquiry), [
            'status' => 'contactado',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('consulta_notas', [
        'consulta_id' => $this->inquiry->id,
        'user_id' => $this->vendedor->id,
        'cuerpo' => 'Cambió el estado de "pendiente" a "contactado".',
    ]);
});

test('saving without changing the status does not record a note', function () {
    $this->actingAs($this->vendedor)
        ->patch(route('admin.inquiries.update', $this->inquiry), [
            'status' => 'pendiente',
        ])
        ->assertRedirect();

    expect($this->inquiry->notes()->count())->toBe(0);
});

test('the csv export carries the note history', function () {
    // El historial es el unico lugar donde viven las notas desde que se retiro
    // `consultas.notas_admin`; si el export no lo lee, la columna Notas sale
    // siempre vacia.
    $this->actingAs($this->vendedor)
        ->post(route('admin.inquiries.notes.store', $this->inquiry), [
            'body' => 'Llamé y quedó en pasar el jueves.',
        ]);

    $this->actingAs($this->vendedor)
        ->post(route('admin.inquiries.notes.store', $this->inquiry), [
            'body' => 'Pasó y se llevó dos.',
        ]);

    $response = $this->actingAs($this->vendedor)->get(route('admin.inquiries.export'));
    $response->assertOk();

    $csv = $response->streamedContent();

    expect($csv)->toContain('Llamé y quedó en pasar el jueves.');
    expect($csv)->toContain('Pasó y se llevó dos.');

    // En orden cronologico, no al reves.
    expect(strpos($csv, 'Llamé y quedó'))->toBeLessThan(strpos($csv, 'Pasó y se llevó'));
});

test('inquiry detail page includes the notes with their author', function () {
    $this->inquiry->notes()->create([
        'user_id' => $this->vendedor->id,
        'body' => 'Nota de prueba.',
    ]);

    $this->actingAs($this->vendedor)
        ->get(route('admin.inquiries.show', $this->inquiry))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/inquiries/Show')
            ->has('inquiry.notes', 1)
            ->where('inquiry.notes.0.body', 'Nota de prueba.')
            ->where('inquiry.notes.0.author.name', $this->vendedor->name)
        );
});

test('pending inquiries count is shared with staff for the sidebar badge', function () {
    Inquiry::create([
        'customer_name' => 'Otro Cliente',
        'customer_phone' => '+59171111111',
        'status' => 'contactado',
        'source' => 'web',
    ]);

    $this->actingAs($this->vendedor)
        ->get(route('admin.inquiries.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('pendingInquiries', 1));
});

test('pending inquiries count is not exposed to users without permission', function () {
    $cliente = User::factory()->create();
    $cliente->assignRole('cliente');

    $this->actingAs($cliente)
        ->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('pendingInquiries', null));
});
