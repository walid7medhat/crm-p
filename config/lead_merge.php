<?php
return [
    'lead_pool_stage_id'   => 10,
    'unqualified_stage_id' => 11,
    'lost_stage_id'        => 2,
    /** null = resolve as first lead stage by order / "New" / "New Lead" */
    'new_lead_stage_id'    => env('LEAD_MERGE_NEW_LEAD_STAGE_ID', 3),
    'merge_roles'          => ['super_admin', 'admin', 'branch_admin'],
    'group_by_branch'      => env('LEAD_MERGE_GROUP_BY_BRANCH', true),
    /**
     * Automatic merge (Rules A/B + daily command) only for these branch keys.
     * Matched against lead_branch_source / branchKey() (case-insensitive, substring).
     * Dubai and any other branch are excluded. Manual merge is unaffected.
     */
    'auto_merge_branch_keys' => ['abu dhabi'],
    // config/lead_merge.php
    'qualified_stage_id' => env('LEAD_MERGE_QUALIFIED_STAGE_ID',6),
    'contacted_stage_id' => env('LEAD_MERGE_CONTACTED_STAGE_ID',5),
    'assigned_stage_id'  => env('LEAD_MERGE_ASSIGNED_STAGE_ID',4),
];
