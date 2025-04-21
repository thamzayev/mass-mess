<?php

namespace App\Jobs;

use App\Models\EmailBatch;
use App\Models\PersonalizedAttachment;
use App\Models\SmtpConfiguration;
use App\Services\EmailTemplateService;
use App\Services\PdfGeneratorService;
use Illuminate\Bus\Batchable; // <-- Import Batchable
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique; // Optional: if you need unique jobs
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config; // For dynamic mailer config
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail; // Mail facade
use Illuminate\Support\Facades\Storage; // For accessing attachments
use Illuminate\Mail\Message; // For raw email sending
use Throwable; // To catch all exceptions/errors

class SendEmailJob implements ShouldQueue
{
    // Use Batchable to allow this job to be part of a Laravel Batch
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $emailBatchId;
    public array $recipientData; // Associative array [header => value] from CSV row
    public string $recipientIdentifier; // Unique identifier (e.g., email or generated ID)

    /**
     * Create a new job instance.
     *
     * @param int $emailBatchId
     * @param array $recipientData
     * @param string $recipientIdentifier
     */
    public function __construct(int $emailBatchId, array $recipientData, string $recipientIdentifier)
    {
        $this->emailBatchId = $emailBatchId;
        $this->recipientData = $recipientData;
        $this->recipientIdentifier = $this->safeBase64UrlEncode($recipientIdentifier); // Encode identifier for tracking URLs
    }

