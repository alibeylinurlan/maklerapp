<?php

namespace App\Jobs;

use App\Models\Property;
use App\Services\BinaAz\BinaAzOwnerChecker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckOwnershipJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 5;

    public function __construct(
        private int $propertyId,
    ) {
        $this->onQueue('default');
    }

    public function handle(BinaAzOwnerChecker $checker): void
    {
        $property = Property::find($this->propertyId);
        if (!$property) return;

        // Artıq yoxlanılıbsa, skip
        if ($property->is_owner !== null) return;

        $isOwner = $checker->check($property);

        if ($isOwner) {
            // Mülkiyyətçidir — match tap
            Log::info("Owner confirmed: {$property->bina_id} - {$property->location_full_name}");
            MatchNewPropertyJob::dispatch($property->id);
        } else {
            // Mülkiyyətçi deyil — DB-dən sil
            Log::info("Not owner, deleting: {$property->bina_id}");
            $property->delete();
        }
    }
}
