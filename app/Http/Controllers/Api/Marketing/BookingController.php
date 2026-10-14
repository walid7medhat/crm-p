<?php

namespace App\Http\Controllers\Api\Marketing;

use App\Models\MarketingBooking;
use App\Models\MarketingStage;
use App\Models\MarketingTask;
use App\Services\Marketing\MarketingHistoryRecorder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use App\Notifications\MarketingBookingStatusNotification;
use App\Events\PhotoBookingChanged;

/** Marketing → Bookings tab: the team works through public photoshoot requests. */
class BookingController extends MarketingBaseController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(MarketingBooking::STATUSES)],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $query = MarketingBooking::query()
            ->with(['handler:id,name', 'task:id,title,category', 'user:id,name,avatar'])
            ->orderByRaw("FIELD(status, 'new', 'contacted', 'confirmed', 'cancelled')")
            ->orderBy('booking_date')
            ->orderBy('booking_time');

        if ($status = $validated['status'] ?? null) {
            $query->where('status', $status);
        }
        if ($search = trim($validated['search'] ?? '')) {
            $like = '%'.$search.'%';
            $query->where(fn ($q) => $q->where('name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('reference', 'like', $like)
                ->orWhere('location_label', 'like', $like)
                ->orWhere('unit_number', 'like', $like));
        }

        $counts = MarketingBooking::query()
            ->selectRaw('status, COUNT(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status');

        return response()->json([
            'bookings' => $query->limit(300)->get()->map(fn ($b) => $this->format($b))->values(),
            'counts' => collect(MarketingBooking::STATUSES)->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)])
                ->put('all', (int) $counts->sum()),
            'shoot_types' => MarketingBooking::SHOOT_TYPES,
            'can_manage' => $this->isAdmin(),
        ]);
    }

    public function update(Request $request, MarketingBooking $booking): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(MarketingBooking::STATUSES)],
            'internal_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $statusChanged = isset($validated['status']) && $validated['status'] !== $booking->status;
        if ($statusChanged) {
            $validated['status_changed_at'] = now();
            $validated['handled_by'] = Auth::id();
        }
        $booking->update($validated);

        if ($statusChanged) {
            $this->notifyRequester($booking);
        }
        PhotoBookingChanged::send($booking, 'updated');

        return response()->json(['booking' => $this->format($booking->fresh(['handler:id,name', 'task:id,title,category', 'user:id,name,avatar']))]);
    }

    /** Turn the booking into a Production card (Pending), due on the shoot date. */
    public function convert(MarketingBooking $booking): JsonResponse
    {
        if ($booking->task_id && $booking->task) {
            return response()->json(['message' => 'This booking already has a card.', 'booking' => $this->format($booking)], 422);
        }

        $stage = MarketingStage::where('category', 'production')->orderBy('sort_order')->orderBy('id')->first();
        if (! $stage) {
            return response()->json(['message' => 'The Production board has no stages yet.'], 422);
        }

        $type = MarketingBooking::TYPE_LABELS[$booking->shoot_type] ?? $booking->shoot_type;
        $time = Carbon::parse($booking->booking_time)->format('g:i A');
        $wasNew = $booking->status === 'new';

        $task = DB::transaction(function () use ($booking, $stage, $type, $time) {
            MarketingTask::where('stage_id', $stage->id)->increment('position');

            $task = MarketingTask::create([
                'category' => 'production',
                'type' => $booking->shoot_type === 'photo' ? 'photo' : 'video',
                'title' => 'Photoshoot — '.($booking->unit_number ? "Unit {$booking->unit_number}, " : '').($booking->location_label ?: $booking->name),
                'stage_id' => $stage->id,
                'position' => 0,
                'due_date' => $booking->booking_date,
                'reference' => "Booking {$booking->reference}",
                'notes' => implode("\n", array_filter([
                    "{$type} on {$booking->booking_date->format('l, j F Y')} at {$time}",
                    $booking->location_label ? "Location: {$booking->location_label}" : null,
                    $booking->unit_number || $booking->bedrooms !== null
                        ? 'Unit: '.trim(($booking->unit_number ?? '').' · '.(MarketingBooking::bedroomsLabel($booking->bedrooms) ?? ''), ' ·')
                        : null,
                    'Requested by: '.implode(' · ', array_filter([$booking->name, $booking->phone, $booking->email])),
                    $booking->notes ? "Notes: {$booking->notes}" : null,
                ])),
                'created_by' => Auth::id(),
            ]);

            MarketingHistoryRecorder::record($task, 'created', [[
                'field' => 'stage_id', 'label' => 'Stage', 'old' => null, 'new' => $stage->name,
            ]]);

            $booking->update([
                'task_id' => $task->id,
                'handled_by' => $booking->handled_by ?? Auth::id(),
                'status' => $booking->status === 'new' ? 'contacted' : $booking->status,
                'status_changed_at' => $booking->status === 'new' ? now() : $booking->status_changed_at,
            ]);

            return $task;
        });

        // Creating the card moves a new request to "contacted" — let the requester know.
        if ($wasNew) {
            $this->notifyRequester($booking->fresh());
        }
        PhotoBookingChanged::send($booking->fresh(), 'updated');

        return response()->json([
            'booking' => $this->format($booking->fresh(['handler:id,name', 'task:id,title,category', 'user:id,name,avatar'])),
            'task_id' => $task->id,
        ], 201);
    }

    /** Admin only (route middleware). */
    public function destroy(MarketingBooking $booking): JsonResponse
    {
        $booking->delete();
        PhotoBookingChanged::send($booking, 'deleted');

        return response()->json(['message' => 'Booking deleted.']);
    }

    /** Bell + pop-up to the colleague who requested the shoot. */
    private function notifyRequester(MarketingBooking $booking): void
    {
        $owner = $booking->user;
        if (! $owner || (int) $owner->id === (int) Auth::id()) {
            return;
        }
        try {
            $owner->notify(new MarketingBookingStatusNotification($booking, Auth::user()?->name));
        } catch (\Throwable $e) {
            Log::warning('Booking status notification failed: '.$e->getMessage(), ['booking' => $booking->id]);
        }
    }

    private function format(MarketingBooking $b): array
    {
        $at = Carbon::parse($b->booking_date->format('Y-m-d').' '.$b->booking_time);

        return [
            'id' => $b->id,
            'reference' => $b->reference,
            'name' => $b->user?->name ?? $b->name,
            'email' => $b->email,
            'phone' => $b->phone,
            'requester' => $b->user ? \App\Services\Marketing\MarketingPresenter::user($b->user) : null,
            'location' => $b->location_label,
            'unit_number' => $b->unit_number,
            'bedrooms' => $b->bedrooms,
            'bedrooms_label' => MarketingBooking::bedroomsLabel($b->bedrooms),
            'shoot_type' => $b->shoot_type,
            'shoot_type_label' => MarketingBooking::TYPE_LABELS[$b->shoot_type] ?? $b->shoot_type,
            'date' => $b->booking_date->format('Y-m-d'),
            'date_label' => $b->booking_date->format('D, j M Y'),
            'time' => $at->format('H:i'),
            'time_label' => $at->format('g:i A'),
            'is_past' => $at->isPast(),
            'notes' => $b->notes,
            'internal_note' => $b->internal_note,
            'status' => $b->status,
            'handled_by' => $b->handler?->name,
            'status_changed_at' => $b->status_changed_at?->toIso8601String(),
            'task' => $b->task ? ['id' => $b->task->id, 'title' => $b->task->title, 'category' => $b->task->category] : null,
            'created_at' => $b->created_at?->toIso8601String(),
        ];
    }
}
