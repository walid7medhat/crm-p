<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Listing;
use App\Models\User;
use App\Models\Owner;

class ReassignListings extends Command
{
    protected $signature = 'data:move-to-agent';
    protected $description = 'Move listings, owners to new agent and deactivate old users';

    public function handle()
    {
        $userIds = [232, 110, 112, 199, 205,134];
        $newAgentId = 26;

        $this->info("🚀 Starting...");

        $listings = Listing::whereIn('agent_id', $userIds)->update([
            'agent_id'    => $newAgentId,
            'assigned_at' => now(),
            'assigned_by' => 1,
        ]);

        // 2️⃣ نقل الـ owners (direct بدون clone)
        $owners = Owner::whereIn('added_by', $userIds)->update([
            'added_by' => $newAgentId
        ]);

        // 3️⃣ تعطيل المستخدمين
        $users = User::whereIn('id', $userIds)->update([
            'status' => 'in_active'
        ]);

        $this->info("✅ Done");
        $this->info("Listings Updated: " . $listings);
        $this->info("Owners Updated: " . $owners);
        $this->info("Users Updated: " . $users);

        return 0;
    }
}
