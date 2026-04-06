<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Notifications\UserCreatedNotification;
use App\Services\AuditLogger;
use App\Services\CacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class UserController extends Controller
{
    public function __construct()
    {
        // Apply policy to resource routes (index, show, store, update, destroy)
        $this->authorizeResource(User::class, 'user');
    }

    public function index(Request $request)
    {
        // Pagination clamp and sorting
        $perPage = (int) ($request->input('per_page', 15));
        $perPage = max(5, min(100, $perPage));
        $allowedSort = ['name', 'email', 'created_at', 'department_id', 'is_active'];
        $sortBy = $request->input('sort_by', 'name');
        $sortBy = in_array($sortBy, $allowedSort, true) ? $sortBy : 'name';
        $sortOrder = strtolower($request->input('sort_order', 'asc')) === 'desc' ? 'desc' : 'asc';

        $users = User::with(['roles', 'department'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('department_id'), function ($query) use ($request) {
                $query->where('department_id', $request->department_id);
            })
            ->when($request->filled('is_active'), function ($query) use ($request) {
                $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
            })
            ->when($request->filled('role'), function ($query) use ($request) {
                $query->whereHas('roles', function ($roleQuery) use ($request) {
                    $roleQuery->where('name', $request->role);
                });
            })
            ->when($request->filled('permission'), function ($query) use ($request) {
                $query->whereHas('permissions', function ($permQuery) use ($request) {
                    $permQuery->where('name', $request->permission);
                });
            })
            ->orderBy($sortBy, $sortOrder)
            ->paginate($perPage);

        return ApiResponse::paginated($users, 'Users retrieved successfully');
    }

    public function store(StoreUserRequest $request)
    {
        // Set default password if not provided
        $plainPassword = $request->password ?: 'Password123';
        
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($plainPassword),
            'department_id' => $request->department_id,
            'position' => $request->position,
            'pnpki_certificate_serial' => $request->pnpki_certificate_serial,
            'can_sign_digitally' => $request->can_sign_digitally ?? false,
            'is_active' => $request->is_active ?? true,
        ]);

        if ($request->filled('role')) {
            $user->assignRole($request->role);
        }

        // Send email notification with login credentials
        try {
            $user->notify(new UserCreatedNotification($plainPassword, Auth::user()->name));
        } catch (\Exception $e) {
            // Log the error but don't fail the user creation
            \Log::error('Failed to send user creation email: ' . $e->getMessage());
        }

        // Log user creation
        AuditLogger::logAuthEvent('user_created', $user, [
            'created_by' => Auth::user()->name,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'department_id' => $user->department_id,
            'assigned_role' => $request->role ?? null,
            'email_sent' => true,
        ]);

        return ApiResponse::created($user->load(['roles', 'permissions','department']), 'User created successfully. Password has been sent to the user\'s email.');
    }

    public function show(User $user)
    {
        // Try to get cached user first
        $cachedUser = CacheService::getCachedUser($user->id);
        if ($cachedUser) {
            return ApiResponse::success($cachedUser, 'User retrieved successfully (cached)');
        }
        
        // Load user with relationships and cache it
        $user->load(['roles', 'permissions','department']);
        CacheService::cacheUser($user);
        
        return ApiResponse::success($user, 'User retrieved successfully');
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $originalData = $user->toArray();
        $updateData = $request->only(['name', 'email', 'department_id', 'phone', 'position', 'pnpki_certificate_serial', 'can_sign_digitally', 'is_active']);
        
        if ($request->has('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $user->update($updateData);

        // Update role if provided and user has permission
        /** @var \App\Models\User $currentUser */
        $currentUser = Auth::user();
        if ($request->filled('role') && $currentUser->hasAnyRole(['admin', 'super-admin'])) {
            $user->syncRoles([$request->role]);
        }

        // Log user update
        AuditLogger::logAuthEvent('user_updated', $user, [
            'updated_by' => Auth::user()->name,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'changes' => array_diff_assoc($updateData, $originalData),
            'role_updated' => $request->has('role'),
            'new_role' => $request->role ?? null,
        ]);
        
        // Invalidate user cache since user was updated
        CacheService::invalidateUser($user->id);

        return ApiResponse::success($user->load(['roles', 'permissions','department']), 'User updated successfully');
    }

    public function destroy(User $user)
    {
        // Log user deletion before deleting
        AuditLogger::logAuthEvent('user_deleted', $user, [
            'deleted_by' => Auth::user()->name,
            'deleted_user_name' => $user->name,
            'deleted_user_email' => $user->email,
            'department_id' => $user->department_id,
        ]);
        
        // Invalidate user cache before deletion
        CacheService::invalidateUser($user->id);

        $user->delete();
        return ApiResponse::success(null, 'User deleted successfully');
    }

    public function assignRole(Request $request, User $user)
    {
        $this->authorize('assignRole', $user);
        $request->validate([
            'role' => 'required|string|exists:roles,name',
        ]);

        $user->assignRole($request->role);

        // Log role assignment
        AuditLogger::logAuthEvent('role_assigned', $user, [
            'assigned_by' => Auth::user()->name,
            'target_user' => $user->name,
            'role_assigned' => $request->role,
        ]);

        // Invalidate user cache since roles changed
        CacheService::invalidateUser($user->id);

        return ApiResponse::success([
            'message' => 'Role assigned successfully',
            'user' => $user->load(['roles', 'permissions'])
        ], 'Role assigned successfully');
    }

    public function removeRole(Request $request, User $user)
    {
        $this->authorize('removeRole', $user);
        $request->validate([
            'role' => 'required|string|exists:roles,name',
        ]);

        $user->removeRole($request->role);

        // Log role removal
        AuditLogger::logAuthEvent('role_removed', $user, [
            'removed_by' => Auth::user()->name,
            'target_user' => $user->name,
            'role_removed' => $request->role,
        ]);

        // Invalidate user cache since roles changed
        CacheService::invalidateUser($user->id);

        return ApiResponse::success([
            'message' => 'Role removed successfully',
            'user' => $user->load(['roles', 'permissions'])
        ], 'Role removed successfully');
    }

    public function assignPermission(Request $request, User $user)
    {
        $this->authorize('assignPermission', $user);
        $request->validate([
            'permission' => 'required|string|exists:permissions,name',
        ]);

        $user->givePermissionTo($request->permission);

        // Log permission assignment
        AuditLogger::logAuthEvent('permission_assigned', $user, [
            'assigned_by' => Auth::user()->name,
            'target_user' => $user->name,
            'permission_assigned' => $request->permission,
        ]);

        // Invalidate user cache since permissions changed
        CacheService::invalidateUser($user->id);

        return ApiResponse::success([
            'message' => 'Permission assigned successfully',
            'user' => $user->load(['roles', 'permissions'])
        ], 'Permission assigned successfully');
    }

    public function removePermission(Request $request, User $user)
    {
        $this->authorize('removePermission', $user);
        $request->validate([
            'permission' => 'required|string|exists:permissions,name',
        ]);

        $user->revokePermissionTo($request->permission);

        // Log permission removal
        AuditLogger::logAuthEvent('permission_removed', $user, [
            'removed_by' => Auth::user()->name,
            'target_user' => $user->name,
            'permission_removed' => $request->permission,
        ]);

        // Invalidate user cache since permissions changed
        CacheService::invalidateUser($user->id);

        return ApiResponse::success([
            'message' => 'Permission removed successfully',
            'user' => $user->load(['roles', 'permissions'])
        ], 'Permission removed successfully');
    }

    public function getRoles()
    {
        $roles = Role::all(['id', 'name']);
        return ApiResponse::success($roles, 'Roles retrieved successfully');
    }

    public function getPermissions()
    {
        $permissions = Permission::all(['id', 'name']);
        return ApiResponse::success($permissions, 'Permissions retrieved successfully');
    }

    /**
     * Update a user's password.
     * - Self update requires current_password verification
     * - Admins/super-admins can update others without current password
     */
    public function updatePassword(Request $request, User $user)
    {
        $this->authorize('changePassword', $user);

        /** @var \App\Models\User $actor */
        $actor = Auth::user();

        // Validation rules differ for self vs admin reset
        if ($actor->id === $user->id) {
            $validated = $request->validate([
                'current_password' => ['required', 'string'],
                'password' => ['required', 'string', 'min:8'],
            ]);

            if (!Hash::check($validated['current_password'], $user->password)) {
                return ApiResponse::forbidden('Current password is incorrect');
            }
        } else {
            $validated = $request->validate([
                'password' => ['required', 'string', 'min:8'],
            ]);
        }

        // Update password
        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Audit event
        AuditLogger::logAuthEvent('password_changed', $user, [
            'changed_by' => $actor->name,
            'target_user' => $user->email,
            'self_changed' => $actor->id === $user->id,
        ]);

        // Invalidate user cache
        CacheService::invalidateUser($user->id);

        // Revoke existing tokens to force re-authentication
        try {
            $user->tokens()->delete();
        } catch (\Throwable $e) {
            // Ignore token revocation errors
        }

        return ApiResponse::noContent('Password updated successfully');
    }
}