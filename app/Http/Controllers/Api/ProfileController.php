<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\User\UserResource;
use App\Models\EmployeeDocument;
use App\Models\EmployeeProfile;
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
            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated',
                ], 401);
            }

            $user->load([
                'roles',
                'permissions',
                'parent.roles',
                'addedBy',
                'children',
                'employeeProfile.department',
                'employeeProfile.designation',
                'employeeProfile.companyBranch',
            ]);

            return response()->json([
                'success' => true,
                'data' => (new UserResource($user))->resolve(),
                'message' => 'Profile retrieved successfully',
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

            $user->load([
                'roles',
                'permissions',
                'parent.roles',
                'addedBy',
                'children',
                'employeeProfile.department',
                'employeeProfile.designation',
                'employeeProfile.companyBranch',
            ]);

            return response()->json([
                'success' => true,
                'data' => (new UserResource($user))->resolve(),
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

            $user->load([
                'roles',
                'permissions',
                'parent.roles',
                'addedBy',
                'children',
                'employeeProfile.department',
                'employeeProfile.designation',
                'employeeProfile.companyBranch',
            ]);

            return response()->json([
                'success' => true,
                'data' => (new UserResource($user))->resolve(),
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

            $user->load([
                'roles',
                'permissions',
                'parent.roles',
                'addedBy',
                'children',
                'employeeProfile.department',
                'employeeProfile.designation',
                'employeeProfile.companyBranch',
            ]);

            return response()->json([
                'success' => true,
                'data' => (new UserResource($user))->resolve(),
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

    /**
     * Get the authenticated user's UAE emergency contact.
     */
    public function getEmergencyContact(Request $request)
    {
        $employeeProfile = $request->user()->employeeProfile;

        return response()->json([
            'success' => true,
            'data' => [
                'name' => $employeeProfile?->emergency_contact_name,
                'phone' => $employeeProfile?->emergency_phone,
                'relation' => $employeeProfile?->emergency_contact_relation,
            ],
        ]);
    }

    /**
     * Get the authenticated user's employee profile, creating a bare one if
     * they don't have one yet (e.g. a user who was never onboarded through
     * the HR "add employee" flow but still needs to save self-service data).
     */
    private function getOrCreateEmployeeProfile($user): EmployeeProfile
    {
        if ($user->employeeProfile) {
            return $user->employeeProfile;
        }

        $profile = EmployeeProfile::create([
            'user_id' => $user->id,
            'employee_name' => $user->name,
            'employee_code' => EmployeeProfile::generateEmployeeCode(),
        ]);

        $user->setRelation('employeeProfile', $profile);

        return $profile;
    }

    /**
     * Update the authenticated user's UAE emergency contact.
     */
    public function updateEmergencyContact(Request $request)
    {
        try {
            $user = $request->user();
            $employeeProfile = $this->getOrCreateEmployeeProfile($user);

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'phone' => 'required|string|max:20',
                'relation' => 'required|string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $employeeProfile->update([
                'emergency_contact_name' => $request->name,
                'emergency_phone' => $request->phone,
                'emergency_contact_relation' => $request->relation,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'name' => $employeeProfile->emergency_contact_name,
                    'phone' => $employeeProfile->emergency_phone,
                    'relation' => $employeeProfile->emergency_contact_relation,
                ],
                'message' => 'Emergency contact updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update emergency contact',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List documents the authenticated user has uploaded to their own profile.
     */
    public function myDocuments(Request $request)
    {
        try {
            $user = $request->user();
            $employeeProfile = $user->employeeProfile;

            if (!$employeeProfile) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                ]);
            }

            $documents = $employeeProfile->documents()
                ->where('document_type', 'employee_upload')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $documents,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load documents',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload a titled document to the authenticated user's own profile.
     * Visible to HR and to the uploading employee; only HR can delete it
     * (via the existing employees.documents.destroy endpoint).
     */
    public function uploadDocument(Request $request)
    {
        try {
            $user = $request->user();
            $employeeProfile = $this->getOrCreateEmployeeProfile($user);

            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'file' => 'required|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:10240',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $file = $request->file('file');
            $path = $file->store("employees/{$user->id}/employee_upload", 'public');

            $document = EmployeeDocument::create([
                'employee_profile_id' => $employeeProfile->id,
                'document_type' => 'employee_upload',
                'document_name' => $request->title,
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'file_size' => round($file->getSize() / 1024),
                'mime_type' => $file->getClientMimeType(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $document,
                'message' => 'Document uploaded successfully',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload document',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get the authenticated user's health disclosure checklist answers.
     */
    public function getHealthDisclosure(Request $request)
    {
        $employeeProfile = $request->user()->employeeProfile;

        return response()->json([
            'success' => true,
            'data' => $employeeProfile?->health_disclosure ?? [],
        ]);
    }

    /**
     * Save the authenticated user's health disclosure checklist answers.
     */
    public function updateHealthDisclosure(Request $request)
    {
        try {
            $user = $request->user();
            $employeeProfile = $this->getOrCreateEmployeeProfile($user);

            $validator = Validator::make($request->all(), [
                'items' => 'required|array',
                'items.*.key' => 'required|string|max:100',
                'items.*.checked' => 'required|boolean',
                'items.*.note' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $items = array_map(fn ($item) => [
                'key' => $item['key'],
                'checked' => (bool) $item['checked'],
                'note' => $item['checked'] ? ($item['note'] ?? null) : null,
            ], $validator->validated()['items']);

            $employeeProfile->update(['health_disclosure' => $items]);

            return response()->json([
                'success' => true,
                'data' => $employeeProfile->health_disclosure,
                'message' => 'Health disclosure saved successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save health disclosure',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}