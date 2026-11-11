<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
class Developer extends Model
{
     use HasFactory;
   use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        // use $listing->activities
        return LogOptions::defaults()
            ->logOnlyDirty() 
            ->logAll()       
            ->useLogName('developer');
    }
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /** Columns the listing screens need to resolve a NOC fee (eager-load selects). */
    public const NOC_FEE_COLUMNS = ['noc_fees_ready', 'noc_fees_off_plan', 'dubai_noc_fees_ready', 'dubai_noc_fees_off_plan'];

    /**
     * NOC fee (AED, before VAT) for a unit of this developer.
     * Dubai uses the dubai_* columns; everywhere else (Abu Dhabi) the original ones.
     */
    public function nocFeeFor(bool $isDubai, bool $isOffPlan): float
    {
        $column = ($isDubai ? 'dubai_' : '').($isOffPlan ? 'noc_fees_off_plan' : 'noc_fees_ready');

        return (float) ($this->{$column} ?? 0);
    }

    /** NOC fee payload shared by every API that sends developer data to the listing screens. */
    public function nocFeesPayload(): array
    {
        return [
            'noc_fees_ready' => $this->noc_fees_ready,
            'noc_fees_off_plan' => $this->noc_fees_off_plan,
            'dubai_noc_fees_ready' => $this->dubai_noc_fees_ready,
            'dubai_noc_fees_off_plan' => $this->dubai_noc_fees_off_plan,
        ];
    }
}