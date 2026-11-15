<?php

namespace App\Services;

use App\Models\HotelProfile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Sends operational alerts to Telegram and e-mail (whichever is configured). */
class Notifier
{
    /** @return array{telegram: bool, email: bool} what was delivered */
    public function send(string $subject, string $body): array
    {
        $hotel = HotelProfile::current();
        $text = "[{$hotel->name}] {$subject}\n{$body}";

        return ['telegram' => $this->telegram($text), 'email' => $this->email("SangaTV Plus · {$hotel->name}: {$subject}", $body)];
    }

    public function telegramConfigured(): bool
    {
        return filled(config('sangatv.telegram.bot_token')) && filled(config('sangatv.telegram.chat_id'));
    }

    /** @return array<int, string> */
    public function emailRecipients(): array
    {
        return array_values(array_unique([...HotelProfile::current()->reportEmailList(), ...config('sangatv.alerts.emails')]));
    }

    public function emailConfigured(): bool
    {
        return $this->emailRecipients() !== [] && ! in_array(config('mail.default'), ['log', 'array'], true);
    }

    private function telegram(string $text): bool
    {
        if (! $this->telegramConfigured()) {
            return false;
        }
        try {
            return Http::timeout(15)->asForm()->post('https://api.telegram.org/bot'.config('sangatv.telegram.bot_token').'/sendMessage', [
                'chat_id' => config('sangatv.telegram.chat_id'),
                'text' => mb_substr($text, 0, 4000),
                'disable_web_page_preview' => 'true',
            ])->successful();
        } catch (Throwable $e) {
            Log::warning('Telegram alert failed: '.$e->getMessage());

            return false;
        }
    }

    /** Offsite copy of a backup file (Telegram bots accept documents up to 50 MB). */
    public function telegramFile(string $path, string $caption): bool
    {
        if (! $this->telegramConfigured() || ! is_file($path) || filesize($path) > 49 * 1024 * 1024) {
            return false;
        }
        try {
            return Http::timeout(120)->attach('document', fopen($path, 'r'), basename($path))
                ->post('https://api.telegram.org/bot'.config('sangatv.telegram.bot_token').'/sendDocument', [
                    'chat_id' => config('sangatv.telegram.chat_id'),
                    'caption' => mb_substr($caption, 0, 1000),
                ])->successful();
        } catch (Throwable $e) {
            Log::warning('Telegram backup upload failed: '.$e->getMessage());

            return false;
        }
    }

    private function email(string $subject, string $body): bool
    {
        if (! $this->emailConfigured()) {
            return false;
        }
        try {
            Mail::raw($body, fn ($m) => $m->to($this->emailRecipients())->subject($subject));

            return true;
        } catch (Throwable $e) {
            Log::warning('Alert e-mail failed: '.$e->getMessage());

            return false;
        }
    }
}
