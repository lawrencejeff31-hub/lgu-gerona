<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    private function isAdmin(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, User $model): bool
    {
        return $this->isAdmin($user) || $user->id === $model->id;
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, User $model): bool
    {
        return $this->isAdmin($user) || $user->id === $model->id;
    }

    public function delete(User $user, User $model): bool
    {
        if (!$this->isAdmin($user)) {
            return false;
        }

        // Prevent self-deletion
        if ($user->id === $model->id) {
            return false;
        }

        // Prevent deleting last super-admin, and require super-admin to delete another super-admin
        if ($model->hasRole('super-admin')) {
            $superCount = \App\Models\User::role('super-admin')->count();
            if ($superCount <= 1) {
                return false;
            }
            if (!$user->hasRole('super-admin')) {
                return false;
            }
        }

        return true;
    }

    // Custom actions for role and permission administration
    public function assignRole(User $user, User $model): bool
    {
        // Admins only, and cannot change their own roles to avoid lockouts
        return $this->isAdmin($user) && $user->id !== $model->id;
    }

    public function removeRole(User $user, User $model): bool
    {
        if (!$this->isAdmin($user) || $user->id === $model->id) {
            return false;
        }

        // Prevent removing last super-admin role
        if ($model->hasRole('super-admin')) {
            $superCount = \App\Models\User::role('super-admin')->count();
            if ($superCount <= 1) {
                return false;
            }
            if (!$user->hasRole('super-admin')) {
                return false;
            }
        }

        return true;
    }

    public function assignPermission(User $user, User $model): bool
    {
        return $this->isAdmin($user) && $user->id !== $model->id;
    }

    public function removePermission(User $user, User $model): bool
    {
        return $this->isAdmin($user) && $user->id !== $model->id;
    }

    // Change password: users can change their own; admins can change others
    public function changePassword(User $user, User $model): bool
    {
        // Allow self password change
        if ($user->id === $model->id) {
            return true;
        }

        // Admins/super-admins can change others' passwords
        return $this->isAdmin($user);
    }
}