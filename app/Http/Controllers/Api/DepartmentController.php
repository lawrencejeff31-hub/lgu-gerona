<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Department;
use App\Services\CacheService;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index()
    {
        // Use caching for department list
        $departments = CacheService::getDepartments();
        return ApiResponse::success($departments, 'Departments retrieved successfully');
    }

    public function store(StoreDepartmentRequest $request)
    {
        $department = Department::create([
            'code' => $request->code,
            'name' => $request->name,
            'description' => $request->description,
            'is_active' => $request->is_active ?? true,
            'head_user_id' => $request->head_user_id,
            'parent_id' => $request->parent_id,
        ]);
        
        // Invalidate departments cache since a new department was created
        CacheService::invalidateDepartments();

        return ApiResponse::created($department->load('headUser', 'parent'), 'Department created successfully');
    }

    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            'code' => 'sometimes|required|string|max:50|unique:departments,code,' . $department->id,
            'name' => 'sometimes|required|string|max:255|unique:departments,name,' . $department->id,
            'description' => 'nullable|string|max:1000',
            'is_active' => 'sometimes|boolean',
        ]);

        $department->update($validated);
        
        // Invalidate departments cache since department was updated
        CacheService::invalidateDepartments();

        return ApiResponse::success($department, 'Department updated successfully');
    }

    public function destroy(Department $department)
    {
        $department->delete();
        
        // Invalidate departments cache since department was deleted
        CacheService::invalidateDepartments();
        
        return ApiResponse::success(null, 'Department deleted successfully');
    }
}
