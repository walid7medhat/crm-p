<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\User\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    /**
     * Get authenticated user profile
     */
    public function show(Request $request)
    {
        try {
            $user = $request->user();
            $user->load(['roles', 'permissions', 'parent', 'addedBy', 'children']);
            
            return response()->json([
                'success' => true,
                'data' => new UserResource($user),
                'message' => 'Profile retrieved successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user profile
     */
    public function update(Request $request)
    {
        try {
            $user = $request->user();
            
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
                'phone' => 'nullable|string|max:20',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update only the display name — the real `name` is left unchanged.
            $user->update([
                'display_name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
            ]);

            $user->load(['roles', 'permissions', 'parent', 'addedBy', 'children']);

            return response()->json([
                'success' => true,
                'data' => new UserResource($user),
                'message' => 'Profile updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user avatar
     */
    public function updateAvatar(Request $request)
    {
        try {
            $user = $request->user();
            
            $validator = Validator::make($request->all(), [
                'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Delete old avatar if exists
            if ($user->avatar && Storage::disk('public')->exists($user->avatar) &&  $user->avatar !== 'users/user.png' ) {
                Storage::disk('public')->delete($user->avatar);
            }

            // Store new avatar
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
            
            // Update user avatar
            $user->update([
                'avatar' => $avatarPath
            ]);

            $user->load(['roles', 'permissions', 'parent', 'addedBy', 'children']);

            return response()->json([
                'success' => true,
                'data' => new UserResource($user),
                'message' => 'Avatar updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update avatar',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the background the user has chosen. Pass background_id = null to
     * reset back to the system default. Only active backgrounds can be selected.
     */
    public function updateBackground(Request $request)
    {
        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'background_id' => 'nullable|integer|exists:backgrounds,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $backgroundId = $request->input('background_id');

            // Don't let users pin a background that the superadmin has deactivated.
            if ($backgroundId !== null) {
                $isActive = \App\Models\Background::where('id', $backgroundId)
                    ->where('is_active', true)
                    ->exists();

                if (! $isActive) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This background is not available',
                    ], 422);
                }
            }

            $user->update(['background_id' => $backgroundId]);

            $user->load(['roles', 'permissions', 'parent', 'addedBy', 'children']);

            return response()->json([
                'success' => true,
                'data' => new UserResource($user),
                'message' => 'Background updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update background',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Change user password
     */
    public function changePassword(Request $request)
    {
        try {
            $user = $request->user();
            
            $validator = Validator::make($request->all(), [
                'current_password' => ['required', 'current_password'],
                'new_password' => ['required', 'confirmed', Rules\Password::defaults()],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update password
            $user->update([
                'password' => Hash::make($request->new_password)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to change password',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}