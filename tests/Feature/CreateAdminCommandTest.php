<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_admin(): void
    {
        $this->artisan('app:create-admin', ['--name' => 'Chủ quán', '--email' => 'owner@test.vn', '--password' => 'mat-khau-123'])
            ->assertSuccessful();

        $user = User::firstWhere('email', 'owner@test.vn');
        $this->assertSame(UserRole::Admin, $user->role);
        $this->assertTrue(Hash::check('mat-khau-123', $user->password));
    }

    public function test_promotes_existing_user_and_validates(): void
    {
        User::factory()->create(['email' => 'staff@test.vn']);

        $this->artisan('app:create-admin', ['--name' => 'A', '--email' => 'staff@test.vn', '--password' => 'mat-khau-123'])->assertSuccessful();
        $this->assertSame(UserRole::Admin, User::firstWhere('email', 'staff@test.vn')->role);

        $this->artisan('app:create-admin', ['--name' => 'A', '--email' => 'sai-email', '--password' => '123'])->assertFailed();
    }
}
