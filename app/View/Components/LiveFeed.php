<?php

namespace App\View\Components;

use App\Models\Property;
use Illuminate\View\Component;

class LiveFeed extends Component
{
    public int $maxId;
    public bool $canAccess;

    public function __construct()
    {
        $this->canAccess = user_has_feature('live_feed');
        $this->maxId = $this->canAccess
            ? (Property::where('is_owner', true)->max('id') ?? 0)
            : 0;
    }

    public function render()
    {
        return view('components.live-feed');
    }
}
