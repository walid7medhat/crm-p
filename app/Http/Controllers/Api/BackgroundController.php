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
        $canManage = $user->hasRole('super_admin') || $user->hasRole('admin');

        $backgrounds = Background::query()
            ->when(! $canManage, fn ($q) => $q->active())
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();

        return ApiResponse::success([
            'backgrounds'           => $backgrounds,
            'selected_id'           => $user->background_id,
            'effective_background'  => $user->background_url,
            'can_manage'            => $canManage,
        ], 'Backgrounds retrieved successfully');
    }

    /**
     * Superadmin: upload one or more background images at once.
     * Accepts `images[]` (multi-upload) and still supports a single `image`.
     */
    public function store(Request $request)
    {
        // Normalise a single `image` into the `images` array for uniform handling.
        if ($request->hasFile('image') && ! $request->hasFile('images')) {
            $request->merge([]);
            $request->files->set('images', [$request->file('image')]);
        }

        $validator = Validator::make($request->all(), [
            'images'     => 'required|array|min:1|max:50',
            'images.*'   => 'image|mimes:jpeg,png,jpg,webp|max:8192',
            'name'       => 'nullable|string|max:255',
            'names'      => 'nullable|array',
            'names.*'    => 'nullable|string|max:255',
            'is_default' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation error', 422, $validator->errors());
        }

        $created = DB::transaction(function () use ($request) {
            $makeDefault = $request->boolean('is_default');

            // Only the first uploaded image can become the default.
            if ($makeDefault) {
                Background::where('is_default', true)->update(['is_default' => false]);
            }

            $items = [];
            $customNames = $request->input('names', []);
            $sharedName = trim((string) $request->input('name', '')) ?: null;

            foreach (array_values($request->file('images')) as $index => $file) {
                $path = $file->store('backgrounds', 'public');
                $perFileName = isset($customNames[$index]) ? trim((string) $customNames[$index]) : '';
                $fallbackName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $name = $perFileName !== ''
                    ? $perFileName
                    : ($sharedName
                        ? ($index === 0 ? $sharedName : $sharedName . ' ' . ($index + 1))
                        : $fallbackName);

                $items[] = Background::create([
                    'name'        => $name,
                    'path'        => $path,
                    'is_default'  => $makeDefault && $index === 0,
                    'is_active'   => true,
                    'uploaded_by' => $request->user()->id,
                ]);
            }

            return $items;
        });

        return ApiResponse::success(
            $created,
            count($created) . ' background' . (count($created) === 1 ? '' : 's') . ' uploaded successfully',
            201
        );
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
