<?php

namespace App\Services\AiSalesIntelligence;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AiAgentUserResolver
{
    /**
     * All active sales users plus anyone who owns leads in the pipeline.
     *
     * @return list<int>
     */
    public function scoredUserIds(?int $branchId = null): array
    {
        $roleQuery = User::query()
            ->where('status', 'active')
            ->when($branchId && \Illuminate\Support\Facades\Schema::hasColumn('users', 'company_branch_id'), fn ($q) => $q->where('company_branch_id', $branchId))
            ->where(function ($q) {
                $q->whereHas('roles', fn ($r) => $r->whereIn('name', ['sales', 'team_lead', 'agent']))
                    ->orWhereHas('roles', fn ($r) => $r->whereIn('name', ['manager', 'admin']));
            });

        $leadOwnerQuery = collect();
        if (Schema::hasTable('leads') && Schema::hasColumn('leads', 'responsible_person_id')) {
            $leadOwnerQuery = DB::table('leads')
                ->whereNotNull('responsible_person_id')
                ->when($branchId && Schema::hasColumn('users', 'company_branch_id'), function ($q) use ($branchId) {
                    $q->whereIn('responsible_person_id', function ($sub) use ($branchId) {
                        $sub->select('id')
                            ->from('users')
                            ->where('company_branch_id', $branchId);
                    });
                })
                ->distinct()
                ->pluck('responsible_person_id');
        }

        return $roleQuery
            ->pluck('id')
            ->merge($leadOwnerQuery)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
