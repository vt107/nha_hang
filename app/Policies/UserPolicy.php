<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Admin quản lý mọi tài khoản; quản lý chỉ quản lý tài khoản phục vụ / bếp.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin, UserRole::Manager);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, User $target): bool
    {
        return $user->role === UserRole::Admin
            || ($user->role === UserRole::Manager && in_array($target->role, self::managedByManager(), true));
    }

    public function delete(User $user, User $target): bool
    {
        return $user->isNot($target) && $this->update($user, $target);
    }

    public function restore(User $user, User $target): bool
    {
        return $this->update($user, $target);
    }

    public function forceDelete(User $user, User $target): bool
    {
        return false;
    }

    /**
     * @return list<UserRole>
     */
    public static function managedByManager(): array
    {
        return [UserRole::Waiter, UserRole::Kitchen];
    }
}
