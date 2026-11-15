<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class Notifier
{
    /** @param array<string, scalar|null> $fields */
    public static function admin(string $title, array $fields): void
    {
        $fields = array_filter($fields, fn ($value) => $value !== null && $value !== '');
        $lines = [$title];
        foreach ($fields as $label => $value) {
            $lines[] = $label.': '.$value;
        }
        $text = implode("\n", $lines);

        self::mail(config('handmade.admin_mail_to', []), $title, $text);
        self::telegram($text);
    }

    public static function customer(string $email, string $subject, array $fields): void
    {
        $lines = [$subject];
        foreach ($fields as $label => $value) {
            if ($value !== null && $value !== '') {
                $lines[] = $label.': '.$value;
            }
        }

        self::mail([$email], $subject, implode("\n", $lines));
    }

    private static function mail(array $to, string $subject, string $text): void
    {
        if (! $to) {
            return;
        }

        try {
            Mail::raw($text, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });
        } catch (Throwable $e) {
            Log::warning('Не удалось отправить письмо: '.$e->getMessage());
        }
    }

    private static function telegram(string $text): void
    {
        $token = (string) config('handmade.telegram_bot_token');
        $chats = config('handmade.telegram_chat_id', []);
        if ($token === '' || ! $chats) {
            return;
        }

        foreach ($chats as $chatId) {
            try {
                Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'disable_web_page_preview' => true,
                ]);
            } catch (Throwable $e) {
                Log::warning('Не удалось отправить сообщение в Telegram: '.$e->getMessage());
            }
        }
    }
}
