<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\Background;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class BackgroundController extends Controller
{
    /**
     * List backgrounds available to the authenticated user, plus which one is
     * currently selected/effective for them. Superadmins also see inactive ones
     * so they can manage them.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $isSuperAdmin = $user->hasRole('super_admin');

        $backgrounds = Background::query()
            ->when(! $isSuperAdmin, fn ($q) => $q->active())
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();

        return ApiResponse::success([
            'backgrounds'           => $backgrounds,
            'selected_id'           => $user->background_id,
            'effective_background'  => $user->background_url,
            'can_manage'            => $isSuperAdmin,
        ], 'Backgrounds retrieved successfully');
    }

    /**
     * Superadmin: upload a new background image.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image'      => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
            'name'       => 'nullable|string|max:255',
            'is_default' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation error', 422, $validator->errors());
        }

        $path = $request->file('image')->store('backgrounds', 'public');

        $background = DB::transaction(function () use ($request, $path) {
            $makeDefault = $request->boolean('is_default');

            if ($makeDefault) {
                Background::where('is_default', true)->update(['is_default' => false]);
            }

            return Background::create([
                'name'        => $request->input('name'),
                'path'        => $path,
                'is_default'  => $makeDefault,
                'is_active'   => true,
                'uploaded_by' => $request->user()->id,
            ]);
        });

        return ApiResponse::success($background, 'Background uploaded successfully', 201);
    }

    /**
     * Superadmin: rename or toggle active state.
     */
    public function update(Request $request, Background $background)
    {
        $validator = Validator::make($request->all(), [
            'name'      => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation error', 422, $validator->errors());
        }

        $background->update($request->only(['name', 'is_active']));

        return ApiResponse::success($background->fresh(), 'Background updated successfully');
    }

    /**
     * Superadmin: mark one background as the system default.
     */
    public function setDefault(Background $background)
    {
        DB::transaction(function () use ($background) {
            Background::where('is_default', true)->update(['is_default' => false]);
            $background->update(['is_default' => true, 'is_active' => true]);
        });

        return ApiResponse::success($background->fresh(), 'Default background updated successfully');
    }

    /**
     * Superadmin: delete a background. Users who picked it fall back to default
     * automatically (FK is set null on delete).
     */
    public function destroy(Background $background)
    {
        if ($background->path && Storage::disk('public')->exists($background->path)) {
            Storage::disk('public')->delete($background->path);
        }

        $background->delete();

        return ApiResponse::success(null, 'Background deleted successfully');
    }
}
