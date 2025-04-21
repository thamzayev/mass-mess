<?php

namespace App\Services;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class EmailTemplateService
{
    /**
     * Replaces placeholders in a template string with data from an array.
     * Example placeholder: {{ $column_name }} or {{ $data['column_name'] }}
     * Adjust the regex and replacement logic based on your chosen syntax.
     *
     * @param string $template The template string containing placeholders.
     * @param array $data Associative array of data (key = placeholder name, value = replacement value).
     * @return string The processed template string.
     */
    public function replacePlaceholders(string $template, array $data): string
    {
        // Basic example using {{ $column_name }} syntax
        // Warning: This simple regex might conflict with other syntaxes (like Blade).
        // Consider a more robust approach or a different syntax like {{ data.column_name }}
        // if using a template engine, or {{ $__data['column_name'] }}
        return preg_replace_callback('/{{\s*\$([a-zA-Z0-9_]+)\s*}}/', function ($matches) use ($data) {
            $key = $matches[1];
            return $data[$key] ?? $matches[0]; // Return original placeholder if key not found
        }, $template);

        // Alternative using {{ $__data['Column Header'] }} syntax (safer)
        /*
        return preg_replace_callback('/{{\s*\$__data\[\'(.*?)\'\]\s*}}/', function ($matches) use ($data) {
            $key = $matches[1];
            // Handle potential differences in key casing if needed
            // $foundKey = collect($data)->keys()->first(fn($k) => strtolower($k) === strtolower($key));
            // return $foundKey ? $data[$foundKey] : $matches[0];
            return $data[$key] ?? $matches[0]; // Return original placeholder if key not found
        }, $template);
        */
    }

    /**
     * Embeds a tracking pixel image tag into the email body.
     *
     * @param string $body The original email body HTML.
     * @param int $batchId The ID of the EmailBatch.
     * @param string $recipientIdentifier Unique identifier for the recipient.
     * @return string The email body HTML with the tracking pixel appended.
     */
    public function embedTrackingPixel(string $body, int $batchId, string $recipientIdentifier): string
    {
        if (!config('app.email_tracking_enabled', true)) { // Allow disabling tracking via config
             return $body;
        }

        $trackingUrl = Route::url('tracking.open', [
            'batchId' => $batchId,
            'recipientIdentifier' => $recipientIdentifier // Ensure this is URL-safe
        ]);

        // Append the pixel - ensure it's valid HTML
        $pixelHtml = '<img src="' . htmlspecialchars($trackingUrl) . '" width="1" height="1" alt="" style="display:none;"/>';

        // Append just before the closing </body> tag if possible, otherwise at the end
        if (str_contains($body, '</body>')) {
            return str_replace('</body>', $pixelHtml . '</body>', $body);
        } else {
            return $body . $pixelHtml;
        }
    }

    /**
     * Rewrites links in the email body to point to the click tracking route.
     *
     * @param string $body The original email body HTML.
     * @param int $batchId The ID of the EmailBatch.
     * @param string $recipientIdentifier Unique identifier for the recipient.
     * @return string The email body HTML with links rewritten.
     */
    public function rewriteLinks(string $body, int $batchId, string $recipientIdentifier): string
    {
         if (!config('app.email_tracking_enabled', true)) { // Allow disabling tracking via config
             return $body;
         }

        // Basic regex to find href attributes in <a> tags. Might need refinement for complex HTML.
        return preg_replace_callback('/<a\s+(?:[^>]*?\s+)?href="([^"]*)"/i', function ($matches) use ($batchId, $recipientIdentifier) {
            $originalUrl = $matches[1];

            // Avoid rewriting mailto links, anchor links, or already tracked links
            if (Str::startsWith($originalUrl, ['mailto:', '#']) || Str::contains($originalUrl, route('tracking.click', ['','',''], false))) {
                return $matches[0]; // Return the original match
            }

            // Encode the original URL safely
            $encodedUrl = rtrim(strtr(base64_encode($originalUrl), '+/', '-_'), '=');

            $trackingUrl = Route::url('tracking.click', [
                'batchId' => $batchId,
                'recipientIdentifier' => $recipientIdentifier, // Ensure this is URL-safe
                'encodedUrl' => $encodedUrl
            ]);

            // Replace the original href with the tracking URL
            return str_replace($originalUrl, htmlspecialchars($trackingUrl), $matches[0]);

        }, $body);
    }
}
