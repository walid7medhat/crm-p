<?php

namespace App\Http\Controllers\Api;

use App\Jobs\SendInvitationEmailJob;
use App\Models\UserInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;

class UserInvitationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'emails' => 'required|array',
            'emails.*' => 'required|email|unique:users,email|unique:user_invitations,email',
        ]);

        $invitations = [];

        DB::transaction(function () use ($request, &$invitations) {
            foreach ($request->emails as $email) {
                $invitation = UserInvitation::create([
                    'email' => $email,
                    'token' => Str::random(60),
                    'expires_at' => now()->addDays(7),
                    'invited_by' => auth()->id(),
                ]);

                // Dispatch job to send email
                SendInvitationEmailJob::dispatch($invitation);

                $invitations[] = $invitation;
            }
        });

        return response()->json([
            'message' => 'Invitations sent successfully',
            'invitations' => $invitations,
        ]);
    }

    public function show($token)
    {
        $invitation = UserInvitation::where('token', $token)->firstOrFail();

        if (!$invitation->isValid()) {
            abort(410, 'This invitation has expired or has already been used');
        }

        return response()->json([
            'invitation' => $invitation,
        ]);
    }

    public function resend($id): JsonResponse
    {
        $invitation = UserInvitation::findOrFail($id);

        if ($invitation->used) {
            return response()->json([
                'message' => 'Cannot resend a used invitation'
            ], 422);
        }

        $invitation->update([
            'expires_at' => now()->addDays(7),
        ]);

        SendInvitationEmailJob::dispatch($invitation);

        return response()->json([
            'message' => 'Invitation resent successfully'
        ]);
    }
 public function validateInvitation($token)
{
    try {
        $invitation = UserInvitation::where('token', $token)->firstOrFail();

        return response()->json([
            'invitation' => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'used' => (bool)$invitation->used,
                'expires_at' => $invitation->expires_at,
                'inviter' => $invitation->inviter ? [
                    'name' => $invitation->inviter->name,
                    'email' => $invitation->inviter->email
                ] : null,
                'is_valid' => $invitation->isValid(),
                'is_expired' => $invitation->isExpired(),
            ],
            'status' => true
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Invitation not found',
            'status' => false
        ], 404);
    }
}

public function registerWithInvitation(Request $request)
{
    $request->validate([
        'name' => 'required|string|max:255',
        'password' => 'required|string|min:8|confirmed',
        'token' => 'required|string|exists:user_invitations,token',
    ]);

    DB::beginTransaction();

    try {
        // Find and validate invitation
        $invitation = UserInvitation::where('token', $request->token)->first();
        
        if (!$invitation->isValid()) {
            return response()->json([
                'message' => 'This invitation is no longer valid'
            ], 422);
        }

        // Create user
        $user = \App\Models\User::create([
            'name' => $request->name,
            'email' => $invitation->email,
            'password' => Hash::make($request->password),
            'email_verified_at' => now(), // Auto-verify since they were invited
        ]);

        // Mark invitation as used
        $invitation->update(['used' => true]);

        // Assign default role if needed
        // $user->assignRole('user');

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Account created successfully!',
            'user' => $user
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Registration failed: ' . $e->getMessage()
        ], 500);
    }
}
}