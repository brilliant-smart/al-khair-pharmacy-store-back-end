<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * List users (Master Admin only)
     */
    public function index()
    {
        $this->authorizeMaster();

        return response()->json(
            User::with('department:id,name')
                ->select('id', 'name', 'email', 'role', 'department_id', 'is_active')
                ->get()
        );
    }

    /**
     * Create user (section head or master admin)
     */
    public function store(Request $request)
    {
        $this->authorizeMaster();

        $validated = $request->validate([
            'name'          => 'required|string',
            'email'         => 'required|email|unique:users',
            'password'      => 'required|min:8',
            'role'          => 'required|in:master_admin,section_head',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        if (
            $validated['role'] === 'section_head'
            && empty($validated['department_id'])
        ) {
            return response()->json([
                'message' => 'Section head must have a department.',
            ], 422);
        }

        if ($validated['role'] === 'master_admin') {
            $validated['department_id'] = null;
        }

        $user = User::create([
            'name'          => $validated['name'],
            'email'         => $validated['email'],
            'password'      => Hash::make($validated['password']),
            'role'          => $validated['role'],
            'department_id' => $validated['department_id'],
        ]);

        return response()->json($user, 201);
    }

    /**
     * Update user (role / department / status / profile)
     */
    public function update(Request $request, User $user)
    {
        $this->authorizeMaster();

        $validated = $request->validate([
            'name'          => 'sometimes|string|max:255',
            'email'         => 'sometimes|email|unique:users,email,' . $user->id,
            'password'      => 'nullable|min:8',
            'role'          => 'sometimes|in:master_admin,section_head',
            'department_id' => 'nullable|exists:departments,id',
            'is_active'     => 'boolean',
        ]);

        if (
            ($validated['role'] ?? $user->role) === 'section_head'
            && empty($validated['department_id'] ?? $user->department_id)
        ) {
            return response()->json([
                'message' => 'Section head must have a department.',
            ], 422);
        }

        if (($validated['role'] ?? null) === 'master_admin') {
            $validated['department_id'] = null;
        }

        // Hash password if provided
        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json($user);
    }

    /**
     * Soft deactivate user
     */
    public function destroy(User $user)
    {
        $this->authorizeMaster();

        $user->update(['is_active' => false]);

        return response()->json(['message' => 'User deactivated']);
    }

    /**
     * Master Admin Gate
     */
    private function authorizeMaster(): void
    {
        abort_unless(
            auth()->user()?->isMasterAdmin(),
            403,
            'Unauthorized'
        );
    }
}
