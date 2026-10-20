<?php

use App\Models\User;
use App\Support\AdminGuard;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->owner = User::factory()->create();
    $this->owner->assignRole('propietario');
});

test('users can no longer be deleted from the admin panel', function () {
    $seller = User::factory()->create();
    $seller->assignRole('vendedor');

    $this->actingAs($this->owner)
        ->delete("/admin/users/{$seller->id}")
        ->assertStatus(405);

    $this->assertModelExists($seller);
});

test('owner can deactivate and reactivate another user', function () {
    $seller = User::factory()->create();
    $seller->assignRole('vendedor');

    $this->actingAs($this->owner)
        ->patch(route('admin.users.toggle-active', $seller))
        ->assertSessionHas('success', 'Usuario desactivado.');

    expect($seller->fresh()->is_active)->toBeFalse();

    $this->actingAs($this->owner)
        ->patch(route('admin.users.toggle-active', $seller))
        ->assertSessionHas('success', 'Usuario activado.');

    expect($seller->fresh()->is_active)->toBeTrue();
});

test('inactive owners do not count as the last owner', function () {
    $other = User::factory()->create();
    $other->assignRole('propietario');
    $other->forceFill(['is_active' => false])->save();

    expect(AdminGuard::isLastOwner($this->owner))->toBeTrue();
    expect(AdminGuard::isLastOwner($other->fresh()))->toBeFalse();
});

test('a deactivated user cannot log in with password', function () {
    $seller = User::factory()->create();
    $seller->assignRole('vendedor');
    $seller->forceFill(['is_active' => false])->save();

    $this->post(route('login.store'), [
        'email' => $seller->email,
        'password' => 'password',
    ]);

    $this->get('/admin')->assertRedirect(route('login'));
    $this->assertGuest();
});

test('a user deactivated while logged in is signed out on the next request', function () {
    $seller = User::factory()->create();
    $seller->assignRole('vendedor');

    $this->actingAs($seller);
    $seller->forceFill(['is_active' => false])->save();

    $this->get('/admin')
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');

    $this->assertGuest();
});

test('a user without the is_active value is not treated as deactivated', function () {
    // Pasa cuando la migración de is_active todavía no se corrió.
    $seller = User::factory()->create();
    $seller->assignRole('vendedor');
    $seller->offsetUnset('is_active');

    $this->actingAs($seller)
        ->get('/admin')
        ->assertOk();

    $this->assertAuthenticatedAs($seller);
});

test('staff cannot delete their own account from settings', function () {
    $seller = User::factory()->create();
    $seller->assignRole('vendedor');

    $this->actingAs($seller)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHas('error');

    $this->assertModelExists($seller);
});
