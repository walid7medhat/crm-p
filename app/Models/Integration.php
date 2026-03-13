<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Integration extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'crm_entity',
        'expert_mode',
        'duplicate_handling',
        'project_id',
        'lead_source',
        'page_id',
        'facebook_form_id',
        'facebook_form_name',
        'field_mappings',
        'responsible_person_id',
        'dont_make_responsible_if_not_clocked_in',
        'status',
        'track_enabled',
        'track_keyword',
    ];

    protected $casts = [
        'expert_mode' => 'boolean',
        'track_enabled' => 'boolean',
        'dont_make_responsible_if_not_clocked_in' => 'boolean',
        'field_mappings' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function responsiblePerson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_person_id');
    }
    public function leads()
    {
        return $this->hasMany(Lead::class,'integration_id');
    }

}