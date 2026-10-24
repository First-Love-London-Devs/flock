<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampRegistration extends Model
{
    protected $fillable = [
        'full_name',
        'phone_number',
        'email',
        'country_group_id',
        'church_group_id',
        'attending_dates',
        'travelling_from',
        'needs_accommodation',
        'bringing_children',
        'number_of_children',
        'notes',
    ];

    protected $casts = [
        'attending_dates' => 'array',
        'needs_accommodation' => 'boolean',
        'bringing_children' => 'boolean',
    ];

    public function church(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'church_group_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'country_group_id');
    }
}
