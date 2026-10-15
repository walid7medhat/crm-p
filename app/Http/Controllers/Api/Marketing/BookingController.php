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
use App\Models\User;
/** Marketing → Bookings tab. */
class BookingController extends MarketingBaseController
{
    private const WITH = ['handler:id,name', 'task:id,title,category', 'user:id,name,avatar,parent_id',    'user.employeeProfile.department'];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(MarketingBooking::STATUSES)],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $base = MarketingBooking::query();
        if (! $this->isAdmin()) {
            // الفريق يشوف بس اللي اتقبل (وأي حجز متقبَّل ليه كارد)
            $base->where(fn ($q) => $q->where('status', 'confirmed')->orWhereNotNull('task_id'));
        }

        $query = (clone $base)
            ->with(self::WITH)
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
                ->orWhere('unit_number', 'like', $like)
                ->orWhereHas('user.employeeProfile.department', fn ($d) => $d->where('name', 'like', $like))
                ->orWhereRaw('CAST(units AS CHAR) like ?', [$like]));
        }

        $counts = (clone $base)
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
        // الفريق ميقدرش يلمس حجز لسه ما اتقبلش
        if (! $this->isAdmin()) {
            abort_unless($booking->status === 'confirmed' || $booking->task_id, 403, 'This booking has not been accepted yet.');
        }

        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(MarketingBooking::STATUSES)],
            'internal_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        // التقبّل بيتم بس من زرار "Accept & create card"
        if (($validated['status'] ?? null) === 'confirmed' && $booking->status !== 'confirmed' && ! $booking->task_id) {
            return response()->json(['message' => 'Use "Accept & create card" to confirm a booking.'], 422);
        }

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

        return response()->json(['booking' => $this->format($booking->fresh(self::WITH))]);
    }

    /** Admin only: يقبل الحجز + يعمل كارد Production في نفس اللحظة. */
    public function convert(MarketingBooking $booking): JsonResponse
    {
        abort_unless($this->isAdmin(), 403, 'Only an admin can accept and create the card.');

        if ($booking->task_id && $booking->task) {
            return response()->json(['message' => 'This booking already has a card.', 'booking' => $this->format($booking)], 422);
        }
        if ($booking->status === 'cancelled') {
            return response()->json(['message' => 'This booking is cancelled.'], 422);
        }

        $stage = MarketingStage::where('category', 'production')->orderBy('sort_order')->orderBy('id')->first();
        if (! $stage) {
            return response()->json(['message' => 'The Production board has no stages yet.'], 422);
        }

        $wasConfirmed = $booking->status === 'confirmed';
        $type = MarketingBooking::TYPE_LABELS[$booking->shoot_type] ?? $booking->shoot_type;
        $time = Carbon::parse($booking->booking_time)->format('g:i A');
        $units = MarketingBooking::unitsOf($booking);
        $unitLines = collect($units)
            ->map(fn ($u) => '• Unit '.$u['unit_number'].($u['bedrooms_label'] ? ' · '.$u['bedrooms_label'] : ''))
            ->all();
        $unitTitle = count($units) > 1
            ? count($units).' units'
            : (($units[0]['unit_number'] ?? null) ? 'Unit '.$units[0]['unit_number'] : null);

        $task = DB::transaction(function () use ($booking, $stage, $type, $time, $unitLines, $unitTitle, $wasConfirmed) {
            MarketingTask::where('stage_id', $stage->id)->increment('position');

            $task = MarketingTask::create([
                'category' => 'production',
                'type' => $booking->shoot_type === 'photo' ? 'photo' : 'video',
                'title' => 'Photoshoot — '.($unitTitle ? $unitTitle.', ' : '').($booking->location_label ?: $booking->name),
                'stage_id' => $stage->id,
                'position' => 0,
                'due_date' => $booking->booking_date,
                'reference' => "Booking {$booking->reference}",
                'notes' => implode("\n", array_filter([
                    "{$type} on {$booking->booking_date->format('l, j F Y')} at {$time}",
                    $booking->location_label ? "Location: {$booking->location_label}" : null,
                    $unitLines ? "Units:\n".implode("\n", $unitLines) : null,
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
                'handled_by' => Auth::id(),
                'status' => 'confirmed',
                'status_changed_at' => $wasConfirmed ? $booking->status_changed_at : now(),
            ]);

            return $task;
        });

        // الـ Accept حصل هنا، فالمستخدم لازم يتبلغ
        if (! $wasConfirmed) {
            $this->notifyRequester($booking->fresh());
        }
        PhotoBookingChanged::send($booking->fresh(), 'updated');

        return response()->json([
            'booking' => $this->format($booking->fresh(self::WITH)),
            'task_id' => $task->id,
        ], 201);
    }

    /** Admin only. */
    public function destroy(MarketingBooking $booking): JsonResponse
    {
        abort_unless($this->isAdmin(), 403, 'Only an admin can delete bookings.');

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
            'name' => User::resolveDisplayName($b->user)  ?? $b->name,
            'email' => $b->email,
            'phone' => $b->phone,
            'requester' => $b->user ? \App\Services\Marketing\MarketingPresenter::user($b->user) : null,
            'location' => $b->location_label,
            'unit_number' => $b->unit_number,
            'bedrooms' => $b->bedrooms,
            'bedrooms_label' => MarketingBooking::bedroomsLabel($b->bedrooms),
            'units' => MarketingBooking::unitsOf($b),
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
             'department' => $b->user?->employeeProfile?->department?->name
                ?: User::resolveDisplayName($b->user?->office),
        ];
    }
}