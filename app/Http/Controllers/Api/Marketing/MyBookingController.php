<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Events\PhotoBookingChanged;
use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\MarketingBooking;
use App\Models\User;
use App\Notifications\MarketingBookingNotification;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * "Photo Shoots" page — a logged-in user (permission `photo-booking`) requests a shoot and
 * follows its status. Contact details come from the user's account; the marketing team works
 * the requests in Marketing → Bookings.
 */
class MyBookingController extends Controller
{
    public function index(): JsonResponse
    {
        $bookings = MarketingBooking::query()
            ->where('user_id', Auth::id())
            ->with(['handler:id,name'])
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(fn (MarketingBooking $b) => self::format($b))
            ->values();

        return response()->json([
            'bookings' => $bookings,
            'shoot_types' => MarketingBooking::SHOOT_TYPES,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'shoot_type' => ['required', Rule::in(array_keys(MarketingBooking::SHOOT_TYPES))],
            'area_id' => ['required', 'integer', 'exists:areas,id'],
            // 'unit_number' => ['required', 'string', 'max:60'],
            // 'bedrooms' => ['required', 'integer', 'between:0,9'],
            'booking_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before:+1 year'],
            'booking_time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'units' => ['required', 'array', 'min:1', 'max:20'],
            'units.*.unit_number' => ['required', 'string', 'max:60', 'distinct:ignore_case'],
            'units.*.bedrooms' => ['required', 'integer', 'between:0,9'],
        ], [
            'area_id.required' => 'Please choose the location.',
            'unit_number.required' => 'Please enter the unit number.',
            'bedrooms.required' => 'Please choose the number of bedrooms.',
            'booking_date.after_or_equal' => 'Please choose today or a future date.',
            'booking_date.before' => 'Please choose a date within the next 12 months.',
            'units.required' => 'Please add at least one unit.',
            'units.*.unit_number.required' => 'Please enter the unit number.',
            'units.*.unit_number.distinct' => 'This unit number is entered twice.',
            'units.*.bedrooms.required' => 'Please choose the number of bedrooms.',
        ]);

        $when = Carbon::parse($validated['booking_date'].' '.$validated['booking_time']);
        if ($when->isPast()) {
            return response()->json([
                'message' => 'That time has already passed — please choose a later time.',
                'errors' => ['booking_time' => ['That time has already passed — please choose a later time.']],
            ], 422);
        }

        // Same user, same slot, still open → return the existing request (double click etc.).
        $existing = MarketingBooking::where('user_id', $user->id)
            ->whereDate('booking_date', $validated['booking_date'])
            ->where('booking_time', $validated['booking_time'].':00')
            ->where('status', '!=', 'cancelled')
            ->first();
        if ($existing) {
            return response()->json(['booking' => self::format($existing), 'duplicate' => true]);
        }

        $area = Area::find($validated['area_id']);

        $booking = null;
        $units = collect($validated['units'])->map(fn ($u) => [
            'unit_number' => trim(strip_tags($u['unit_number'])),
            'bedrooms' => (int) $u['bedrooms'],
        ])->values()->all();
            for ($attempt = 0; $attempt < 3 && ! $booking; $attempt++) {
                try {
                    $booking = MarketingBooking::create([
                        'reference' => MarketingBooking::nextReference(),
                        'user_id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone ?: ($user->company_mobile ?: ($user->personal_phone ?: null)),
                        'shoot_type' => $validated['shoot_type'],
                        'area_id' => $area?->id,
                        'location_label' => $area?->title ?: $area?->name,
                        'units' => $units,
                        'unit_number' => $units[0]['unit_number'],
                        'bedrooms' => $units[0]['bedrooms'],
                        'booking_date' => $validated['booking_date'],
                        'booking_time' => $validated['booking_time'],
                        'notes' => isset($validated['notes']) ? trim(strip_tags($validated['notes'])) ?: null : null,
                        'status' => 'new',
                        'ip' => $request->ip(),
                        'user_agent' => substr((string) $request->userAgent(), 0, 255),
                    ]);
                } catch (QueryException $e) {
                    // 1062 = duplicate entry، ونتأكد إنه على عمود reference بالذات
                    $isDuplicateReference = ($e->errorInfo[1] ?? null) === 1062 && str_contains($e->getMessage(), 'reference');
                    if (! $isDuplicateReference) {
                        throw $e;   // أي خطأ تاني يظهر بوضوح (مثلًا: Unknown column 'units')
                    }
                }
            }

            if (! $booking) {
                return response()->json(['message' => 'Could not save your request. Please try again.'], 500);
            }

        $this->notifyTeam($booking);
        PhotoBookingChanged::send($booking, 'created');

        return response()->json(['booking' => self::format($booking->fresh(['handler:id,name']))], 201);
    }

    /** The requester can withdraw a request that isn't confirmed yet. */
    public function cancel(MarketingBooking $booking): JsonResponse
    {
        abort_unless((int) $booking->user_id === (int) Auth::id(), 404);

        if (in_array($booking->status, ['confirmed', 'cancelled'], true)) {
            return response()->json(['message' => 'This request can no longer be cancelled here — please contact the marketing team.'], 422);
        }

        $booking->update(['status' => 'cancelled', 'status_changed_at' => now(), 'handled_by' => Auth::id()]);
        PhotoBookingChanged::send($booking, 'cancelled');

        return response()->json(['booking' => self::format($booking->fresh(['handler:id,name']))]);
    }

    public static function format(MarketingBooking $b): array
    {
        $at = Carbon::parse($b->booking_date->format('Y-m-d').' '.$b->booking_time);

        return [
            'id' => $b->id,
            'reference' => $b->reference,
            'shoot_type' => $b->shoot_type,
            'shoot_type_label' => MarketingBooking::TYPE_LABELS[$b->shoot_type] ?? $b->shoot_type,
            'location' => $b->location_label,
            'unit_number' => $b->unit_number,
            'bedrooms' => $b->bedrooms,
            'bedrooms_label' => MarketingBooking::bedroomsLabel($b->bedrooms),
            'date' => $b->booking_date->format('Y-m-d'),
            'date_label' => $b->booking_date->format('D, j M Y'),
            'time' => $at->format('H:i'),
            'time_label' => $at->format('g:i A'),
            'is_past' => $at->isPast(),
            'notes' => $b->notes,
            'status' => $b->status,
            'handled_by' => $b->handler?->name,
            'status_changed_at' => $b->status_changed_at?->toIso8601String(),
            'created_at' => $b->created_at?->toIso8601String(),
        ];
    }

    /** Everyone who can open Marketing (permission holders + super admins). */
    private function notifyTeam(MarketingBooking $booking): void
    {
        try {
            User::permission('marketing')->where('status', 'active')->get()
                ->merge(User::role('super_admin')->where('status', 'active')->get())
                ->unique('id')
                ->reject(fn (User $u) => (int) $u->id === (int) $booking->user_id)
                ->each(fn (User $u) => $u->notify(new MarketingBookingNotification($booking)));
        } catch (\Throwable $e) {
            Log::warning('Booking notification failed: '.$e->getMessage(), ['booking' => $booking->id]);
        }
    }
}
