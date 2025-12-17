<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    public function getUsers(Request $request)
    {
        $user = $request->get('auth_user');
        
        if ($user['role'] !== 'admin') {
            return response()->json(['error' => 'Admin access required'], 403);
        }

        try {
            $role = $request->query('role');
            
            $query = DB::table('users')
                ->select('id', 'first_name', 'last_name', 'username', 'email', 'phone', 'role', 'created_at')
                ->orderBy('created_at', 'desc');
            
            if ($role) {
                $query->where('role', $role);
            }
            
            $users = $query->get();
            
            return response()->json(['success' => true, 'users' => $users]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch users: ' . $e->getMessage()], 500);
        }
    }

    public function createUser(Request $request)
    {
        $admin = $request->get('auth_user');
        
        if ($admin['role'] !== 'admin') {
            return response()->json(['error' => 'Admin access required'], 403);
        }

        $data = $request->json()->all();
        $first_name = $data['first_name'] ?? '';
        $last_name = $data['last_name'] ?? '';
        $username = $data['username'] ?? '';
        $email = $data['email'] ?? '';
        $phone = $data['phone'] ?? '';
        $password = $data['password'] ?? '';
        $role = $data['role'] ?? 'customer';

        if (empty($first_name) || empty($last_name) || empty($username) || empty($email) || empty($password)) {
            return response()->json(['error' => 'All required fields must be filled'], 400);
        }

        if (!in_array($role, ['customer', 'supplier', 'admin'])) {
            return response()->json(['error' => 'Invalid role'], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['error' => 'Invalid email format'], 400);
        }

        try {
            $existing = DB::table('users')
                ->where('username', $username)
                ->orWhere('email', $email)
                ->first();

            if ($existing) {
                return response()->json(['error' => 'Username or email already exists'], 409);
            }

            $userId = DB::table('users')->insertGetId([
                'first_name' => $first_name,
                'last_name' => $last_name,
                'username' => $username,
                'email' => $email,
                'phone' => $phone,
                'password' => Hash::make($password),
                'role' => $role,
                'created_at' => now()
            ]);

            $newUser = DB::table('users')
                ->select('id', 'first_name', 'last_name', 'username', 'email', 'phone', 'role', 'created_at')
                ->where('id', $userId)
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'user' => $newUser
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create user: ' . $e->getMessage()], 500);
        }
    }

    public function updateUser(Request $request)
    {
        $admin = $request->get('auth_user');
        
        if ($admin['role'] !== 'admin') {
            return response()->json(['error' => 'Admin access required'], 403);
        }

        $data = $request->json()->all();
        $userId = $data['user_id'] ?? 0;

        if (empty($userId)) {
            return response()->json(['error' => 'User ID is required'], 400);
        }

        $isSuperAdmin = ($userId == 1);

        try {
            // Check if username or email already exists
            if (isset($data['username']) || isset($data['email'])) {
                $checkUsername = $data['username'] ?? '';
                $checkEmail = $data['email'] ?? '';
                $existing = DB::table('users')
                    ->where(function($query) use ($checkUsername, $checkEmail) {
                        if ($checkUsername) {
                            $query->where('username', $checkUsername);
                        }
                        if ($checkEmail) {
                            $query->orWhere('email', $checkEmail);
                        }
                    })
                    ->where('id', '!=', $userId)
                    ->first();

                if ($existing) {
                    return response()->json(['error' => 'Username or email already exists'], 409);
                }
            }

            $updateData = [];
            if (isset($data['first_name'])) $updateData['first_name'] = $data['first_name'];
            if (isset($data['last_name'])) $updateData['last_name'] = $data['last_name'];
            if (isset($data['username'])) $updateData['username'] = $data['username'];
            if (isset($data['email'])) $updateData['email'] = $data['email'];
            if (isset($data['phone'])) $updateData['phone'] = $data['phone'];
            if (isset($data['role']) && !$isSuperAdmin) $updateData['role'] = $data['role'];
            if (isset($data['password']) && !empty($data['password'])) {
                $updateData['password'] = Hash::make($data['password']);
            }

            if (empty($updateData)) {
                return response()->json(['error' => 'No fields to update'], 400);
            }

            // Check if role is being changed to supplier
            $oldUser = DB::table('users')->where('id', $userId)->first();
            $roleChangedToSupplier = false;
            
            if (isset($data['role']) && $data['role'] === 'supplier' && $oldUser->role !== 'supplier') {
                $roleChangedToSupplier = true;
            }

            DB::table('users')->where('id', $userId)->update($updateData);

            // If role changed to supplier, transfer ALL products to this new supplier
            // This ensures they see all supplier data immediately (all products and all orders)
            if ($roleChangedToSupplier) {
                // Transfer all products to this new supplier so they can see all data
                DB::table('products')->update(['supplier_id' => $userId]);
            }

            $updatedUser = DB::table('users')
                ->select('id', 'first_name', 'last_name', 'username', 'email', 'phone', 'role', 'created_at')
                ->where('id', $userId)
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'User updated',
                'user' => $updatedUser
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update user: ' . $e->getMessage()], 500);
        }
    }

    public function deleteUser(Request $request)
    {
        $admin = $request->get('auth_user');
        
        if ($admin['role'] !== 'admin') {
            return response()->json(['error' => 'Admin access required'], 403);
        }

        $userId = $request->json('user_id') ?? $request->query('user_id') ?? 0;

        if (empty($userId)) {
            return response()->json(['error' => 'User ID is required'], 400);
        }

        try {
            $user = DB::table('users')
                ->select('id', 'username', 'email', 'role')
                ->where('id', $userId)
                ->first();

            if (!$user) {
                return response()->json(['error' => 'User not found'], 404);
            }

            // Protect Super Admin
            if ($userId == 1 || strtolower($user->username) === 'admin') {
                return response()->json(['error' => 'Super admin cannot be deleted. This account is protected and cannot be deleted by anyone.'], 403);
            }

            // Prevent self-deletion
            if ($userId == $admin['id']) {
                return response()->json(['error' => 'You cannot delete your own account. Please logout and have another admin delete it.'], 403);
            }

            // Only super admin can delete regular admins
            if ($user->role === 'admin') {
                $isSuperAdmin = ($admin['id'] == 1 || strtolower($admin['username']) === 'admin');
                if (!$isSuperAdmin) {
                    return response()->json(['error' => 'Only super admin can delete other admin accounts. Regular admins cannot delete other admins.'], 403);
                }
            }

            DB::table('users')->where('id', $userId)->delete();

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully',
                'deleted_user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete user: ' . $e->getMessage()], 500);
        }
    }
}
