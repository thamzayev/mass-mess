<?php

namespace App\Jobs;

use App\Models\Email;
use App\Models\EmailBatch;
use App\Services\EmailTemplateService;
use App\Services\PdfGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateBatchEmailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1200;
    public $failOnTimeout = true;

    protected EmailBatch $batch;

    public function __construct(EmailBatch $batch)
    {
        $this->batch = $batch->withoutRelations();
    }

    public function handle(EmailTemplateService $templateService, PdfGeneratorService $pdfService): void
    {
        $batch = EmailBatch::with(['personalizedAttachments', 'user'])->findOrFail($this->batch->id);

        Log::info("Starting email generation for Batch ID: {$batch->id} User ID: {$batch->user_id}");

        if ($batch->status !== 'generating') {
            $batch->update(['status' => 'generating', 'generated_count' => 0, 'failed_count' => 0]);
        } else {
             $batch->update(['generated_count' => 0, 'failed_count' => 0]);
             Email::where('email_batch_id', $batch->id)->delete();
        }

        $dataRows = $this->batch->data_rows ?? [];
        $dataRows = json_decode($dataRows, true);
        $staticAttachments = $this->batch->attachment_paths ?? [];
        $personalizedAttachmentsConfig = $batch->personalizedAttachments;
        $totalRows = count($dataRows);
        $generatedCount = 0;
        $failedCount = 0;

        foreach ($dataRows as $index => $row) {
            try {
                $row['_index'] = $index;
                $row['_email_batch_id'] = $batch->id;

                $toAddress = $templateService->replacePlaceholders($batch->email_to, $row);
                $ccAddress = $batch->email_cc ? $templateService->replacePlaceholders($batch->email_cc, $row) : null;
                $bccAddress = $batch->email_bcc ? $templateService->replacePlaceholders($batch->email_bcc, $row) : null;
                $subject = $templateService->replacePlaceholders($batch->email_subject, $row);
                $body = $templateService->replacePlaceholders($batch->email_body, $row);

                if($batch->tracking_enabled){
                    $body = $templateService->embedTrackingPixel($body, $batch->id, $toAddress); // Embed tracking pixel
                }
                Log::info($body);
                $currentAttachments = [];

                if ($this->batch->has_personalized_attachments && $personalizedAttachmentsConfig->isNotEmpty()) {
                    foreach ($personalizedAttachmentsConfig as $config) {
                        $compiledFilename = $templateService->replacePlaceholders($config->filename, $row);
                        $safeFilename = preg_replace('/[^A-Za-z0-9\.\-\_]/', '_', $compiledFilename);
                        if (!str_ends_with(strtolower($safeFilename), '.pdf')) {
                            $safeFilename .= '.pdf';
                        }

                        $headerHtml = $config->header ? $templateService->replacePlaceholders($config->header, $row) : '';
                        $templateBody = $templateService->replacePlaceholders($config->template, $row);
                        $footerHtml = $config->footer ? $templateService->replacePlaceholders($config->footer, $row) : '';

                        $htmlContent = $pdfService->generateHtmlContent($templateBody, $row, $headerHtml, $footerHtml);
                        $pdfContent = $pdfService->generatePdfContent($htmlContent);

                        $pdfPath = "personalized-attachments/batch_{$batch->id}/row_{$index}/{$safeFilename}";

                        Storage::disk('private')->put($pdfPath, $pdfContent);

                        $currentAttachments[] = $pdfPath;
                    }
                }

                $currentAttachments = array_merge($currentAttachments, $staticAttachments);


                Email::create([
                    'email_batch_id' => $batch->id,
                    'user_id' => $batch->user_id,
                    'to_address' => $toAddress,
                    'cc_address' => $ccAddress,
                    'bcc_address' => $bccAddress,
                    'subject' => $subject,
                    'body' => $body,
                    'attachments' => $currentAttachments,
                    'status' => 'pending',
                    'sent_at' => null,
                    'error_message' => null,
                ]);

                $generatedCount++;

            } catch (Throwable $e) {
                $failedCount++;
                Log::error("Failed to generate email for row {$index} in Batch ID: {$batch->id}. Error: " . $e->getMessage(), [
                    'exception' => $e,
                    'row_data' => $row
                ]);
                 Email::create([
                    'email_batch_id' => $batch->id,
                    'user_id' => $batch->user_id,
                    'to_address' => $templateService->replacePlaceholders($this->batch->email_to, $row, true), // Attempt placeholder replacement safely
                    'subject' => $templateService->replacePlaceholders($this->batch->email_subject, $row, true),
                    'body' => 'Error during generation.',
                    'status' => 'failed',
                    'error_message' => 'Generation failed: ' . $e->getMessage(),
                 ]);
            }

            if ($index % 10 === 0 || $index === $totalRows - 1) {
                 $batch->update([
                     'generated_count' => $generatedCount,
                     'failed_count' => $failedCount,
                 ]);
            }
        }

        $finalStatus = ($failedCount === 0) ? 'generated' : 'failed';
        if ($generatedCount === 0 && $failedCount > 0) {
            $finalStatus = 'failed';
        }

        $batch->update([
            'status' => $finalStatus,
            'generated_count' => $generatedCount,
            'failed_count' => $failedCount,
        ]);

        Log::info("Finished email generation for Batch ID: {$batch->id}. Generated: {$generatedCount}, Failed: {$failedCount}. Final Status: {$finalStatus}");

        $batch->user->notify(
            new \App\Notifications\BatchGenerationCompleted($this->batch, $generatedCount, $failedCount)
        );
    }

    public function failed(Throwable $exception): void
    {
         Log::error("GenerateBatchEmailsJob failed for Batch ID: {$this->batch->id}. Error: {$exception->getMessage()}");
         $batch = EmailBatch::find($this->batch->id);
         if ($batch) {
             $batch->loadMissing('user');
             $batch->update([
                 'status' => 'failed',
             ]);

             $batch->user?->notify(new \App\Notifications\BatchGenerationFailed($batch, $exception->getMessage()));
         }
     }
}
