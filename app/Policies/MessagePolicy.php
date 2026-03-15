<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

class MessagePolicy
{
    public function view(User $user, Message $message): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }
        return $message->conversation->users()->where('user_id', $user->id)->exists();
    }

    public function create(User $user, Message $message = null): bool
    {
        return true;
    }
}
