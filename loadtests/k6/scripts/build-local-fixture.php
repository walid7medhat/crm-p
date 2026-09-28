<?php

/**
 * Rebuild loadtests/k6/data/tokens.json from existing local users.
 * Mints JWTs with JWTAuth::fromUser() (does not call POST /api/auth/login).
 * Each user only receives lead IDs that User::canViewLead() allows.
 *
 * Run from the project root:
 *   php loadtests/k6/scripts/build-local-fixture.php
 */

use App\Models\Lead;
use App\Models\Listing;
use App\Models\Stage;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

require __DIR__ . '/../../../vendor/autoload.php';
$app = require __DIR__ . '/../../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$ownerIds = Lead::query()
    ->whereNotNull('responsible_person_id')
    ->where('stage_id', '!=', 10)
    ->distinct()
    ->pluck('responsible_person_id');

$parentIds = User::query()->whereIn('id', $ownerIds)->whereNotNull('parent_id')->pluck('parent_id');

$candidateIds = User::query()
    ->where('status', 'active')
    ->where(function ($q) use ($ownerIds, $parentIds) {
        $q->whereIn('id', $ownerIds->merge($parentIds)->push(1)->unique())
            ->orWhereHas('roles', fn ($r) => $r->whereIn('name', ['super_admin', 'admin']));
    })
    ->orderBy('id')
    ->limit(12)
    ->pluck('id');
$users = [];

foreach ($candidateIds as $id) {
    $user = User::query()->where('status', 'active')->find($id);
    if (! $user) {
        continue;
    }

    $query = Lead::query()->orderByDesc('id');
    if (! $user->hasRole('super_admin') && (int) $user->id !== 30 && (int) $user->id !== 33) {
        $subordinates = $user->getAllSubordinatesIds();
        $query->whereIn('responsible_person_id', $subordinates)
            ->where('stage_id', '!=', 10);
    }

    $allowed = [];
    foreach ($query->limit(40)->get() as $lead) {
        if ($user->canViewLead($lead)) {
            $allowed[] = (int) $lead->id;
        }
        if (count($allowed) >= 8) {
            break;
        }
    }

    if ($allowed === []) {
        fwrite(STDERR, "skip user {$id}: no authorized leads\n");
        continue;
    }

    $users[] = [
        'user_id' => (int) $user->id,
        'token' => JWTAuth::fromUser($user),
        'lead_ids' => $allowed,
    ];
}

if ($users === []) {
    fwrite(STDERR, "no users with authorized leads\n");
    exit(1);
}

$payload = [
    'users' => $users,
    'listing_ids' => Listing::query()->orderByDesc('id')->limit(8)->pluck('id')->map(fn ($id) => (int) $id)->all(),
    'lead_stage_id' => (int) Stage::query()->where('stage_type', 'lead')->where('name', '!=', 'lead pool')->value('id'),
    'deal_type' => 'primary',
    'search_term' => 'al',
];

$path = __DIR__ . '/../data/tokens.json';
file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

$leadCounts = array_map(fn ($row) => $row['user_id'] . ':' . count($row['lead_ids']), $users);
echo 'users=' . count($users) . ' leads=' . implode(',', $leadCounts) . ' listings=' . count($payload['listing_ids']) . "\n";
