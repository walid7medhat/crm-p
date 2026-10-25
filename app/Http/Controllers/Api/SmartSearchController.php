<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\SmartSearchService;
use App\Support\UaeDateRange;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SmartSearchController extends Controller
{
    public function index(Request $request, SmartSearchService $search): JsonResponse
    {
        $started = microtime(true);

        $validated = $request->validate([
            'q' => 'nullable|string|max:200',
            'types' => ['nullable', 'string', 'max:80', function (string $attribute, mixed $value, \Closure $fail) {
                foreach (explode(',', (string) $value) as $part) {
                    $part = trim($part);
                    if ($part === '' || $part === 'all') {
                        continue;
                    }
                    if (! in_array($part, ['leads', 'deals', 'listings', 'places'], true)) {
                        $fail('Unknown search type.');
                    }
                }
            }],
            'limit' => 'nullable|integer|min:1|max:5',
            'area_id' => 'nullable|integer|min:1',
            'bedrooms' => 'nullable|integer|min:0|max:20',
            'listing_status' => 'nullable|string|in:sale,rent,Sale,Rent',
            'property_type_id' => 'nullable|integer|min:1',
            'project_id' => 'nullable|integer|min:1',
            'min_price' => 'nullable|numeric|min:0|max:1000000000',
            'max_price' => 'nullable|numeric|min:0|max:1000000000',
            'created_from' => 'nullable|date_format:Y-m-d',
            'created_to' => 'nullable|date_format:Y-m-d',
            'requirement_area_id' => 'nullable|integer|min:1',
            'confirmed' => 'nullable|boolean',
            'suppress' => 'nullable|string|max:120',
        ]);

        if (! empty($validated['created_from']) || ! empty($validated['created_to'])) {
            $from = $validated['created_from'] ?? $validated['created_to'];
            $to = $validated['created_to'] ?? $validated['created_from'];
            $start = Carbon::parse($from, UaeDateRange::TIMEZONE)->startOfDay();
            $end = Carbon::parse($to, UaeDateRange::TIMEZONE)->startOfDay();
            if ($end->lt($start)) {
                throw ValidationException::withMessages([
                    'created_to' => 'The end date must be on or after the start date.',
                ]);
            }
            if ($start->diffInDays($end) > 31) {
                throw ValidationException::withMessages([
                    'created_to' => 'Date range cannot exceed 31 days.',
                ]);
            }
            $validated['created_from'] = $from;
            $validated['created_to'] = $to;
        }

        $data = $search->search($request->user(), $validated);
        $elapsed = (int) round((microtime(true) - $started) * 1000);

        if ($elapsed > 300) {
            Log::warning('smart_search.slow', [
                'elapsed_ms' => $elapsed,
                'q_length' => mb_strlen((string) ($validated['q'] ?? '')),
                'types' => $validated['types'] ?? 'all',
            ]);
        }

        return ApiResponse::success($data, 'Search completed', 200, [
            'elapsed_ms' => $elapsed,
            'limit' => $data['limit'],
        ]);
    }
}
