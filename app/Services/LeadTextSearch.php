<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shared lead free-text search applicator.
 * Keeps result semantics stable while using faster paths for phone/email/lean queries.
 *
 * Identity contains-search prefers MySQL ngram FULLTEXT when
 * config('lead_search.use_fulltext') is true and leads_identity_ngram_ft exists.
 * Phone-shaped terms keep the work_phone_digits path (+ legacy secondary columns).
 */
class LeadTextSearch
{
    /** Minimum length for general text search terms (Phase 1). */
    public const MIN_TEXT_LENGTH = 2;

    /** Minimum length for email-like terms containing "@". */
    public const MIN_EMAIL_LENGTH = 3;

    private static ?bool $fulltextReady = null;

    /**
     * Whether a free-text term should trigger LeadTextSearch filtering.
     * Phone path keeps the existing ≥4 digit gate; text requires MIN_TEXT_LENGTH.
     */
    public static function isActionable(string $search): bool
    {
        $term = trim($search);
        if ($term === '') {
            return false;
        }

        $digits = preg_replace('/\D+/', '', $term) ?? '';
        if (strlen($digits) >= 4 && preg_match('/^[\d\s\+\-\(\)]+$/', $term) === 1) {
            return true;
        }

        if (str_contains($term, '@')) {
            return mb_strlen($term) >= self::MIN_EMAIL_LENGTH;
        }

        return mb_strlen($term) >= self::MIN_TEXT_LENGTH;
    }

    /**
     * Reset cached FULLTEXT detection (tests / after migrations in-process).
     */
    public static function clearFulltextCache(): void
    {
        self::$fulltextReady = null;
    }

    public static function fulltextEnabled(): bool
    {
        if (! config('lead_search.use_fulltext', true)) {
            return false;
        }

        // InnoDB FULLTEXT indexes are not updated for rows inserted inside an open
        // transaction until commit. Feature tests using DatabaseTransactions would
        // otherwise false-fail; production HTTP search runs outside a writer txn.
        if (app()->runningUnitTests() && DB::transactionLevel() > 0) {
            return false;
        }

        if (self::$fulltextReady !== null) {
            return self::$fulltextReady;
        }

        $required = config('lead_search.fulltext_indexes', [
            'leads_lead_name_ngram_ft',
            'leads_identity_ngram_ft',
        ]);

        try {
            $present = collect(Schema::getIndexes('leads'))
                ->pluck('name')
                ->filter()
                ->all();
            self::$fulltextReady = collect($required)->every(
                fn ($name) => in_array($name, $present, true)
            );
        } catch (\Throwable) {
            self::$fulltextReady = false;
        }

        return self::$fulltextReady;
    }