    /**
     * Execute the job.
     *
     * @param EmailTemplateService $templateService
     * @param PdfGeneratorService $pdfGeneratorService
     * @return void
     * @throws Throwable // Allow exceptions to be caught by the batch processor
     */
    public function handle(EmailTemplateService $templateService, PdfGeneratorService $pdfGeneratorService): void
    {
        // Check if the batch has been cancelled before processing
        if ($this->batch() && $this->batch()->cancelled()) {
            Log::info("SendEmailJob cancelled for Batch ID: {$this->emailBatchId}, Recipient: {$this->recipientIdentifier}");
            return;
        }

        // --- 1. Retrieve Batch and SMTP Configuration ---
        $emailBatch = EmailBatch::with('smtpConfiguration')->find($this->emailBatchId);

        if (!$emailBatch || !$emailBatch->smtpConfiguration) {
            Log::error("SendEmailJob: EmailBatch or SmtpConfiguration not found.", ['batch_id' => $this->emailBatchId]);
            $this->fail(new \Exception("EmailBatch or SmtpConfiguration not found for Batch ID: {$this->emailBatchId}.")); // Fail the job
            return;
        }

        $smtpConfig = $emailBatch->smtpConfiguration;
        $recipientEmail = $this->recipientData['email'] ?? null; // Assuming 'email' column exists

        if (!$recipientEmail || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            Log::warning("SendEmailJob: Invalid or missing recipient email.", ['batch_id' => $this->emailBatchId, 'data' => $this->recipientData]);
            // We don't fail the job here, but let the batch continue. Failure count handled by ProcessEmailBatchJob/batch callbacks if desired.
            // Or, explicitly fail if every row *must* have a valid email:
            // $this->fail(new \InvalidArgumentException("Invalid or missing email for recipient in Batch ID: {$this->emailBatchId}."));
            return; // Skip this recipient
        }


        // --- 2. Prepare Email Content ---
        $emailSubject = $templateService->replacePlaceholders($emailBatch->email_title_template, $this->recipientData);
        $emailBodyHtml = $templateService->replacePlaceholders($emailBatch->email_body_template, $this->recipientData);

        // --- 3. Add Tracking (if enabled) ---
        if ($emailBatch->tracking_enabled) {
            $emailBodyHtml = $templateService->embedTrackingPixel($emailBodyHtml, $this->emailBatchId, $this->recipientIdentifier);
            $emailBodyHtml = $templateService->rewriteLinks($emailBodyHtml, $this->emailBatchId, $this->recipientIdentifier);
        }

        // --- 4. Generate Personalized Attachment (if enabled) ---
        $personalizedAttachmentPath = null;
        $personalizedAttachmentName = null;
        if ($emailBatch->has_personalized_attachments && !empty($emailBatch->personalized_attachment_template)) {
            try {
                $personalizedAttachmentPath = $pdfGeneratorService->generatePdfFromTemplate(
                    $emailBatch->personalized_attachment_template,
                    $this->recipientData,
                    $this->emailBatchId,
                    $this->recipientIdentifier // Use encoded identifier for filename safety
                );
                // Create DB record for the attachment
                 $decodedRecipientIdentifier = $this->safeBase64UrlDecode($this->recipientIdentifier);
                 PersonalizedAttachment::create([
                     'email_batch_id' => $this->emailBatchId,
                     'recipient_identifier' => $decodedRecipientIdentifier, // Store decoded ID
                     'file_path' => $personalizedAttachmentPath, // Relative path within storage disk
                     'original_name' => basename($personalizedAttachmentPath), // Or generate a nicer name
                 ]);
                $personalizedAttachmentName = basename($personalizedAttachmentPath);

            } catch (Throwable $e) {
                Log::error("SendEmailJob: Failed to generate personalized attachment.", [
                    'batch_id' => $this->emailBatchId,
                    'recipient' => $recipientEmail,
                    'error' => $e->getMessage()
                ]);
                // Decide if job should fail if PDF generation fails, or just send without it
                // For now, we'll let it continue without the attachment, but log the error.
                // To fail the job instead: throw $e;
                $personalizedAttachmentPath = null; // Ensure it's null if failed
            }
        }

        // --- 5. Configure Dynamic Mailer ---
        $mailerName = 'dynamic_smtp_' . $this->job->getJobId(); // Unique name per job
        $mailConfig = [
            'transport' => 'smtp',
            'host' => $smtpConfig->host,
            'port' => $smtpConfig->port,
            'encryption' => $smtpConfig->encryption, // 'tls', 'ssl', or null
            'username' => $smtpConfig->username,
            'password' => $smtpConfig->password, // Assuming stored securely and accessible
            'timeout' => null,
            'local_domain' => config('mail.mailers.smtp.local_domain'), // Optional: Use default or configure per user
        ];
        Config::set("mail.mailers.{$mailerName}", $mailConfig);
        Config::set("mail.from.address", $smtpConfig->from_address); // Set default From for this mailer instance
        Config::set("mail.from.name", $smtpConfig->from_name ?? config('app.name'));


        // --- 6. Send Email ---
        try {
            Mail::mailer($mailerName)->html($emailBodyHtml, function (Message $message) use ($recipientEmail, $emailSubject, $smtpConfig, $emailBatch, $personalizedAttachmentPath, $personalizedAttachmentName) {
                // Set From using the config set above, or override here if needed
                // $message->from($smtpConfig->from_address, $smtpConfig->from_name);
                $message->to($recipientEmail);
                $message->subject($emailSubject);

                // Attach Static Files
                if (!empty($emailBatch->attachment_paths)) {
                    foreach ($emailBatch->attachment_paths as $attachmentInfo) {
                         // Ensure $attachmentInfo['path'] exists and is accessible
                         $filePath = $attachmentInfo['path'] ?? null;
                         if ($filePath && Storage::disk('private')->exists($filePath)) { // Check existence on correct disk
                             $message->attachData(
                                 Storage::disk('private')->get($filePath),
                                 basename($filePath) // Use original filename stored during upload if available
                                  // You might need to store mime type during upload too
                             );
                         } else {
                             Log::warning("SendEmailJob: Static attachment not found or path missing.", ['batch_id' => $this->emailBatchId, 'path_info' => $attachmentInfo]);
                         }
                    }
                }

                // Attach Personalized File
                if ($personalizedAttachmentPath && Storage::disk('local')->exists($personalizedAttachmentPath)) { // Check on correct disk
                      $message->attachData(
                          Storage::disk('local')->get($personalizedAttachmentPath),
                          $personalizedAttachmentName ?? 'personalized_attachment.pdf',
                          ['mime' => 'application/pdf']
                      );
                }
            });

            // --- 7. Update Counts (Handled by Batch Callbacks) ---
            // If you need real-time counts (less robust with concurrency):
            // $emailBatch->increment('sent_count');

            Log::info("SendEmailJob: Email sent successfully.", ['batch_id' => $this->emailBatchId, 'recipient' => $recipientEmail]);

        } catch (Throwable $e) {
             // --- 8. Handle Failures ---
            Log::error("SendEmailJob: Failed to send email.", [
                'batch_id' => $this->emailBatchId,
                'recipient' => $recipientEmail,
                'error' => $e->getMessage(),
                // 'trace' => $e->getTraceAsString() // Optional: for detailed debugging
            ]);

             // If you need real-time counts (less robust with concurrency):
             // $emailBatch->increment('failed_count');

             // Let the batch processor know this job failed by throwing the exception
             throw $e;
        } finally {
             // --- 9. Cleanup ---
             // Remove temporary personalized PDF if stored locally and no longer needed
             if ($personalizedAttachmentPath && Storage::disk('local')->exists($personalizedAttachmentPath)) {
                // Storage::disk('local')->delete($personalizedAttachmentPath); // Uncomment if desired
             }
             // Forget the dynamic mailer config to prevent memory leaks
             Config::forget("mail.mailers.{$mailerName}");
        }
    }


     /**
      * Safely encodes a string to URL-safe base64.
      *
      * @param string $string
      * @return string
      */
     protected function safeBase64UrlEncode(string $string): string
     {
         return rtrim(strtr(base64_encode($string), '+/', '-_'), '=');
     }

     /**
      * Safely decodes a URL-safe base64 encoded string.
      * Handles potential padding issues.
      *
      * @param string $data
      * @return string|false
      */
     protected function safeBase64UrlDecode(string $data)
     {
         $decoded = base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT), true);
         return $decoded; // Returns false on failure
     }
}
