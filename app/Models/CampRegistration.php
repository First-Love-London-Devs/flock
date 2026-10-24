<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampRegistration extends Model
{
    protected $fillable = [
        'full_name',
        'phone_number',
        'email',
        'country',
        'denomination',
        'travelling_from',
        'needs_accommodation',
        'bringing_children',
        'number_of_children',
        'notes',
    ];

    protected $casts = [
        'needs_accommodation' => 'boolean',
        'bringing_children' => 'boolean',
    ];
}
