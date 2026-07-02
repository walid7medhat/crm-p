<?php

namespace App\Services\AiSalesIntelligence;

use App\Models\User;

class AiAgentUserResolver
{
    /**
     * @return list<int>
     */
    public function scoredUserIds(?int $branchId = null): array
    {
        return User::query()
            ->where('status', 'active')
            ->when($branchId && \Illuminate\Support\Facades\Schema::hasColumn('users', 'company_branch_id'), fn ($q) => $q->where('company_branch_id', $branchId))
            ->where(function ($q) {
                $q->whereHas('roles', fn ($r) => $r->whereIn('name', ['sales', 'team_lead']))
                    ->orWhereHas('roles', fn ($r) => $r->where('name', 'manager'));
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
