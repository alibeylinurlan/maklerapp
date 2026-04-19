<?php

namespace App\Console\Commands;

use App\Models\PropertyMatch;
use Illuminate\Console\Command;

class PurgeDismissedMatchesCommand extends Command
{
    protected $signature = 'matches:purge-dismissed';
    protected $description = '"Silindi" statusunda 1 saatdan artıq olan uyğunluqları sil';

    public function handle(): int
    {
        $deleted = PropertyMatch::where('status', 'dismissed')
            ->where('dismissed_at', '<=', now()->subHour())
            ->delete();

        $this->info("Silindi: {$deleted} uyğunluq");
        return Command::SUCCESS;
    }
}
