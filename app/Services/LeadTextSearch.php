<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

/**
 * Shared lead free-text search applicator.
 * Keeps result semantics stable while using faster paths for phone/email queries.
 */
class LeadTextSearch
{
    /**
     * @param  Builder<\App\Models\Lead>  $query
     * @param  array{comments?: bool, relations?: bool, admin?: bool}  $options
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

        // Phone-like terms: scan contact/number columns only (much faster than full OR + whereHas).
        $digits = preg_replace('/\D+/', '', $term) ?? '';
        if (strlen($digits) >= 4 && preg_match('/^[\d\s\+\-\(\)]+$/', $term) === 1) {
            return $query->where(function (Builder $s) use ($term, $digits) {
                $s->where('work_phone', 'like', "%{$term}%")
                    ->orWhere('work_phone', 'like', "%{$digits}%")
                    ->orWhere('work_phone_2', 'like', "%{$term}%")
                    ->orWhere('work_phone_2', 'like', "%{$digits}%")
                    ->orWhere('whatsapp_number', 'like', "%{$term}%")
                    ->orWhere('whatsapp_number', 'like', "%{$digits}%")
                    ->orWhere('lead_number', 'like', "%{$term}%")
                    ->orWhere('source_client_phone', 'like', "%{$term}%")
                    ->orWhere('source_client_phone', 'like', "%{$digits}%");
            });
        }

        // Email-like terms: prefer email columns.
        if (str_contains($term, '@')) {
            return $query->where(function (Builder $s) use ($term) {
                $s->where('email', 'like', "%{$term}%")
                    ->orWhere('secondary_email', 'like', "%{$term}%")
                    ->orWhere('source_client_email', 'like', "%{$term}%");
            });
        }

        if (! $isAdmin) {
            return $query->where(function (Builder $s) use ($term, $includeComments) {
                $s->where('lead_name', 'like', "%{$term}%")
                    ->orWhere('lead_source', 'like', "%{$term}%");

                if ($includeComments) {
                    $s->orWhereHas('comments', function ($cm) use ($term) {
                        $cm->where('comment', 'like', "%{$term}%");
                    });
                }

                $s->orWhereHas('propertyType', function ($pt) use ($term) {
                    $pt->where('name', 'like', "%{$term}%");
                });
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

        return $query->where(function (Builder $s) use ($term, $expanded, $includeComments, $includeRelations) {
            $s->where('lead_name', 'like', "%{$term}%")
                ->orWhere('lead_number', 'like', "%{$term}%")
                ->orWhere('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('work_phone', 'like', "%{$term}%")
                ->orWhere('bedrooms', 'like', "%{$term}%")
                ->orWhere('work_phone_2', 'like', "%{$term}%")
                ->orWhere('lead_source', 'like', "%{$term}%")
                ->orWhere('status_lead', 'like', "%{$term}%")
                ->orWhere('interaction_result', 'like', "%{$term}%")
                ->orWhere('more_information', 'like', "%{$term}%")
                ->orWhere(function ($q2) use ($term) {
                    $q2->where('lead_type', 'like', "%{$term}%")
                        ->orWhere('lead_type', 'both');
                })
                ->orWhere(function ($q2) use ($term) {
                    $q2->where('property_status', 'like', "%{$term}%")
                        ->orWhere('property_status', 'both');
                })
                ->orWhere('budget_from', 'like', "%{$term}%")
                ->orWhere('budget_to', 'like', "%{$term}%")
                ->orWhere('source_information', 'like', "%{$term}%")
                ->orWhere('purpose_buying', 'like', "%{$term}%")
                ->orWhere('budget', 'like', "%{$term}%");

            if ($includeRelations) {
                $s->orWhereHas('responsiblePerson', function ($r) use ($term) {
                    $r->where('name', 'like', "%{$term}%");
                })
                    ->orWhereHas('propertyType', function ($pt) use ($term) {
                        $pt->where('name', 'like', "%{$term}%");
                    })
                    ->orWhereHas('stage', function ($st) use ($term) {
                        $st->where('name', 'like', "%{$term}%");
                    })
                    ->orWhereHas('integration', function ($st) use ($term) {
                        $st->where('track_keyword', 'like', "%{$term}%");
                    });
            }

            if ($includeComments) {
                $s->orWhereHas('comments', function ($cm) use ($term) {
                    $cm->where('comment', 'like', "%{$term}%");
                });
            }

            // Avoid duplicating the same term already covered by lead_source / more_information.
            if (count($expanded) > 1) {
                $s->orWhere(function ($exp) use ($expanded) {
                    foreach ($expanded as $partial) {
                        $exp->orWhere('lead_source', 'like', "%{$partial}%")
                            ->orWhere('more_information', 'like', "%{$partial}%");
                    }
                });
            }
        });
    }
}
