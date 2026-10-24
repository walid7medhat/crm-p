<?php
return [
    'lead_pool_stage_id'   => 10,
    'unqualified_stage_id' => 11,
    'lost_stage_id'        => 2,
    'merge_roles'          => ['super_admin', 'admin', 'branch_admin'],
    'group_by_branch' => env('LEAD_MERGE_GROUP_BY_BRANCH', true),
];