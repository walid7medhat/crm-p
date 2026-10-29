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
    /**
     * Emails never used to match duplicates (shared placeholders such as "no@email.com").
     * Without this, unrelated leads with the same placeholder would be duplicates and auto-merged.
     */
    'ignored_duplicate_emails' => array_filter(array_map('trim', explode(',', (string) env('LEAD_MERGE_IGNORED_EMAILS', '')))),
    /**
     * Auto-merge safety: a phone/email shared by more leads than this is treated as a placeholder
     * (e.g. "0000000") and skipped, instead of loading and merging thousands of unrelated leads.
     */
    'max_contact_matches' => (int) env('LEAD_MERGE_MAX_CONTACT_MATCHES', 50),
    /** Phones with fewer digits than this (e.g. "0", "971") are never used for auto-merge. */
    'min_phone_digits' => (int) env('LEAD_MERGE_MIN_PHONE_DIGITS', 7),
    'qualified_stage_id' => env('LEAD_MERGE_QUALIFIED_STAGE_ID',6),
    'contacted_stage_id' => env('LEAD_MERGE_CONTACTED_STAGE_ID',5),
    'assigned_stage_id'  => env('LEAD_MERGE_ASSIGNED_STAGE_ID',4),
];
