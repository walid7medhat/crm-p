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
        // NOTE: previously collapsed EMPD-XXX into EMP-XXX here, assuming they were the
        // same employee with a typo'd prefix. They are NOT — the external biometric system
        // uses EMP-XXX and EMPD-XXX as genuinely distinct employee code series (e.g. EMP-060
        // is a different real person from EMPD-060). Collapsing them caused attendance data
        // to be silently overwritten between two different employees whenever their numeric
        // suffix matched. Do not reintroduce that collapse.
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
