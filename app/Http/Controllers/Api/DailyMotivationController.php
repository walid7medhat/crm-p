<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\MotivationMessage;
use App\Models\User;
use App\Notifications\DailyMotivationTestNotification;
use App\Services\DailyMotivation\DailyMotivationService;
use App\Services\DailyMotivation\MotivationRotation;
use App\Services\DailyMotivation\MotivationWorkbookImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class DailyMotivationController extends Controller
{
    public function __construct(
        private DailyMotivationService $motivation,
        private MotivationWorkbookImporter $importer,
    ) {}

    public function today(Request $request)
    {
        return ApiResponse::success(
            $this->motivation->presentFor($request->user()),
            'Daily motivation retrieved'
        );
    }

    public function dismiss(Request $request)
    {
        return ApiResponse::success(
            $this->motivation->dismiss($request->user()),
            'Daily motivation dismissed'
        );
    }

    public function overview(Request $request)
    {
        if ($denied = $this->denyUnlessSuperAdmin($request)) {
            return $denied;
        }

        return ApiResponse::success($this->motivation->overview(), 'Daily motivation overview');
    }

    public function messages(Request $request)
    {
        if ($denied = $this->denyUnlessSuperAdmin($request)) {
            return $denied;
        }

        return ApiResponse::success([
            'messages' => $this->motivation->messagesForAdmin(),
        ], 'Messages retrieved');
    }

    public function updateMessage(Request $request, MotivationMessage $message)
    {
        if ($denied = $this->denyUnlessSuperAdmin($request)) {
            return $denied;
        }

        $data = $request->validate([
            'body_en' => ['required', 'string', 'max:5000'],
            'body_ar' => ['required', 'string', 'max:5000'],
            'subtitle_en' => ['nullable', 'string', 'max:180'],
            'subtitle_ar' => ['nullable', 'string', 'max:180'],
            'is_enabled' => ['sometimes', 'boolean'],
        ]);

        $updated = $this->motivation->updateMessage($message, $data);

        return ApiResponse::success([
            'id' => $updated->id,
            'number' => $updated->number,
            'body_en' => $updated->body_en,
            'body_ar' => $updated->body_ar,
            'subtitle_en' => $updated->subtitle_en,
            'subtitle_ar' => $updated->subtitle_ar,
            'is_enabled' => (bool) $updated->is_enabled,
        ], 'Message updated');
    }

    public function updateMessageEnabled(Request $request, MotivationMessage $message)
    {
        if ($denied = $this->denyUnlessSuperAdmin($request)) {
            return $denied;
        }

        $data = $request->validate([
            'is_enabled' => ['required', 'boolean'],
        ]);

        $updated = $this->motivation->setMessageEnabled($message, $request->boolean('is_enabled'));

        return ApiResponse::success([
            'id' => $updated->id,
            'number' => $updated->number,
            'is_enabled' => (bool) $updated->is_enabled,
            'enabled_remaining' => MotivationMessage::query()->where('is_enabled', true)->count(),
        ], $data['is_enabled'] ? 'Message enabled' : 'Message paused');
    }

    public function delivery(Request $request)
    {
        if ($denied = $this->denyUnlessSuperAdmin($request)) {
            return $denied;
        }

        $request->validate([
            'enabled' => ['required', 'boolean'],
            'confirm' => ['required', 'accepted'],
        ]);

        try {
            $settings = $this->motivation->setDelivery($request->boolean('enabled'), (int) $request->user()->id);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success([
            'is_enabled' => (bool) $settings->is_enabled,
            'anchor_date' => $settings->anchor_date?->toDateString(),
            'delivery' => $settings->is_enabled ? 'enabled' : 'disabled',
        ], $settings->is_enabled ? 'Daily motivation is on for active users' : 'Daily motivation is off');
    }

    public function sendToday(Request $request)
    {
        if ($denied = $this->denyUnlessSuperAdmin($request)) {
            return $denied;
        }

        return ApiResponse::success(
            $this->motivation->deliverToday(),
            'Daily motivation send checked'
        );
    }

    public function preview(Request $request)
    {
        if ($denied = $this->denyUnlessSuperAdmin($request)) {
            return $denied;
        }

        $data = $request->validate([
            'number' => ['required', 'integer', 'min:1', 'max:'.MotivationRotation::CYCLE],
        ]);

        try {
            $payload = $this->motivation->preview((int) $data['number'], true);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($payload, 'Test preview');
    }

    public function sendTest(Request $request)
    {
        if ($denied = $this->denyUnlessSuperAdmin($request)) {
            return $denied;
        }

        $data = $request->validate([
            'number' => ['required', 'integer', 'min:1', 'max:'.MotivationRotation::CYCLE],
        ]);

        $message = MotivationMessage::query()->where('number', (int) $data['number'])->first();
        if (! $message) {
            return ApiResponse::error('Message '.$data['number'].' is not in the collection.', 422);
        }

        $request->user()->notify(new DailyMotivationTestNotification($message));

        return ApiResponse::success(
            $this->motivation->preview((int) $message->number, true),
            'Test sent to you only'
        );
    }

    public function import(Request $request)
    {
        if ($denied = $this->denyUnlessSuperAdmin($request)) {
            return $denied;
        }

        $request->validate([
            'file' => ['required', 'file', 'max:5120'],
        ]);

        $extension = strtolower($request->file('file')->getClientOriginalExtension());
        if (! in_array($extension, ['xlsx', 'xls', 'csv'], true)) {
            return ApiResponse::error('Upload the Excel workbook (.xlsx) or a CSV export.', 422);
        }

        $stored = $request->file('file')->storeAs(
            'motivation',
            'import-'.now()->format('YmdHis').'.'.$extension
        );
        $fullPath = Storage::disk('local')->path($stored);

        try {
            $result = $this->importer->import($fullPath);
        } catch (Throwable $e) {
            Storage::disk('local')->delete($stored);

            return ApiResponse::error($e->getMessage(), 422);
        }

        Storage::disk('local')->delete($stored);
        $this->motivation->forgetCaches();

        return ApiResponse::success($result, 'Messages imported');
    }

    public function lookup(Request $request)
    {
        if ($denied = $this->denyUnlessSuperAdmin($request)) {
            return $denied;
        }

        $data = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:120'],
        ]);

        return ApiResponse::success([
            'users' => $this->motivation->lookupUsers($data['q']),
        ], 'Users found');
    }

    public function resetUser(Request $request, User $user)
    {
        if ($denied = $this->denyUnlessSuperAdmin($request)) {
            return $denied;
        }

        $request->validate([
            'confirm' => ['required', 'accepted'],
        ]);

        try {
            $result = $this->motivation->resetUser($user);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($result, 'Rotation reset for this person');
    }

    private function denyUnlessSuperAdmin(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole('super_admin')) {
            return ApiResponse::error('Unauthorized access.', 403);
        }

        return null;
    }
}
