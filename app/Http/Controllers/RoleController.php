<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::all();
        return response()->json($roles);
    }

    public function show($id)
    {
        $role = Role::findOrFail($id);
        return response()->json($role);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:roles,name',
            'description' => 'nullable|string',
            'is_system_role' => 'sometimes|boolean',
        ]);

        $role = Role::create([
            'name' => $request->name,
            'description' => $request->description,
            'is_system_role' => $request->get('is_system_role', false),
        ]);

        return response()->json($role, 201);
    }

    public function update(Request $request, Role $role)
    {
        $request->validate([
            'name' => 'required|string|unique:roles,name,' . $role->id,
            'description' => 'nullable|string',
            'is_system_role' => 'sometimes|boolean',
        ]);

        $role->update([
            'name' => $request->name,
            'description' => $request->description,
            'is_system_role' => $request->get('is_system_role', $role->is_system_role),
        ]);

        return response()->json($role);
    }

    public function destroy(Role $role)
    {
        if ($role->is_system_role) {
            return response()->json(['message' => 'Cannot delete a system role'], 403);
        }

        $role->delete();
        return response()->json(['message' => 'Role deleted successfully']);
    }

    public function assign(Request $request)
    {
        $request->validate([
            'role_id' => 'required|exists:roles,id',
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($request->user_id);
        $role = Role::findOrFail($request->role_id);

        $user->assignRole($role->id);

        return response()->json(['message' => 'Role assigned successfully']);
    }

    public function remove(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::findOrFail($request->user_id);

        $user->removeRole();

        return response()->json(['message' => 'Role assigned successfully']);
    }
}
