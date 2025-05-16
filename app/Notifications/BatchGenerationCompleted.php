<?php

namespace App\Notifications;

use App\Models\EmailBatch; // Import EmailBatch
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class BatchGenerationCompleted extends Notification implements ShouldQueue // Optional: Queue the notification itself
{
    use Queueable;

    protected EmailBatch $batch;
    protected int $generatedCount;
    protected int $failedCount;

    /**
     * Create a new notification instance.
     */
    public function __construct(EmailBatch $batch, int $generatedCount, int $failedCount)
    {
        $this->batch = $batch;
        $this->generatedCount = $generatedCount;
        $this->failedCount = $failedCount;
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
        $title = "Batch #{$this->batch->id} Generation Complete";
        $body = "Email generation finished for Batch #{$this->batch->id}. ";
        $body .= "Successfully generated: {$this->generatedCount}. ";
        $body .= "Failed: {$this->failedCount}.";

        // Determine icon and color based on success/failure
        $icon = ($this->failedCount === 0) ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle';
        $color = ($this->failedCount === 0) ? 'success' : 'warning'; // Use warning if some failed, danger if all failed?

        return [
            'title' => $title,
            'body' => $body,
            'email_batch_id' => $this->batch->id,
            'generated_count' => $this->generatedCount,
            'failed_count' => $this->failedCount,
            'status' => $this->batch->status, // Include final status
            // Filament specific keys for better display
            'icon' => $icon,
            'iconColor' => $color,
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
     * (Not used since we only specify 'database' in via(), but kept for completeness)
     */
    // public function toMail(object $notifiable): MailMessage
    // {
    //     return (new MailMessage)
    //                 ->line('The introduction to the notification.')
    //                 ->action('Notification Action', url('/'))
    //                 ->line('Thank you for using our application!');
    // }
}
