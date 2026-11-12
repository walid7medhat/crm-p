<?php

namespace App\Http\Requests\Listing;

use App\Models\Area;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class AreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parent_id' => 'nullable|exists:areas,id',
            'name' => ['required', 'string', 'max:255', $this->uniqueUnderParent()],
            'type' => 'required|string|max:255',
            'create_project' => 'sometimes|boolean',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ];
    }

    /**
     * The same name can't exist twice under the same parent (case and outer spaces
     * ignored). The same name under a different parent is fine (e.g. "Downtown" in
     * two cities).
     */
    private function uniqueUnderParent(): Closure
    {
        return function (string $attribute, $value, Closure $fail) {
            $name = trim((string) $value);
            if ($name === '') {
                return;
            }

            $parentId = $this->input('parent_id') ?: null;
            $current = $this->route('area');
            $currentId = $current instanceof Area ? $current->id : $current;

            $existing = Area::query()
                ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])
                ->when($parentId, fn ($q) => $q->where('parent_id', $parentId), fn ($q) => $q->whereNull('parent_id'))
                ->when($currentId, fn ($q) => $q->where('id', '!=', $currentId))
                ->first(['id', 'name']);

            if (! $existing) {
                return;
            }

            $parentName = $parentId ? Area::whereKey($parentId)->value('name') : null;
            $fail($parentName
                ? "\"{$existing->name}\" already exists under {$parentName}."
                : "\"{$existing->name}\" already exists.");
        };
    }
}
