<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnderstandingCampaign extends Model
{
    protected $fillable = [
        'stream_id',
        'attended_on',
        'first_name',
        'last_name',
        'street_name',
        'postal_code',
        'phone_number',
        're_dedicating',
        'first_time',
        'who_invited',
        'allocated_group_id',
    ];

    protected $casts = [
        'attended_on' => 'date',
        're_dedicating' => 'boolean',
        'first_time' => 'boolean',
    ];

    public function stream(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'stream_id');
    }

    public function allocatedGroup(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'allocated_group_id');
    }
}
