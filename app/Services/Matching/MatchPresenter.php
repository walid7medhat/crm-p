<?php

namespace App\Services\Matching;

use App\Models\Area;
use App\Models\Lead;
use App\Models\LeadListingMatch;
use App\Models\Listing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Shapes matches, listings and leads for Match Studio and writes the WhatsApp message. */
class MatchPresenter
{
    public function __construct(private LeadMatcher $matcher)
    {
    }

    public function match(LeadListingMatch $match): array
    {
        $listing = $match->listing;

        return [
            'id' => $match->id,
            'score' => $match->score,
            'status' => $match->status,
            'bucket' => $match->bucket,
            'fits' => $match->reasons['fits'] ?? [],
            'gaps' => $match->reasons['gaps'] ?? [],
            'previous_price' => $match->previous_price !== null ? (float) $match->previous_price : null,
            'sent_at' => $match->sent_at?->toIso8601String(),
            'listing' => $listing ? $this->listing($listing) : null,
        ];
    }

    public function listing(Listing $listing): array
    {
        $image = $this->imagePath($listing);
        $beds = $listing->number_of_bedrooms;

        return [
            'id' => $listing->id,
            'reference_number' => $listing->reference_number,
            'title' => $listing->project?->title ?: ($listing->reference_number ?: 'Property #'.$listing->id),
            'area' => $listing->area?->name,
            'price' => $listing->price !== null ? (float) $listing->price : null,
            'bedrooms' => $beds,
            'bedrooms_label' => $beds === null ? null : $this->matcher->bedsLabel((int) $beds),
            'size_sqft' => $listing->size_sqft,
            'property_type' => $listing->propertyType?->name,
            'listing_status' => $listing->listing_status,
            'completion_status' => $listing->completion_status,
            'main_image' => $image ? route('image.watermark', ['path' => $image]) : null,
        ];
    }

    /** A lead as Match Studio shows it: who, how to reach them, and what they asked for. */
    public function lead(Lead $lead): array
    {
        $req = $this->matcher->requirementsFor($lead) ?? [];

        return [
            'id' => $lead->id,
            'name' => $lead->lead_name,
            'first_name' => $this->firstName($lead),
            'initials' => $this->initials($lead->lead_name),
            'stage' => $lead->stage?->name,
            'stage_id' => $lead->stage_id,
            'whatsapp' => $this->phoneDigits($lead->whatsapp_number ?: $lead->work_phone),
            'quiet_days' => $this->matcher->quietDays($lead),
            'created_at' => $lead->created_at?->toIso8601String(),
            'requirements' => $this->requirementRows($req, $lead),
        ];
    }

    /** @return array<int, array{label:string, value:string}> */
    public function requirementRows(array $req, Lead $lead): array
    {
        $rows = [];
        $currency = $lead->currency ?: 'AED';
        $money = fn ($n) => $currency.' '.number_format((float) $n, 0);

        if (! empty($req['budget_from']) && ! empty($req['budget_to'])) {
            $rows[] = ['label' => 'Budget', 'value' => $money($req['budget_from']).' – '.$money($req['budget_to'])];
        } elseif (! empty($req['budget_to'])) {
            $rows[] = ['label' => 'Budget', 'value' => 'up to '.$money($req['budget_to'])];
        } elseif (! empty($req['budget_from'])) {
            $rows[] = ['label' => 'Budget', 'value' => 'from '.$money($req['budget_from'])];
        }
        if (($req['bedrooms'] ?? null) !== null) {
            $rows[] = ['label' => 'Bedrooms', 'value' => $req['bedrooms'] === 0 ? 'Studio' : (string) $req['bedrooms']];
        }
        if (! empty($req['area_id']) && ($area = Area::query()->select(['id', 'name'])->find($req['area_id']))) {
            $rows[] = ['label' => 'Area', 'value' => $area->name];
        }
        if (! empty($req['property_type_id']) && ($type = DB::table('property_types')->where('id', $req['property_type_id'])->value('name'))) {
            $rows[] = ['label' => 'Type', 'value' => $type];
        }
        if (! empty($req['lead_type'])) {
            $rows[] = ['label' => 'Looking to', 'value' => match ($req['lead_type']) {
                'sale' => 'Buy',
                'rent' => 'Rent',
                default => 'Buy or rent',
            }];
        }
        if (! empty($req['property_status'])) {
            $rows[] = ['label' => 'Timing', 'value' => match ($req['property_status']) {
                'ready' => 'Ready to move',
                'off_plan' => 'Off-plan',
                default => 'Ready or off-plan',
            }];
        }

        return $rows;
    }

    /** The message the agent sends, one line per home. */
    public function whatsappMessage(Lead $lead, iterable $matches, ?string $agentName): string
    {
        $lines = [];
        $i = 0;
        foreach ($matches as $match) {
            $l = $match->listing;
            if (! $l) {
                continue;
            }
            $i++;
            $bits = array_filter([
                $l->number_of_bedrooms !== null ? $this->matcher->bedsLabel((int) $l->number_of_bedrooms) : null,
                $l->propertyType?->name,
                $l->size_sqft ? number_format((int) $l->size_sqft).' sqft' : null,
                $l->price ? $this->shortPrice((float) $l->price) : null,
            ]);
            $title = $l->project?->title ?: ($l->reference_number ?: 'Property #'.$l->id);
            $lines[] = $i.'. '.implode(', ', array_filter([$title, $l->area?->name])).': '.implode(' · ', $bits);
        }

        $count = $i;
        $intro = $count === 1 ? 'a home that fits' : "{$count} homes that fit";
        $ask = $count === 1 ? 'it' : 'any of them';
        $sign = $agentName ? "\n".$agentName : '';

        return "Hi {$this->firstName($lead)}, I picked {$intro} what you asked for:\n\n"
            .implode("\n", $lines)
            ."\n\nWould you like to view {$ask} this week?{$sign}";
    }

    public function shortPrice(float $price): string
    {
        if ($price >= 1_000_000) {
            return 'AED '.rtrim(rtrim(number_format($price / 1_000_000, 2), '0'), '.').'M';
        }
        if ($price >= 1_000) {
            return 'AED '.number_format($price / 1_000).'K';
        }

        return 'AED '.number_format($price);
    }

    public function firstName(Lead $lead): string
    {
        $first = trim((string) $lead->first_name);
        if ($first !== '') {
            return $first;
        }
        $name = trim((string) $lead->lead_name);

        return $name !== '' ? explode(' ', $name)[0] : 'there';
    }

    public function initials(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
        $letters = array_map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)), array_slice(array_filter($parts), 0, 2));

        return implode('', $letters) ?: '?';
    }

    public function phoneDigits(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '05') && strlen($digits) === 10) {
            $digits = '971'.substr($digits, 1);
        }

        return $digits;
    }

    private function imagePath(Listing $listing): ?string
    {
        if ($listing->hero_image_path && Storage::disk('public')->exists($listing->hero_image_path)) {
            return $listing->hero_image_path;
        }
        $first = $listing->relationLoaded('firstGalleryImage') ? $listing->firstGalleryImage : $listing->firstGalleryImage()->first();

        return $first?->image_path;
    }
}