    /**
     * @param  EloquentBuilder<\App\Models\Lead>|QueryBuilder  $query
     * @param  array{comments?: bool, relations?: bool, admin?: bool, lean?: bool, lead_name?: bool}  $options
     * @return EloquentBuilder<\App\Models\Lead>|QueryBuilder
     */
    public static function apply(EloquentBuilder|QueryBuilder $query, string $search, array $options = []): EloquentBuilder|QueryBuilder
    {
        $term = trim($search);
        if ($term === '' || ! self::isActionable($term)) {
            return $query;
        }

        $includeComments = (bool) ($options['comments'] ?? false);
        $includeRelations = (bool) ($options['relations'] ?? true);
        $isAdmin = (bool) ($options['admin'] ?? true);
        $lean = (bool) ($options['lean'] ?? false);
        // false → never match on lead_name (e.g. sales searching the Lead Pool).
        $matchLeadName = (bool) ($options['lead_name'] ?? true);

        // Phone-like terms: scan contact/number columns only (much faster than full OR + whereHas).
        $digits = preg_replace('/\D+/', '', $term) ?? '';
        if (strlen($digits) >= 4 && preg_match('/^[\d\s\+\-\(\)]+$/', $term) === 1) {
            return self::applyPhone($query, self::phoneDigitVariants($digits));
        }

        // Email-like terms: prefer email columns (equality + prefix + contains).
        if (str_contains($term, '@')) {
            return self::applyEmail($query, $term);
        }

        $like = '%'.$term.'%';

        if (! $isAdmin) {
            return self::applyNonAdminText(
                $query,
                $term,
                $like,
                $includeComments,
                $lean,
                $matchLeadName
            );
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

        return $query->where(function ($s) use ($term, $like, $expanded, $includeComments, $includeRelations, $lean) {
            if (self::fulltextEnabled()) {
                self::applyIdentityFulltext($s, $term);
            } else {
                // Legacy leading-wildcard identity OR (used when FULLTEXT index is absent).
                $s->where('lead_name', 'like', $like)
                    ->orWhere('lead_number', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('work_phone', 'like', $like)
                    ->orWhere('work_phone_2', 'like', $like)
                    ->orWhere('lead_source', 'like', $like);
            }

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

            // Responsible person's name — same match rules as whereHas, as a single
            // users.id subquery (avoids correlated EXISTS per lead row).
            if ($includeRelations) {
                // "yasmeen hasan" as one substring won't match "Yasmeen Abdlrhman Hasan" (middle
                // name in between) — also require every individual word to appear somewhere in
                // the name when the search has more than one word.
                $nameWords = array_slice(preg_split('/\s+/', $term, -1, PREG_SPLIT_NO_EMPTY), 0, 6);

                $s->orWhereIn('responsible_person_id', function ($sub) use ($like, $nameWords) {
                    $sub->select('id')
                        ->from('users')
                        ->where(function ($r) use ($like, $nameWords) {
                            $r->where('name', 'like', $like)
                                ->orWhere('display_name', 'like', $like);

                            if (count($nameWords) > 1) {
                                $r->orWhere(function ($multi) use ($nameWords) {
                                    foreach ($nameWords as $word) {
                                        $multi->where('name', 'like', '%'.$word.'%');
                                    }
                                });
                            }
                        });
                });
            }

            if ($includeRelations && ! $lean) {
                $s->orWhereHas('propertyType', function ($pt) use ($like) {
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

    /**
     * Same UAE local/international pairs the previous phone LIKE search used.
     *
     * @return list<string>
     */
    public static function phoneDigitVariants(string $digits): array
    {
        return array_values(array_unique(array_filter([
            $digits,
            (str_starts_with($digits, '971') && strlen($digits) > 3) ? ('0'.substr($digits, 3)) : null,
            (str_starts_with($digits, '0') && strlen($digits) > 1) ? ('971'.substr($digits, 1)) : null,
        ], fn ($variant) => is_string($variant) && $variant !== '')));
    }

    /**
     * work_phone_digits is generated from work_phone only. These extra 0/971 forms
     * are applied to that column alone so a national number still seeks the index
     * when the stored value kept the other prefix.
     *
     * @param  list<string>  $variants
     * @return list<string>
     */
    public static function indexedWorkPhoneVariants(array $variants): array
    {
        $indexed = $variants;
        $raw = $variants[0] ?? '';
        if ($raw !== '' && ! str_starts_with($raw, '0') && ! str_starts_with($raw, '971')) {
            $indexed[] = '0'.$raw;
            $indexed[] = '971'.$raw;
        }

        return array_values(array_unique($indexed));
    }

    /**
     * Strip FULLTEXT boolean operators so user input cannot change MATCH mode.
     */
    public static function fulltextBooleanQuery(string $term): string
    {
        $clean = preg_replace('/[+\-><()~*\"@]+/u', ' ', $term) ?? '';
        $clean = trim(preg_replace('/\s+/u', ' ', $clean) ?? '');

        return $clean;
    }

    /**
     * @param  EloquentBuilder<\App\Models\Lead>|QueryBuilder  $query
     * @return EloquentBuilder<\App\Models\Lead>|QueryBuilder
     */
    private static function applyNonAdminText(
        EloquentBuilder|QueryBuilder $query,
        string $term,
        string $like,
        bool $includeComments,
        bool $lean,
        bool $matchLeadName
    ): EloquentBuilder|QueryBuilder {
        return $query->where(function ($s) use ($term, $like, $includeComments, $lean, $matchLeadName) {
            if (self::fulltextEnabled()) {
                // FT may hit last_name/email/lead_number; restrict to the historical
                // non-admin columns so result semantics stay the same.
                $s->where(function ($identity) use ($term, $like, $matchLeadName) {
                    self::applyIdentityFulltext($identity, $term);
                    $identity->where(function ($cols) use ($like, $matchLeadName) {
                        if ($matchLeadName) {
                            $cols->orWhere('lead_name', 'like', $like);
                        }
                        $cols->orWhere('first_name', 'like', $like)
                            ->orWhere('lead_source', 'like', $like);
                    });
                });
            } else {
                if ($matchLeadName) {
                    $s->orWhere('lead_name', 'like', $like);
                }
                $s->orWhere('first_name', 'like', $like)
                    ->orWhere('lead_source', 'like', $like);
            }

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

    /**
     * @param  mixed  $query  nested where builder
     */
    private static function applyIdentityFulltext($query, string $term): void
    {
        $ft = self::fulltextBooleanQuery($term);
        if ($ft === '') {
            // Nothing searchable after stripping operators — force empty match.
            $query->whereRaw('0 = 1');

            return;
        }

        $groups = config('lead_search.fulltext_match_groups', [
            ['lead_name'],
            ['first_name', 'last_name', 'email', 'lead_source', 'lead_number'],
        ]);

        // Separate whereIn subqueries (not OR MATCH in one WHERE): MySQL often
        // full-scans OR'd MATCH clauses, but uses type=fulltext per subquery.
        $query->where(function ($match) use ($groups, $ft) {
            foreach ($groups as $columns) {
                $columnList = implode(', ', $columns);
                $match->orWhereIn('leads.id', function ($sub) use ($columnList, $ft) {
                    $sub->select('id')
                        ->from('leads')
                        ->whereRaw(
                            "MATCH({$columnList}) AGAINST (? IN BOOLEAN MODE)",
                            [$ft]
                        );
                });
            }
        });
    }

    /**
     * @param  EloquentBuilder<\App\Models\Lead>|QueryBuilder  $query
     * @return EloquentBuilder<\App\Models\Lead>|QueryBuilder
     */
    private static function applyEmail(EloquentBuilder|QueryBuilder $query, string $term): EloquentBuilder|QueryBuilder
    {
        $like = '%'.$term.'%';
        $prefix = $term.'%';

        return $query->where(function ($s) use ($term, $like, $prefix) {
            // Equality first (uses leads_email_index when selective).
            $s->where('email', $term)
                ->orWhere('secondary_email', $term)
                ->orWhere('source_client_email', $term)
                // Prefix can use BTREE for left-anchored typing (john@ex…).
                ->orWhere('email', 'like', $prefix)
                ->orWhere('secondary_email', 'like', $prefix)
                ->orWhere('source_client_email', 'like', $prefix)
                // Preserve historical mid-string contains behaviour.
                ->orWhere('email', 'like', $like)
                ->orWhere('secondary_email', 'like', $like)
                ->orWhere('source_client_email', 'like', $like);
        });
    }

    /**
     * @param  EloquentBuilder<\App\Models\Lead>|QueryBuilder  $query
     * @param  list<string>  $variants
     * @return EloquentBuilder<\App\Models\Lead>|QueryBuilder
     */
    private static function applyPhone(EloquentBuilder|QueryBuilder $query, array $variants): EloquentBuilder|QueryBuilder
    {
        $indexed = self::indexedWorkPhoneVariants($variants);

        return $query->where(function ($outer) use ($variants, $indexed) {
            // Exact and prefix seeks on the generated work_phone column. A leading-wildcard
            // LIKE cannot use leads_work_phone_digits_index; these predicates can.
            $outer->whereIn('leads.id', self::indexedWorkPhoneIdQuery($indexed));

            // Unchanged substring match for every column the old search covered, including
            // work_phone. work_phone_digits does not represent the other numbers.
            $outer->orWhere(function ($legacy) use ($variants) {
                foreach ($variants as $variant) {
                    $like = '%'.$variant.'%';
                    $legacy->orWhere('work_phone', 'like', $like)
                        ->orWhere('work_phone_2', 'like', $like)
                        ->orWhere('whatsapp_number', 'like', $like)
                        ->orWhere('lead_number', 'like', $like)
                        ->orWhere('source_client_phone', 'like', $like);
                }
            });
        });
    }

    /**
     * @param  list<string>  $indexed
     */
    private static function indexedWorkPhoneIdQuery(array $indexed): QueryBuilder
    {
        return DB::table('leads')->select('id')->where(function ($phones) use ($indexed) {
            foreach ($indexed as $variant) {
                $phones->orWhere('work_phone_digits', $variant)
                    ->orWhere('work_phone_digits', 'like', $variant.'%');
            }
        });
    }
}
