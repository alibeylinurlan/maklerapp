<?php

namespace App\Events;

use App\Models\Property;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PropertyCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $property;

    public function __construct(Property $property)
    {
        $thumb = null;
        if (!empty($property->photos)) {
            $thumb = $property->photos[0]['thumb'] ?? $property->photos[0]['medium'] ?? null;
        }

        $this->property = [
            'id'          => $property->id,
            'price'       => $property->price ? number_format($property->price) . ' ' . ($property->currency === 'azn' ? '₼' : '$') : null,
            'rooms'       => $property->rooms,
            'area'        => $property->area,
            'floor'       => $property->floor,
            'floor_total' => $property->floor_total,
            'location'    => $property->location_full_name,
            'category'    => $property->category?->name_az,
            'thumb'       => $thumb,
            'url'         => $property->full_url,
            'at'          => $property->bumped_at?->diffForHumans() ?? now()->diffForHumans(),
        ];
    }

    public function broadcastOn(): array
    {
        return [new Channel('properties')];
    }

    public function broadcastAs(): string
    {
        return 'property.created';
    }
}
