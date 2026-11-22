<?php

namespace App\Services;

use App\Models\Inquiry;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramNotificationService
{
    /**
     * Envía una notificación instantánea a un grupo o canal de Telegram del equipo de ventas.
     */
    public function notifyNewInquiry(Inquiry $inquiry): bool
    {
        $token = Setting::get('telegram_bot_token') ?: config('services.telegram.bot_token', env('TELEGRAM_BOT_TOKEN'));
        $chatId = Setting::get('telegram_chat_id') ?: config('services.telegram.chat_id', env('TELEGRAM_CHAT_ID'));

        if (! $token || ! $chatId) {
            return false;
        }

        try {
            $adminUrl = url("/admin/inquiries/{$inquiry->id}");
            $total = $inquiry->total_estimated ? 'Bs '.number_format((float) $inquiry->total_estimated, 2) : 'A cotizar';

            $message = "🛍️ *¡Nuevo Pedido / Consulta Web!*\n\n";
            $message .= "• *Pedido #:* {$inquiry->id}\n";
            $message .= '• *Cliente:* '.$this->escapeMarkdown($inquiry->customer_name ?? 'Consumidor')."\n";
            $message .= '• *Teléfono:* '.$this->escapeMarkdown($inquiry->customer_phone ?? '—')."\n";
            $message .= "• *Total estimado:* {$total}\n";
            $message .= '• *Origen:* '.ucfirst($inquiry->source ?? 'web')."\n";

            if ($inquiry->items()->exists()) {
                $count = $inquiry->items()->count();
                $message .= "• *Productos:* {$count} ítem(s)\n";
            }

            $message .= "\n🔗 [Ver en Panel Administrativo]({$adminUrl})";

            $response = Http::timeout(5)
                ->asJson()
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $message,
                    'parse_mode' => 'Markdown',
                    'disable_web_page_preview' => false,
                ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('No se pudo enviar notificación de Telegram: '.$e->getMessage());

            return false;
        }
    }

    private function escapeMarkdown(string $text): string
    {
        // Escapar caracteres reservados de Markdown v1
        return str_replace(['_', '*', '`', '['], ['\\_', '\\*', '\\`', '\\['], $text);
    }
}
