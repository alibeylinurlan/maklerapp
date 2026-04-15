<?php

namespace App\Observers;

use App\Models\Property;

class PropertyObserver
{
    public function created(Property $property): void
    {
        // Publishing is handled by ScrapeLoopCommand directly
    }
}
