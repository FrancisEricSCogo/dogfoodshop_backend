<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    public function getProfile(Request $request)
    {
        $user = $request->get('auth_user');
        
        $userData = DB::table('users')->where('id', $user['id'])->first();
        unset($userData->password);
        
        return response()->json(['success' => true, 'user' => $userData]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->get('auth_user');
        $data = $request->json()->all();
        
        $updateData = [];
        if (isset($data['first_name'])) $updateData['first_name'] = $data['first_name'];
        if (isset($data['last_name'])) $updateData['last_name'] = $data['last_name'];
        if (isset($data['email'])) $updateData['email'] = $data['email'];
        if (isset($data['phone'])) $updateData['phone'] = $data['phone'];
        if (isset($data['address'])) $updateData['address'] = $data['address'];
        if (isset($data['postal_code'])) $updateData['postal_code'] = $data['postal_code'];
        if (isset($data['city'])) $updateData['city'] = $data['city'];
        
        try {
            DB::table('users')->where('id', $user['id'])->update($updateData);
            
            $updatedUser = DB::table('users')->where('id', $user['id'])->first();
            unset($updatedUser->password);
            
            return response()->json([
                'success' => true,
                'message' => 'Profile updated',
                'user' => $updatedUser
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Update failed: ' . $e->getMessage()], 500);
        }
    }

    public function uploadAvatar(Request $request)
    {
        $user = $request->get('auth_user');

        if (!$request->hasFile('avatar')) {
            return response()->json(['error' => 'No file uploaded or upload error'], 400);
        }

        $file = $request->file('avatar');
        
        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file->getMimeType(), $allowedTypes)) {
            return response()->json(['error' => 'Invalid file type. Only JPG, PNG, and GIF are allowed.'], 400);
        }

        // Validate file size (5MB max)
        if ($file->getSize() > 5 * 1024 * 1024) {
            return response()->json(['error' => 'File size exceeds 5MB limit.'], 400);
        }

        try {
            // Get current user to check for old avatar
            $currentUser = DB::table('users')->where('id', $user['id'])->first();
            
            // Delete old avatar if exists
            if (!empty($currentUser->profile_pic)) {
                $oldPath = 'uploads/' . $currentUser->profile_pic;
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }

            // Generate unique filename
            $extension = $file->getClientOriginalExtension();
            $filename = 'avatar_' . $user['id'] . '_' . time() . '.' . $extension;
            
            // Store in public/uploads
            $path = $file->storeAs('', $filename, 'public');
            
            // Update database
            DB::table('users')->where('id', $user['id'])->update(['profile_pic' => $filename]);
            
            $updatedUser = DB::table('users')->where('id', $user['id'])->first();
            unset($updatedUser->password);
            
            return response()->json([
                'success' => true,
                'message' => 'Avatar uploaded successfully',
                'user' => $updatedUser,
                'filename' => $filename
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to save file: ' . $e->getMessage()], 500);
        }
    }
}
