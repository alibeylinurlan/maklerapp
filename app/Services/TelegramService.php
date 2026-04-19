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

        $status = 'ok';
        $error  = null;

        try {
            Http::post("https://api.telegram.org/bot{$this->token}/sendMessage", [
                'chat_id'    => $chatId,
                'text'       => $message,
                'parse_mode' => 'HTML',
            ]);
        } catch (\Throwable $e) {
            $status = 'fail';
            $error  = $e->getMessage();
            Log::warning("Telegram send failed: " . $e->getMessage());
        }

        $logMessage = implode(' | ', array_filter([
            "to:{$chatId}",
            "status:{$status}",
            $error ? "error:{$error}" : null,
            strip_tags(preg_replace('/\s+/', ' ', $message)),
        ]));

        Log::channel('telegram')->info($logMessage);
    }
}
