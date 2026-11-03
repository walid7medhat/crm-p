<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\AgentJoinedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Tells the people above a newly added agent that they joined:
 *  - every super_admin
 *  - every admin in the agent's parent chain (office admin, branch admin)
 *  - every branch_admin in the agent's branch (they work across the whole branch)
 * The agent and the person who added them are not notified.
 * Never throws: a failed notification must not block creating the user.
 */
class NewAgentJoinedNotifier
{
    public static function notify(User $agent, ?int $addedById = null): void
    {
        try {
            $agent->loadMissing(['roles', 'parent']);

            $recipientIds = User::role('super_admin')->pluck('id')->all();

            // Admins above the agent
            $visited = [];
            $current = $agent->parent;
            while ($current && ! isset($visited[$current->id])) {
                $visited[$current->id] = true;
                if ($current->hasRole('admin')) {
                    $recipientIds[] = $current->id;
                }
                $current = $current->parent;
            }

            // Branch admins of the agent's branch
            $branch = $agent->getBranchNode();
            if ($branch) {
                $branchUserIds = $branch->getAllSubordinatesIds();
                $recipientIds = array_merge(
                    $recipientIds,
                    User::role('branch_admin')->whereIn('id', $branchUserIds)->pluck('id')->all()
                );
            }

            $exclude = array_filter([$agent->id, $addedById]);
            $recipientIds = array_values(array_diff(array_unique($recipientIds), $exclude));
            if (! $recipientIds) {
                return;
            }

            Notification::send(
                User::whereIn('id', $recipientIds)->get(),
                new AgentJoinedNotification($agent)
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to send agent joined notification', [
                'agent_id' => $agent->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
