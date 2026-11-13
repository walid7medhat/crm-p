<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Support\Brand\Concerns\BrandOwned;

class EvaluationSetting extends Model
{
    use BrandOwned;

    protected $fillable = ['recurrence_mode'];

    /**
     * The single settings row, created with defaults on first access.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    public function isRecurring(): bool
    {
        return $this->recurrence_mode === 'recurring';
    }
}
