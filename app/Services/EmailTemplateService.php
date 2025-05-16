<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class EmailTemplateService
{
    /**
     * Replaces placeholders and processes conditional blocks in a template string.
     *
     * Placeholder syntax: [[ variable_name ]]
     * Conditional syntax:
     *   [[ IF variable_name == 'some_string' ]]...[[ ENDIF ]]
     *   [[ IF variable_name != 'some_string' ]]...[[ ENDIF ]]
     *   [[ IF variable_name == 123 ]]...[[ ENDIF ]]
     *   [[ IF variable_name != 123 ]]...[[ ENDIF ]]
     *   [[ IF variable_name ]]...[[ ENDIF ]] (checks for truthiness)
     *
     * String literals in conditions can contain escaped quotes (e.g., 'O\'Malley').
     * Nested conditionals and placeholders are supported.
     *
     * @param string $template The template string containing placeholders.
     * @param array $data Associative array of data (key = placeholder name, value = replacement value).
     * @return string The processed template string.
     */
    public function replacePlaceholders(string $template, array $data): string
    {
        // First, process all conditional blocks.
        // The callbacks for conditionals will recursively call this method for their content,
        // ensuring nested structures and placeholders within resolved blocks are processed.
        $template = $this->processConditionalLogic($template, $data);

        // After conditionals are resolved, process any remaining simple placeholders.
        $template = $this->processSimplePlaceholders($template, $data);

        return $template;
    }

    /**
     * Processes conditional blocks like [[ IF ... ]] ... [[ ENDIF ]].
     *
     * @param string $template
     * @param array $data
     * @return string
     */
    private function processConditionalLogic(string $template, array $data): string
    {
        // Process conditions with string literals: [[ IF variable == 'value' ]] or [[ IF variable != 'value' ]]
        // Regex handles escaped single quotes in the string literal (e.g., 'O\'Malley').
        $template = preg_replace_callback(
            '/\[\[\s*IF\s+([a-zA-Z0-9_]+)\s*(==|!=)\s*\'((?:\\\\\'|[^\'])*)\'\s*\]\](.*?)\[\[\s*ENDIF\s*\]\]/s',
            function ($matches) use ($data) {
                $key = $matches[1];
                $operator = $matches[2];
                $valueToCompare = stripslashes($matches[3]); // Handle escaped quotes
                $content = $matches[4];

                $conditionMet = false;
                if (array_key_exists($key, $data)) {
                    if ($operator === '==') {
                        $conditionMet = ((string)$data[$key] === $valueToCompare);
                    } elseif ($operator === '!=') {
                        $conditionMet = ((string)$data[$key] !== $valueToCompare);
                    }
                } elseif ($operator === '!=') { // Key not set, so it's '!=' to any specific string value
                    $conditionMet = true;
                }

                return $conditionMet ? $this->replacePlaceholders($content, $data) : '';
            },
            $template
        );

        // Process conditions with numeric literals: [[ IF variable == 123 ]] or [[ IF variable != 123 ]]
        $template = preg_replace_callback(
            '/\[\[\s*IF\s+([a-zA-Z0-9_]+)\s*(==|!=)\s*([0-9]+(?:\.[0-9]+)?)\s*\]\](.*?)\[\[\s*ENDIF\s*\]\]/s',
            function ($matches) use ($data) {
                $key = $matches[1];
                $operator = $matches[2];
                $numericValueToCompare = $matches[3];
                $content = $matches[4];

                $conditionMet = false;
                if (array_key_exists($key, $data) && is_numeric($data[$key])) {
                    if ($operator === '==') {
                        $conditionMet = ((float)$data[$key] == (float)$numericValueToCompare);
                    } elseif ($operator === '!=') {
                        $conditionMet = ((float)$data[$key] != (float)$numericValueToCompare);
                    }
                } elseif ($operator === '!=') { // Key not set or not numeric, considered '!=' to a number
                    $conditionMet = true;
                }

                return $conditionMet ? $this->replacePlaceholders($content, $data) : '';
            },
            $template
        );

        // Process truthiness check: [[ IF variable ]]
        $template = preg_replace_callback(
            '/\[\[\s*IF\s+([a-zA-Z0-9_]+)\s*\]\](.*?)\[\[\s*ENDIF\s*\]\]/s',
            function ($matches) use ($data) {
            $key = $matches[1];
                $content = $matches[2];
                return !empty($data[$key]) ? $this->replacePlaceholders($content, $data) : '';
            },
            $template
        );

        return $template;
    }

    /**
     * Replaces simple placeholders like [[ variable_name ]].
     *
     * @param string $template
     * @param array $data
     * @return string
     */
    private function processSimplePlaceholders(string $template, array $data): string
    {
        return preg_replace_callback(
            '/\[\[\s*([a-zA-Z0-9_]+)\s*\]\]/',
            function ($matches) use ($data) {
                $key = $matches[1];
                if (array_key_exists($key, $data)) {
                    return (string) $data[$key]; // Cast to string to handle null or other types
                }
                return $matches[0]; // Return original placeholder if key not found
            },
            $template
        );
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
        if (!config('app.email_tracking_enabled', true)) {
             return $body;
        }
        Log::info("Embedding tracking pixel for Batch ID: {$batchId} Recipient: {$recipientIdentifier}");
        $trackingUrl = Route::url('tracking.open', [
            'batchId' => $batchId,
            'recipientIdentifier' => $recipientIdentifier
        ]);

        // Append the pixel - ensure it's valid HTML
        $pixelHtml = '<img src="' . htmlspecialchars($trackingUrl) . '" width="1" height="1" alt="" style="display:none;"/>';
        Log::info("Tracking pixel HTML: {$pixelHtml}");
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
