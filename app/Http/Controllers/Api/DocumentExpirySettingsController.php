<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DocumentExpirySettingsController extends Controller
{
    protected array $defaults = [
        'passport_days' => 15,
        'labor_card_days' => 15,
        'emirates_id_days' => 15,
        'residency_days' => 15,
    ];

    private function isHr($user): bool
    {
        if (!$user) return false;
        return $user->hasRole(['super_admin', 'admin', 'hr']);
    }

    private function resolveRow()
    {
        $row = DB::table('document_expiry_settings')->orderBy('id')->first();
        if (!$row) {
            $id = DB::table('document_expiry_settings')->insertGetId(array_merge($this->defaults, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
            $row = DB::table('document_expiry_settings')->find($id);
        }
        return $row;
    }

    public function show(Request $request): JsonResponse
    {
        if (!$this->isHr($request->user())) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $row = $this->resolveRow();

        return response()->json([
            'passport_days' => (int) $row->passport_days,
            'labor_card_days' => (int) $row->labor_card_days,
            'emirates_id_days' => (int) $row->emirates_id_days,
            'residency_days' => (int) $row->residency_days,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        if (!$this->isHr($request->user())) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'passport_days' => ['required', 'integer', 'min:1', 'max:365'],
            'labor_card_days' => ['required', 'integer', 'min:1', 'max:365'],
            'emirates_id_days' => ['required', 'integer', 'min:1', 'max:365'],
            'residency_days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $row = $this->resolveRow();
        DB::table('document_expiry_settings')->where('id', $row->id)->update(array_merge($validated, [
            'updated_at' => now(),
        ]));

        return response()->json([
            'success' => true,
            ...$validated,
        ]);
    }
}
