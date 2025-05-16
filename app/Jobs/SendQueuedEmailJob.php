<?php

namespace App\Jobs;

use App\Models\Email;
use App\Models\SmtpConfiguration;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Mail\Message;
use Throwable;

class SendQueuedEmailJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120; // Timeout for sending a single email
    public $failOnTimeout = true;
    public $tries = 3; // Retry sending 3 times if it fails
    public $backoff = [60, 120]; // Delay 60s, then 120s between retries

    protected int $emailId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $emailId)
    {
        $this->emailId = $emailId;
    }

    /**
     * Execute the job.
     * @throws Throwable
     */
    public function handle(): void
    {
        // Check if the batch this job belongs to has been cancelled
        if ($this->batch() && $this->batch()->cancelled()) {
            Log::info("SendQueuedEmailJob cancelled for Email ID: {$this->emailId} due to batch cancellation.");
            return;
        }

        $email = Email::with(['email_batch.smtpConfiguration'])->find($this->emailId);

        if (!$email) {
            Log::error("SendQueuedEmailJob: Email record not found.", ['email_id' => $this->emailId]);
            $this->fail(new \Exception("Email record not found for ID: {$this->emailId}."));
            return;
        }

        // Only attempt to send if status is 'pending' (or maybe 'failed' if retrying)
        if (!in_array($email->status, ['pending', 'failed'])) {
            Log::info("SendQueuedEmailJob: Email ID {$this->emailId} is not in 'pending' or 'failed' status. Current status: {$email->status}. Skipping.");
            return; // Don't fail the job, just skip if already sent/processed
       }

       if (!$email->email_batch || !$email->email_batch->smtpConfiguration) {
            Log::error("SendQueuedEmailJob: EmailBatch or SmtpConfiguration missing for Email ID: {$this->emailId}.", ['email_id' => $this->emailId]);
            $email->update(['status' => 'failed', 'error_message' => 'Batch or SMTP configuration missing.']);
            $this->fail(new \Exception("EmailBatch or SmtpConfiguration missing for Email ID: {$this->emailId}."));
            return;
        }


        $smtpConfig = $email->email_batch->smtpConfiguration;

        // --- Configure Dynamic Mailer ---
        $mailerName = 'dynamic_smtp_' . $this->job->getJobId();
        $mailConfig = [
            'transport' => 'smtp',
            'host' => $smtpConfig->host,
            'port' => $smtpConfig->port,
            'encryption' => $smtpConfig->encryption,
            'username' => $smtpConfig->username,
            'password' => $smtpConfig->password,
            'timeout' => 60, // Mailer timeout
            'local_domain' => config('mail.mailers.smtp.local_domain'),
        ];
        Config::set("mail.mailers.{$mailerName}", $mailConfig);
        Config::set("mail.from.address", $smtpConfig->from_address);
        Config::set("mail.from.name", $smtpConfig->from_name ?? config('app.name'));

        try {
            Mail::mailer($mailerName)->send([], [], function (Message $message) use ($email, $smtpConfig) {
                $message->to($email->to_address);
                if ($email->cc_address) {
                    $message->cc(explode(',', $email->cc_address)); // Assuming comma-separated
                }
                if ($email->bcc_address) {
                    $message->bcc(explode(',', $email->bcc_address)); // Assuming comma-separated
                }
                $message->subject($email->subject);
                $message->html($email->body); // Use html() for HTML content

                // Attach files
                if (!empty($email->attachments)) {
                    foreach ($email->attachments as $attachmentPath) {
                        if (Storage::disk('private')->exists($attachmentPath)) {
                            $message->attachData(
                                Storage::disk('private')->get($attachmentPath),
                                basename($attachmentPath)
                                // Consider storing/detecting mime type if needed
                            );
                        } else {
                            Log::warning("SendQueuedEmailJob: Attachment not found.", ['email_id' => $this->emailId, 'path' => $attachmentPath]);
                        }
                    }
                }
            });

            // Update email status on success
            $email->update(['status' => 'sent', 'sent_at' => now(), 'error_message' => null]);
            Log::info("SendQueuedEmailJob: Email sent successfully.", ['email_id' => $this->emailId, 'to' => $email->to_address]);

        } catch (Throwable $e) {
            Log::error("SendQueuedEmailJob: Failed to send email.", ['email_id' => $this->emailId, 'to' => $email->to_address, 'error' => $e->getMessage()]);
            // Update status and error message
            $email->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            // Re-throw the exception to mark the job as failed within the batch
            throw $e;
        } finally {
            // Clean up dynamic mailer config
            Config::set("mail.mailers.{$mailerName}", $mailConfig);
            Config::set("mail.from.address", $smtpConfig->from_address);
            Config::set("mail.from.name", $smtpConfig->from_name ?? config('app.name'));
        }
    }

    /**
     * Handle a job failure after all retries.
     */
    public function failed(Throwable $exception): void
    {
        Log::error("SendQueuedEmailJob ultimately failed after retries for Email ID: {$this->emailId}. Error: {$exception->getMessage()}");
        // The status should already be 'failed' from the handle method's catch block,
        // but we can ensure it here just in case.
        $email = Email::find($this->emailId);
        if ($email && $email->status !== 'sent') { // Avoid overwriting if somehow sent on a later try but still failed? Unlikely.
            $email->update(['status' => 'failed', 'error_message' => $exception->getMessage()]);
        }
        // The batch processor will automatically count this as a failed job.
    }
}
