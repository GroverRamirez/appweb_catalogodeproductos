<?php

use App\Models\User;
use App\Support\AdminGuard;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->owner = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $this->owner->assignRole('propietario');
});

test('owner cannot remove their own propietario role', function () {
    $this->actingAs($this->owner)
        ->patch(route('admin.users.update', $this->owner), [
            'name' => $this->owner->name,
            'email' => $this->owner->email,
            'role' => 'vendedor',
        ])
        ->assertSessionHas('error');

    expect($this->owner->fresh()->hasRole('propietario'))->toBeTrue();
});

test('owner cannot delete their own account', function () {
    $this->actingAs($this->owner)
        ->delete(route('admin.users.destroy', $this->owner))
        ->assertSessionHas('error');

    $this->assertModelExists($this->owner);
});

test('admin guard detects the last owner', function () {
    expect(AdminGuard::isLastOwner($this->owner))->toBeTrue();
    expect(AdminGuard::wouldRemoveLastOwner($this->owner, ['vendedor']))->toBeTrue();
    expect(AdminGuard::wouldRemoveLastOwner($this->owner, ['propietario']))->toBeFalse();

    $second = User::factory()->create();
    $second->assignRole('propietario');

    expect(AdminGuard::isLastOwner($this->owner))->toBeFalse();
});
