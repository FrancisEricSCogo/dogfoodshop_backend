<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AdminController extends Controller
{
    public function getUsers(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if ($user->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $role = $request->query('role');
        $query = User::select('id', 'first_name', 'last_name', 'username', 'email', 'phone', 'role', 'created_at');

        if ($role) {
            $query->where('role', $role);
        }

        $users = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'users' => $users
        ]);
    }

    public function createUser(Request $request)
    {
        try {
            $admin = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if ($admin->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'username' => 'required|string|max:50|unique:users',
            'email' => 'required|email|max:100|unique:users',
            'password' => 'required|string|min:6',
            'phone' => 'nullable|string|max:20',
            'role' => 'required|in:customer,supplier,admin',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'username' => $request->username,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'user' => $user->makeHidden(['password', 'token'])
        ], 201);
    }

    public function updateUser(Request $request)
    {
        try {
            $admin = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if ($admin->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:users,id',
            'first_name' => 'sometimes|required|string|max:100',
            'last_name' => 'sometimes|required|string|max:100',
            'username' => 'sometimes|required|string|max:50|unique:users,username,' . $request->id,
            'email' => 'sometimes|required|email|max:100|unique:users,email,' . $request->id,
            'phone' => 'nullable|string|max:20',
            'role' => 'sometimes|required|in:customer,supplier,admin',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        $user = User::find($request->id);

        // Protect super admin
        if ($user->id == 1 || strtolower($user->username) === 'admin') {
            return response()->json(['error' => 'Super admin cannot be modified'], 403);
        }

        $user->update($request->only(['first_name', 'last_name', 'username', 'email', 'phone', 'role']));

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'user' => $user->makeHidden(['password', 'token'])
        ]);
    }

    public function deleteUser($id)
    {
        try {
            $admin = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if ($admin->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $user = User::find($id);

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Protect super admin
        if ($user->id == 1 || strtolower($user->username) === 'admin') {
            return response()->json(['error' => 'Super admin cannot be deleted'], 403);
        }

        // Prevent self-deletion
        if ($user->id == $admin->id) {
            return response()->json(['error' => 'You cannot delete your own account'], 403);
        }

        // Only super admin can delete other admins
        if ($user->role === 'admin' && ($admin->id != 1 && strtolower($admin->username) !== 'admin')) {
            return response()->json(['error' => 'Only super admin can delete other admin accounts'], 403);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }
}

