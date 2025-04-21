<?php

namespace App\Http\Controllers;

use App\Models\EmailBatch; // Optional: To verify batch exists
use App\Models\EmailTrackingEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log; // For logging errors
use Illuminate\Support\Facades\Response; // For creating custom responses
use Illuminate\Support\Str; // For URL decoding helper
use Throwable; // For catching exceptions

class TrackingController extends Controller
{
    // Transparent 1x1 pixel GIF
    protected const TRACKING_PIXEL = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    /**
     * Tracks an email open event.
     * Logs the event and returns a transparent pixel image.
     *
     * @param Request $request
     * @param int $batchId
     * @param string $recipientIdentifier Base64 URL-safe encoded identifier
     * @return \Illuminate\Http\Response
     */
    public function trackOpen(Request $request, int $batchId, string $encodedRecipientIdentifier)
    {
        try {
             // Basic validation: Check if tracking is globally enabled
             if (!config('app.email_tracking_enabled', true)) {
                 return $this->pixelResponse();
             }

            // Decode recipient identifier if needed (ensure it was encoded safely)
            // Example assumes base64 URL-safe encoding without padding
            $recipientIdentifier = $this->safeBase64UrlDecode($encodedRecipientIdentifier);
            if (!$recipientIdentifier) {
                 Log::warning("Tracking open: Invalid recipient identifier received.", ['encoded' => $encodedRecipientIdentifier]);
                 return $this->pixelResponse(); // Return pixel even on error to avoid broken images
            }


            // Optional: Verify the EmailBatch exists and belongs to a user if needed
            // $batch = EmailBatch::find($batchId);
            // if (!$batch || !$batch->tracking_enabled) {
            //     return $this->pixelResponse();
            // }

            EmailTrackingEvent::create([
                'email_batch_id' => $batchId,
                'recipient_identifier' => $recipientIdentifier,
                'type' => 'open',
                'tracked_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'link_url' => null, // Not applicable for open
            ]);

        } catch (Throwable $e) {
            // Log the error but still return the pixel to avoid broken images in emails
            Log::error("Error tracking email open: " . $e->getMessage(), [
                'batch_id' => $batchId,
                'recipient_identifier' => $recipientIdentifier ?? $encodedRecipientIdentifier, // Log decoded or encoded
                'exception' => $e
            ]);
        }

        // Always return the transparent pixel response
        return $this->pixelResponse();
    }

    /**
     * Tracks an email link click event.
     * Logs the event and redirects the user to the original URL.
     *
     * @param Request $request
     * @param int $batchId
     * @param string $recipientIdentifier Base64 URL-safe encoded identifier
     * @param string $encodedUrl Base64 URL-safe encoded original URL
     * @return \Illuminate\Http\RedirectResponse
     */
    public function trackClick(Request $request, int $batchId, string $encodedRecipientIdentifier, string $encodedUrl)
    {
        $originalUrl = null; // Initialize
        $recipientIdentifier = null;

        try {
             // Basic validation: Check if tracking is globally enabled
             if (!config('app.email_tracking_enabled', true)) {
                 // Decode URL to redirect anyway, just don't track
                 $originalUrl = $this->safeBase64UrlDecode($encodedUrl);
                 return redirect()->away($originalUrl ?: '/'); // Redirect to decoded URL or fallback
             }

            // Decode identifiers and URL
            $recipientIdentifier = $this->safeBase64UrlDecode($encodedRecipientIdentifier);
            $originalUrl = $this->safeBase64UrlDecode($encodedUrl);

            // Validate decoded data
            if (!$recipientIdentifier) {
                 Log::warning("Tracking click: Invalid recipient identifier received.", ['encoded' => $encodedRecipientIdentifier]);
                 // Still try to redirect if URL is valid
                 return redirect()->away($originalUrl ?: '/');
            }
            if (!$originalUrl || !filter_var($originalUrl, FILTER_VALIDATE_URL)) {
                Log::error("Tracking click: Invalid original URL received or failed validation.", ['encoded' => $encodedUrl, 'decoded' => $originalUrl]);
                // Redirect to a safe fallback page
                return redirect('/'); // Or a dedicated error page
            }


            // Optional: Verify the EmailBatch exists and belongs to a user if needed
            // $batch = EmailBatch::find($batchId);
            // if (!$batch || !$batch->tracking_enabled) {
            //     // Don't log, just redirect
            //     return redirect()->away($originalUrl);
            // }

            EmailTrackingEvent::create([
                'email_batch_id' => $batchId,
                'recipient_identifier' => $recipientIdentifier,
                'type' => 'click',
                'tracked_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'link_url' => $originalUrl, // Store the decoded original URL
            ]);

            // Redirect to the original URL
            return redirect()->away($originalUrl);

        } catch (Throwable $e) {
            Log::error("Error tracking email click: " . $e->getMessage(), [
                'batch_id' => $batchId,
                 'recipient_identifier' => $recipientIdentifier ?? $encodedRecipientIdentifier,
                'encoded_url' => $encodedUrl,
                'original_url' => $originalUrl, // Include decoded URL if available
                'exception' => $e
            ]);

            // If something goes wrong, still try to redirect the user gracefully
            return redirect()->away($originalUrl ?: '/'); // Redirect to original URL if decoded, else fallback
        }
    }

    /**
     * Helper function to return the transparent pixel response.
     *
     * @return \Illuminate\Http\Response
     */
    protected function pixelResponse(): \Illuminate\Http\Response
    {
        $pixelData = base64_decode(self::TRACKING_PIXEL);
        return Response::make($pixelData, 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-cache, no-store, must-revalidate, private',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
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
