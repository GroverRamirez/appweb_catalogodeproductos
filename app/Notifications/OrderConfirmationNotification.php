<?php

namespace App\Notifications;

use App\Models\Inquiry;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Notificación de confirmación de pedido dirigida al cliente comprador.
 *
 * Se despacha a la cola 'notifications' para no bloquear el retorno
 * del checkout en el navegador web.
 */
class OrderConfirmationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 120];

    public int $timeout = 60;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Inquiry $inquiry)
    {
        $this->onQueue('notifications');
        $this->afterCommit = true;
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inq = $this->inquiry;
        $inq->loadMissing('items');

        $storeName = (string) Setting::get('store_name', config('app.name'));
        $currency = (string) Setting::get('currency_symbol', 'Bs');

        $mail = (new MailMessage)
            ->subject("¡Confirmación de tu pedido #{$inq->id}! — {$storeName}")
            ->greeting("¡Hola, {$inq->customer_name}!")
            ->line("Gracias por tu compra en **{$storeName}**. Hemos recibido tu solicitud con el número **#{$inq->id}**.")
            ->line('### Resumen de tu compra:');

        foreach ($inq->items as $item) {
            $unitFormatted = number_format((float) $item->unit_price, 2, ',', '.');
            $lineTotal = number_format((float) $item->unit_price * $item->quantity, 2, ',', '.');
            $code = $item->product_code_snapshot ? " ({$item->product_code_snapshot})" : '';
            $mail->line("• **{$item->quantity}x** {$item->product_name_snapshot}{$code} — {$currency} {$lineTotal} ({$currency} {$unitFormatted} c/u)");
        }

        if ($inq->discount_amount && (float) $inq->discount_amount > 0) {
            $discountFormatted = number_format((float) $inq->discount_amount, 2, ',', '.');
            $couponText = $inq->coupon_code ? " (Cupón: {$inq->coupon_code})" : '';
            $mail->line("**Descuento aplicado{$couponText}:** -{$currency} {$discountFormatted}");
        }

        if ($inq->total_estimated) {
            $totalFormatted = number_format((float) $inq->total_estimated, 2, ',', '.');
            $mail->line("**Total a pagar:** {$currency} {$totalFormatted}");
        }

        $bankEnabled = (bool) Setting::get('bank_qr_enabled', true);
        $bankName = (string) Setting::get('bank_name', '');
        $bankHolder = (string) Setting::get('bank_account_holder', '');
        $bankNumber = (string) Setting::get('bank_account_number', '');

        if ($bankEnabled && ($bankName || $bankNumber)) {
            $mail->line('---')
                ->line('### Datos para tu pago / transferencia:')
                ->line("**Banco:** {$bankName}")
                ->line("**Titular:** {$bankHolder}")
                ->line("**Nº de Cuenta:** `{$bankNumber}`")
                ->line("**Concepto / Glosa:** Pedido #{$inq->id}");
        }

        if ($inq->public_token) {
            $thanksUrl = URL::signedRoute('cart.thanks', ['inquiry' => $inq->public_token]);
            $mail->action('Ver mi pedido y subir comprobante', $thanksUrl);
        }

        $mail->line('Una vez realizado tu pago, puedes subir tu comprobante directamente en el enlace anterior o avisarnos por WhatsApp para procesar tu entrega a la brevedad.')
            ->salutation("Saludos cordiales,\nEquipo de {$storeName}");

        return $mail;
    }
}
