<?php

namespace App\Jobs;

use App\Models\Email;
use App\Models\EmailBatch;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendGeneratedEmailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // Allow 1 hour for batch dispatching and monitoring
    public $failOnTimeout = true;

    protected int $batchId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $batchId)
    {
        $this->batchId = $batchId; // Avoid serializing large relations
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $batchModel = EmailBatch::findOrFail($this->batchId);

        Log::info("Starting SendGeneratedEmailsJob for Batch ID: {$batchModel->id}");

        // Ensure the batch is in a state ready for sending
        if ($batchModel->status !== 'generated' && $batchModel->status !== 'sending') { // Allow restarting 'sending' state
            Log::warning("SendGeneratedEmailsJob: Batch ID {$batchModel->id} is not in 'generated' or 'sending' status. Current status: {$batchModel->status}. Aborting.");
            // Optionally update status back or fail
            // $batchModel->update(['status' => 'failed']); // Or revert to 'generated'
            return;
        }

        // Update status to 'sending' if it's not already
        if ($batchModel->status !== 'sending') {
            $batchModel->update(['status' => 'sending', 'sent_count' => 0, 'failed_count' => 0]); // Reset counts for sending phase
        }

        // Find pending emails for this batch
        $pendingEmails = Email::where('email_batch_id', $batchModel->id)
                              ->where('status', 'pending') // Only select emails not yet processed
                              ->get();

        if ($pendingEmails->isEmpty()) {
            Log::info("SendGeneratedEmailsJob: No pending emails found to send for Batch ID: {$batchModel->id}.");
            // Update status to 'sent' if generated count matches total (or handle edge cases)
            $batchModel->update(['status' => 'sent']); // Assuming all were generated and none are pending
            return;
        }
        // Prepare individual sending jobs
        $jobs = $pendingEmails->map(function (Email $email) {
            // *** IMPORTANT: Replace SendQueuedEmailJob with your actual single email sending job ***
            return new SendQueuedEmailJob($email->id);
        })->all();

        $batchId = $this->batchId;
        Log::info("Dispatching email sending batch for Batch ID: {$batchId}:", $jobs);
        // Dispatch the batch
        $batchJob = Bus::batch($jobs)
        ->then(function (Batch $batch) use ($batchId) { // <-- Use batchId
            Log::info("Email sending batch completed for Batch ID: {$batchId}. Total Jobs: {$batch->totalJobs}, Failed Jobs: {$batch->failedJobs}");
            // All jobs completed successfully...
            $emailBatch = EmailBatch::find($batchId);
            if ($emailBatch) {
                    $emailBatch->update(['status' => 'sent', 'sent_count' => $batch->totalJobs - $batch->failedJobs, 'failed_count' => $batch->failedJobs]);
                    Log::info("Email sending batch completed successfully for Batch ID: {$emailBatch->id}");
                    // Optionally notify user
                }
            })->catch(function (Batch $batch, Throwable $e) use ($batchId) { // <-- Use batchId
                // First batch job failure detected...
                $emailBatch = EmailBatch::find($batchId);
                if ($emailBatch) {
                    $emailBatch->update(['status' => 'failed']); // Or 'partially_failed'
                    Log::error("Email sending batch failed for Batch ID: {$emailBatch->id}. Error: " . $e->getMessage());
                    // Optionally notify user
                }
            })->finally(function (Batch $batch) use ($batchId) { // <-- Use batchId
                // The batch has finished executing...
                $emailBatch = EmailBatch::find($batchId);
                if ($emailBatch && $emailBatch->status === 'sending') { // Check if status wasn't already set by then/catch
                    // Update counts and determine final status if not already 'sent' or 'failed'
                    $finalStatus = $batch->failedJobs > 0 ? 'failed' : 'sent'; // Simplified logic
                    $emailBatch->update(['status' => $finalStatus, 'sent_count' => $batch->totalJobs - $batch->failedJobs, 'failed_count' => $batch->failedJobs]);
                    Log::info("Email sending batch finished for Batch ID: {$emailBatch->id}. Status: {$finalStatus}");
                }
            })
            ->name('Send Emails Batch ID: ' . $batchModel->id) // Optional: Name the batch for Horizon/Telescope
            ->allowFailures() // Allow the batch to finish even if some jobs fail
            ->dispatch();

        Log::info("Dispatched email sending batch ({$batchJob->id}) for Batch ID: {$batchModel->id} with {$pendingEmails->count()} jobs.");
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error("SendGeneratedEmailsJob itself failed for Batch ID: {$this->batchId}. Error: {$exception->getMessage()}");
        $batchModel = EmailBatch::find($this->batchId);

        if ($batchModel && $batchModel->status === 'sending') { // Only update if it was in 'sending' state
            $batchModel->update(['status' => 'failed']);
            // Optionally notify user
        }
    }
}
