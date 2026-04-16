<?php

namespace App\Jobs;

use App\Models\CustomerRequest;
use App\Models\Property;
use App\Models\PropertyMatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MatchRequestToExistingPropertiesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        private int $requestId,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $request = CustomerRequest::find($this->requestId);
        if (!$request || !$request->is_active) return;

        Property::where('is_business', false)
            ->cursor()
            ->each(function (Property $property) use ($request) {
                if (MatchNewPropertyJob::matchesFilters($property, $request->filters)) {
                    PropertyMatch::firstOrCreate(
                        [
                            'property_id'         => $property->id,
                            'customer_request_id' => $request->id,
                        ],
                        [
                            'user_id' => $request->user_id,
                            'status'  => 'new',
                        ]
                    );
                }
            });
    }
}
