<?php

namespace App\Services\AiLeadIntelligence;

use App\Models\Area;
use App\Models\Lead;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Builder;

/**
 * Mirrors ListingController::getMatchingListings filter pipeline for compact ALI use.
 * Does not change that controller; keeps the same relaxation / price-expansion rules.
 */
class MatchingListingsResolver
{
    /**
     * @return array{count:int,listings:list<array<string,mixed>>,has_criteria:bool}
     */
    public function forLead(Lead $lead, ?int $limit = null): array
    {
        $limit = $limit ?? (int) config('ai_lead_intelligence.property_matches.limit', 5);
        $params = $this->buildParams($lead);

        if (!$params['has_criteria']) {
            return ['count' => 0, 'listings' => [], 'has_criteria' => false];
        }

        $listings = $this->queryMatches(
            $params['project_id'],
            $params['area_id'],
            $params['property_type_id'],
            $params['number_of_bedrooms'],
            $params['min_price'],
            $params['max_price'],
            max(1, $limit)
        );

        $compact = $listings->map(function (Listing $listing) {
            return [
                'id' => $listing->id,
                'reference' => $listing->reference ?? $listing->ref_no ?? null,
                'title' => $listing->title ?? $listing->name ?? null,
                'price' => $listing->price,
                'status' => $listing->status,
                'bedrooms' => $listing->number_of_bedrooms,
                'property_type' => $listing->propertyType?->name,
                'area' => $listing->area?->name,
                'project_id' => $listing->project_id,
            ];
        })->values()->all();

        return [
            'count' => count($compact),
            'listings' => $compact,
            'has_criteria' => true,
        ];
    }

    /**
     * @return array{has_criteria:bool,project_id:?int,area_id:?int,property_type_id:?int,number_of_bedrooms:mixed,min_price:mixed,max_price:mixed}
     */
    public function buildParams(Lead $lead): array
    {
        $projectId = $lead->integration?->project_id ?? $lead->project_id;
        $areaId = $lead->area_id;
        $typeId = $lead->property_type_id;
        $bedrooms = $lead->bedrooms;
        $minPrice = $lead->budget_from ?? $lead->budget;
        $maxPrice = $lead->budget_to ?? $lead->budget;

        $hasCriteria = $projectId || $areaId || $typeId
            || ($bedrooms !== null && $bedrooms !== '')
            || ($minPrice !== null && $minPrice !== '')
            || ($maxPrice !== null && $maxPrice !== '');

        return [
            'has_criteria' => (bool) $hasCriteria,
            'project_id' => $projectId ? (int) $projectId : null,
            'area_id' => $areaId ? (int) $areaId : null,
            'property_type_id' => $typeId ? (int) $typeId : null,
            'number_of_bedrooms' => $bedrooms,
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, Listing>
     */
    protected function queryMatches(
        ?int $projectId,
        ?int $areaId,
        ?int $typeId,
        mixed $bedrooms,
        mixed $minPrice,
        mixed $maxPrice,
        int $limit
    ) {
        $baseQuery = Listing::query()
            ->with(['propertyType:id,name', 'area:id,name'])
            ->where('is_active', true)
            ->where('status', '!=', 'draft')
            ->where('status', '!=', 'converted')
            ->where('is_archived', false);

        if ($areaId) {
            $area = Area::find($areaId);
            if ($area) {
                $childIds = $area->getChildIdsAttribute();
                $allAreaIds = array_merge([$areaId], $childIds);
                $areaQuery = clone $baseQuery;
                $areaQuery->where(function ($q) use ($allAreaIds) {
                    $q->whereIn('area_id', $allAreaIds)
                        ->orWhereHas('project', function ($q2) use ($allAreaIds) {
                            $q2->whereIn('area_id', $allAreaIds);
                        });
                });
                if ($areaQuery->exists()) {
                    $baseQuery = $areaQuery;
                } elseif ($projectId) {
                    $baseQuery->where('project_id', $projectId);
                }
            }
        } elseif ($projectId) {
            $baseQuery->where('project_id', $projectId);
        }

        if ($minPrice && $maxPrice) {
            $priceRanges = [
                [$minPrice, $maxPrice],
                [$minPrice * 0.8, $maxPrice * 1.2],
                [$minPrice * 0.6, $maxPrice * 1.4],
            ];

            foreach ($priceRanges as [$min, $max]) {
                $priceQuery = clone $baseQuery;
                if ($typeId) {
                    $priceQuery->where('property_type_id', $typeId);
                }
                if ($bedrooms !== null && $bedrooms !== '') {
                    $bed = strtolower((string) $bedrooms) === 'studio' ? 0 : $bedrooms;
                    $priceQuery->where('number_of_bedrooms', $bed);
                }
                $priceQuery->whereBetween('price', [$min, $max]);
                if ($priceQuery->exists()) {
                    return $priceQuery->orderByDesc('created_at')->limit($limit)->get();
                }
            }
        }

        $queries = [];
        $queries[] = function ($q) use ($typeId, $bedrooms, $minPrice, $maxPrice) {
            if ($typeId) {
                $q->where('property_type_id', $typeId);
            }
            if ($bedrooms !== null && $bedrooms !== '') {
                $bed = strtolower((string) $bedrooms) === 'studio' ? 0 : $bedrooms;
                $q->where('number_of_bedrooms', $bed);
            }
            if ($minPrice) {
                $q->where('price', '>=', $minPrice);
            }
            if ($maxPrice) {
                $q->where('price', '<=', $maxPrice);
            }
        };
        $queries[] = function ($q) use ($typeId, $bedrooms) {
            if ($typeId) {
                $q->where('property_type_id', $typeId);
            }
            if ($bedrooms !== null && $bedrooms !== '') {
                $bed = strtolower((string) $bedrooms) === 'studio' ? 0 : $bedrooms;
                $q->where('number_of_bedrooms', $bed);
            }
        };
        $queries[] = function ($q) use ($typeId) {
            if ($typeId) {
                $q->where('property_type_id', $typeId);
            }
        };
        $queries[] = function ($q) {
            // location-scoped base only
        };

        $finalQuery = null;
        foreach ($queries as $callback) {
            /** @var Builder $testQuery */
            $testQuery = clone $baseQuery;
            $callback($testQuery);
            if ($testQuery->exists()) {
                $finalQuery = $testQuery;
                break;
            }
        }

        if (!$finalQuery) {
            $finalQuery = $baseQuery;
        }

        return $finalQuery->orderByDesc('created_at')->limit($limit)->get();
    }
}
