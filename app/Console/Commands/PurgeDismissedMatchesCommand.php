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
        $dismissed = PropertyMatch::where('status', 'dismissed')
            ->where('dismissed_at', '<=', now()->subHour())
            ->delete();

        $old = PropertyMatch::where('created_at', '<=', now()->subMonth())
            ->delete();

        $this->info("Silindi (dismissed): {$dismissed} | Köhnə (1 ay): {$old} uyğunluq");
        return Command::SUCCESS;
    }
}
