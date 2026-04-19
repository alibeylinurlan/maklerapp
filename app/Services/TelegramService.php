<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private string $token;

    public function __construct()
    {
        $this->token = config('services.telegram.bot_token');
    }

    public function send(string $chatId, string $message): void
    {
        if (!$this->token || !$chatId) return;

        try {
            Http::post("https://api.telegram.org/bot{$this->token}/sendMessage", [
                'chat_id'    => $chatId,
                'text'       => $message,
                'parse_mode' => 'HTML',
            ]);
        } catch (\Throwable $e) {
            Log::warning("Telegram send failed: " . $e->getMessage());
        }
    }
}
