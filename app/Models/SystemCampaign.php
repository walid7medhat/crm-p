<?php

namespace App\Models;

use App\Support\SystemCampaignCatalog;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class SystemCampaign extends Model
{
    protected $fillable = [
        'title',
        'audiences',
        'frequency_hours',
        'is_active',
        'desktop_image_path',
        'mobile_image_path',
        'created_by',
    ];

    protected $casts = [
        'audiences' => 'array',
        'frequency_hours' => 'integer',
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function impressions(): HasMany
    {
        return $this->hasMany(SystemCampaignImpression::class);
    }

    public function getDesktopImageUrlAttribute(): ?string
    {
        return $this->publicUrl($this->desktop_image_path);
    }

    public function getMobileImageUrlAttribute(): ?string
    {
        return $this->publicUrl($this->mobile_image_path);
    }

    public function isWithinCooldown(CarbonInterface|string|null $shownAt): bool
    {
        if (!$shownAt) {
            return false;
        }

        $shown = $shownAt instanceof CarbonInterface ? $shownAt : Carbon::parse($shownAt);

        return $shown->copy()->addHours((int) $this->frequency_hours)->isFuture();
    }

    public function isDueFor(User $user, ?SystemCampaignImpression $impression): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if (!SystemCampaignCatalog::matches($user, $this->audiences ?? [])) {
            return false;
        }

        return !$this->isWithinCooldown($impression?->shown_at);
    }

    public function deleteStoredImages(): void
    {
        $disk = Storage::disk('public');
        foreach ([$this->desktop_image_path, $this->mobile_image_path] as $path) {
            if ($path && $disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }

    private function publicUrl(?string $path): ?string
    {
        return $path ? asset('storage/' . $path) : null;
    }
}
