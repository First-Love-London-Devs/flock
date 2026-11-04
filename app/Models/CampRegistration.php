<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampRegistration extends Model
{
    protected $fillable = [
        'full_name',
        'phone_number',
        'email',
        'camp',
        'church',
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

    /**
     * The church to show for this row. Copenhagen-era rows store it directly;
     * Basel-era rows only carried denomination + country, so derive a label.
     */
    public function getChurchLabelAttribute(): string
    {
        return $this->church ?: implode(' · ', array_filter([$this->denomination, $this->country]));
    }
}
