<?php

namespace App\Notifications;

use App\Models\EmailBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class BatchGenerationFailed extends Notification implements ShouldQueue // Optional: Queue the notification itself
{
    use Queueable;

    protected EmailBatch $batch;
    protected string $errorMessage;

    /**
     * Create a new notification instance.
     */
    public function __construct(EmailBatch $batch, string $errorMessage)
    {
        $this->batch = $batch;
        $this->errorMessage = $errorMessage;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Only use the database channel
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $title = "Batch #{$this->batch->id} Generation Failed";
        $body = "The email generation job failed for Batch #{$this->batch->id}. ";
        $body .= "Error: " . substr($this->errorMessage, 0, 200) . (strlen($this->errorMessage) > 200 ? '...' : ''); // Truncate long errors

        return [
            'title' => $title,
            'body' => $body,
            'email_batch_id' => $this->batch->id,
            'error_message' => $this->errorMessage, // Store full error message if needed
            // Filament specific keys
            'icon' => 'heroicon-o-x-circle',
            'iconColor' => 'danger',
            // Optional: Add an action URL to view the batch
            'actions' => [
                [
                    'label' => 'View Batch',
                    'url' => \App\Filament\Resources\EmailBatchResource::getUrl('view', ['record' => $this->batch->id]),
                    'shouldOpenInNewTab' => true,
                ],
            ],
        ];
    }

    /**
     * Get the mail representation of the notification.
     * (Not used)
     */
    // public function toMail(object $notifiable): MailMessage
    // {
    //     return (new MailMessage)
    //                 ->line('The introduction to the notification.')
    //                 ->action('Notification Action', url('/'))
    //                 ->line('Thank you for using our application!');
    // }
}
