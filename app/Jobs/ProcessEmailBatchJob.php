<?php

namespace App\Jobs;

use App\Models\EmailBatch;
use App\Services\CsvProcessingService;
use Illuminate\Bus\Batchable; // Use Batchable trait
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus; // Bus facade for nested batching
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessEmailBatchJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $batchData;

    public function __construct(array $batchData)
    {
        $this->batchData = $batchData;
    }

    public function handle(CsvProcessingService $csvService): void
    {
        $emailBatchId = $this->batchData['email_batch_id'];
        $emailBatch = EmailBatch::find($emailBatchId);

        if (!$emailBatch) {
            Log::error("ProcessEmailBatchJob: EmailBatch not found for ID {$emailBatchId}");
            return;
        }

        // Prevent processing if already completed or failed drastically
        if (in_array($emailBatch->status, ['sent', 'failed'])) {
             Log::warning("ProcessEmailBatchJob: Batch {$emailBatchId} already processed or failed.");
             return;
        }

         $emailBatch->update(['status' => 'generating']); // Or 'sending'

        try {
            $jobs = [];
            $records = $csvService->getRecords($emailBatch->csv_file_path, 'private'); // Use correct disk

            foreach ($records as $index => $record) {
                // Assuming $record is an associative array [header => value]
                // Add recipient identifier (e.g., email or unique ID from CSV)
                $recipientIdentifier = $record['email'] ?? 'row_' . ($index + 1); // Use email if exists, else row number

                 $jobs[] = new SendEmailJob($emailBatch->id, $record, $recipientIdentifier);
            }

             if (!empty($jobs)) {
                 // Create a Laravel Batch to manage the SendEmailJob instances
                 Bus::batch($jobs)
                     ->then(function (\Illuminate\Bus\Batch $batch) use ($emailBatch) {
                         // All jobs completed successfully
                          EmailBatch::find($emailBatch->id)?->update(['status' => 'sent']);
                     })
                     ->catch(function (\Illuminate\Bus\Batch $batch, Throwable $e) use ($emailBatch) {
                         // First batch job failure detected
                         Log::error("Email sending batch failed for Batch ID {$emailBatch->id}. Error: " . $e->getMessage());
                          EmailBatch::find($emailBatch->id)?->update(['status' => 'failed']);
                     })
                     ->finally(function (\Illuminate\Bus\Batch $batch) use ($emailBatch) {
                         // The batch has finished (even if cancelled or failed partially)
                         // Could update counts here if SendEmailJob doesn't do it reliably
                         $batchModel = EmailBatch::find($emailBatch->id);
                         if ($batchModel && $batchModel->status !== 'failed') { // Avoid overriding failed status
                            if ($batch->finished() && !$batch->failed()) {
                                 $batchModel->update(['status' => 'sent']);
                            }
                            // Update counts based on job results? Requires more complex tracking.
                         }

                     })
                     ->name('SendEmails-BatchID-' . $emailBatch->id) // Optional name for Horizon/Telescope
                     ->dispatch();

                  $emailBatch->update(['status' => 'sending']); // Update status after dispatching
             } else {
                 Log::warning("ProcessEmailBatchJob: No records found to process for Batch ID {$emailBatchId}.");
                 $emailBatch->update(['status' => 'failed']); // Mark as failed if no records
             }

        } catch (Throwable $e) {
            Log::error("ProcessEmailBatchJob failed for Batch ID {$emailBatchId}: " . $e->getMessage());
             $emailBatch->update(['status' => 'failed']);
             // Rethrow if needed by queue runner
             throw $e;
        }
    }
}
