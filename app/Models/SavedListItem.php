<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedListItem extends Model
{
    protected $fillable = [
        'saved_list_id',
        'property_id',
    ];

    public function savedList(): BelongsTo
    {
        return $this->belongsTo(SavedList::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
