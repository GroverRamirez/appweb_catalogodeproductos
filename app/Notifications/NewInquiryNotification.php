<?php

namespace App\Notifications;

use App\Models\Inquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notificación al admin cuando llega una nueva consulta/pedido.
 *
 * Implementa ShouldQueue para enviar el email de forma asíncrona:
 * el request del cliente retorna de inmediato y el worker de cola
 * se encarga del envío en segundo plano.
 *
 * Requisitos en producción:
 *   - QUEUE_CONNECTION=database (o redis) en .env
 *   - Tabla `jobs` migrada (incluida en la migración inicial)
 *   - Worker corriendo: php artisan queue:work --queue=notifications,default
 *
 * En tests (QUEUE_CONNECTION=sync) el envío ocurre de forma síncrona,
 * por lo que Notification::fake() y sus assertions siguen funcionando sin cambios.
 */
class NewInquiryNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Número máximo de intentos antes de mover el job a failed_jobs.
     * 3 intentos cubre fallas transitorias del servidor SMTP.
     */
    public int $tries = 3;

    /**
     * Segundos de espera entre reintentos (backoff exponencial manual).
     * Primer reintento a los 30 s, segundo a los 2 min.
     *
     * @var array<int, int>
     */
    public array $backoff = [30, 120];

    /**
     * Máximo de segundos para considerar el job como colgado.
     * El envío de email raramente toma más de 30 s; 60 s da margen holgado.
     */
    public int $timeout = 60;

    /**
     * Desechar el job si el modelo Inquiry ya no existe en la DB
     * (poco probable, pero evita errores innecesarios en failed_jobs).
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public Inquiry $inquiry)
    {
        // $queue y $afterCommit se asignan aquí (no como propiedades tipadas)
        // porque el trait Queueable ya las declara sin tipo y redeclararlas
        // tipadas provoca un fatal de composición en PHP 8.3.
        //
        // Cola dedicada para procesar los emails con prioridad separada, y
        // afterCommit para no leer un Inquiry que aún no hizo commit cuando
        // notifyAdmin() se invoca dentro de una transacción.
        $this->onQueue('notifications');
        $this->afterCommit = true;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inq = $this->inquiry;
        // loadMissing es seguro en el worker: el modelo fue re-hidratado desde la DB.
        $inq->loadMissing('items');

        $mail = (new MailMessage)
            ->subject("Nueva consulta #{$inq->id} — {$inq->customer_name}")
            ->greeting('¡Hola!')
            ->line('Recibiste una nueva consulta en el catálogo.')
            ->line("**Cliente:** {$inq->customer_name}")
            ->line("**Teléfono:** {$inq->customer_phone}");

        if ($inq->customer_email) {
            $mail->line("**Email:** {$inq->customer_email}");
        }

        $mail->line("**Origen:** {$inq->source}");

        if ($inq->message) {
            $mail->line('**Mensaje:**')->line($inq->message);
        }

        if ($inq->items->isNotEmpty()) {
            $mail->line('**Productos solicitados:**');
            foreach ($inq->items as $item) {
                $mail->line("• {$item->quantity}x {$item->product_name_snapshot} ({$item->product_code_snapshot})");
            }
        }

        if ($inq->total_estimated) {
            $mail->line('**Total estimado:** '.number_format((float) $inq->total_estimated, 2));
        }

        $mail->action('Ver en el panel', url("/admin/inquiries/{$inq->id}"));

        return $mail;
    }
}
