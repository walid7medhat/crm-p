<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

/**
 * Shared lead free-text search applicator.
 * Keeps result semantics stable while using faster paths for phone/email/lean queries.
 */
class LeadTextSearch
{
    /**
     * @param  Builder<\App\Models\Lead>  $query
     * @param  array{comments?: bool, relations?: bool, admin?: bool, lean?: bool}  $options
     * @return Builder<\App\Models\Lead>
     */
    public static function apply(Builder $query, string $search, array $options = []): Builder
    {
        $term = trim($search);
        if ($term === '') {
            return $query;
        }

        $includeComments = (bool) ($options['comments'] ?? false);
        $includeRelations = (bool) ($options['relations'] ?? true);
        $isAdmin = (bool) ($options['admin'] ?? true);
        $lean = (bool) ($options['lean'] ?? false);

        // Phone-like terms: scan contact/number columns only (much faster than full OR + whereHas).
        $digits = preg_replace('/\D+/', '', $term) ?? '';
        if (strlen($digits) >= 4 && preg_match('/^[\d\s\+\-\(\)]+$/', $term) === 1) {
            $variants = array_values(array_unique(array_filter([
                $digits,
                // UAE local <-> international variants
                (str_starts_with($digits, '971') && strlen($digits) > 3) ? ('0'.substr($digits, 3)) : null,
                (str_starts_with($digits, '0') && strlen($digits) > 1) ? ('971'.substr($digits, 1)) : null,
            ])));

            return $query->where(function (Builder $s) use ($variants) {
                foreach ($variants as $variant) {
                    $like = '%'.$variant.'%';
                    $s->orWhere('work_phone', 'like', $like)
                        ->orWhere('work_phone_2', 'like', $like)
                        ->orWhere('whatsapp_number', 'like', $like)
                        ->orWhere('lead_number', 'like', $like)
                        ->orWhere('source_client_phone', 'like', $like);
                }
            });
        }

        // Email-like terms: prefer email columns.
        if (str_contains($term, '@')) {
            return $query->where(function (Builder $s) use ($term) {
                $like = '%'.$term.'%';
                $s->where('email', 'like', $like)
                    ->orWhere('secondary_email', 'like', $like)
                    ->orWhere('source_client_email', 'like', $like);
            });
        }

        $like = '%'.$term.'%';

        if (! $isAdmin) {
            return $query->where(function (Builder $s) use ($like, $includeComments, $lean) {
                $s->where('lead_name', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('lead_source', 'like', $like);

                if (! $lean) {
                    $s->orWhereHas('propertyType', function ($pt) use ($like) {
                        $pt->where('name', 'like', $like);
                    });
                }

                if ($includeComments && ! $lean) {
                    $s->orWhereHas('comments', function ($cm) use ($like) {
                        $cm->where('comment', 'like', $like);
                    });
                }
            });
        }

        $websitePartials = ['website', 'Allproperties.ae', 'Oiaproperties.com'];
        $portalPartials = ['portal', 'propertyfinder', 'bayut'];
        $whatsappPartials = ['whatsapp'];

        if ($term === 'website') {
            $expanded = $websitePartials;
        } elseif ($term === 'portal') {
            $expanded = $portalPartials;
        } elseif ($term === 'whatsapp') {
            $expanded = $whatsappPartials;
        } else {
            $expanded = [$term];
        }

        // Lean mode intentionally skips relation/EXISTS scans (agent/property/stage/integration).
        // Use dedicated filters in the advanced search modal for those.

        return $query->where(function (Builder $s) use ($term, $like, $expanded, $includeComments, $includeRelations, $lean) {
            // Core identity / contact fields (highest hit-rate, cheapest).
            $s->where('lead_name', 'like', $like)
                ->orWhere('lead_number', 'like', $like)
                ->orWhere('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('work_phone', 'like', $like)
                ->orWhere('work_phone_2', 'like', $like)
                ->orWhere('lead_source', 'like', $like);

            if (! $lean) {
                $s->orWhere('status_lead', 'like', $like)
                    ->orWhere('interaction_result', 'like', $like)
                    ->orWhere('bedrooms', 'like', $like)
                    ->orWhere('more_information', 'like', $like)
                    ->orWhere('budget_from', 'like', $like)
                    ->orWhere('budget_to', 'like', $like)
                    ->orWhere('source_information', 'like', $like)
                    ->orWhere('purpose_buying', 'like', $like)
                    ->orWhere('budget', 'like', $like)
                    ->orWhere(function ($q2) use ($like) {
                        $q2->where('lead_type', 'like', $like)
                            ->orWhere('lead_type', 'both');
                    })
                    ->orWhere(function ($q2) use ($like) {
                        $q2->where('property_status', 'like', $like)
                            ->orWhere('property_status', 'both');
                    });
            }

            if ($includeRelations && ! $lean) {
                $s->orWhereHas('responsiblePerson', function ($r) use ($like) {
                    $r->where('name', 'like', $like);
                })
                    ->orWhereHas('propertyType', function ($pt) use ($like) {
                        $pt->where('name', 'like', $like);
                    })
                    ->orWhereHas('stage', function ($st) use ($like) {
                        $st->where('name', 'like', $like);
                    })
                    ->orWhereHas('integration', function ($st) use ($like) {
                        $st->where('track_keyword', 'like', $like);
                    });
            }

            if ($includeComments && ! $lean) {
                $s->orWhereHas('comments', function ($cm) use ($like) {
                    $cm->where('comment', 'like', $like);
                });
            }

            if (count($expanded) > 1) {
                $s->orWhere(function ($exp) use ($expanded, $lean) {
                    foreach ($expanded as $partial) {
                        $partialLike = '%'.$partial.'%';
                        $exp->orWhere('lead_source', 'like', $partialLike);
                        if (! $lean) {
                            $exp->orWhere('more_information', 'like', $partialLike);
                        }
                    }
                });
            }
        });
    }
}
