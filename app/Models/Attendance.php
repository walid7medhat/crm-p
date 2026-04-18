<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $fillable = [
        'employee_id',
        'employee_name',
        'status',
        'user_id',
        'date',
        'check_in',
        'check_out',
    ];

    protected $casts = [
        'date' => 'date',
        'check_in' => 'datetime',
        'check_out' => 'datetime',
    ];

    /**
     * Align with frontend / HR API normalization (handles "#EMPEMP-006" → "EMP-006").
     */
    public static function normalizeEmployeeId(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $s = strtoupper(str_replace('#', '', trim((string) $raw)));
        $s = preg_replace('/(EMP)+/', 'EMP', $s) ?? $s;
        $s = preg_replace('/EMP+/', 'EMP', $s) ?? $s;
        $s = trim((string) $s);

        return $s !== '' ? $s : null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
