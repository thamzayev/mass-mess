<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;

class RateLimitService
{
    /**
     * Returns an array of suggested rate limits for common email providers.
     * Loads data from config/email_providers.php.
     *
     * @return array Associative array (Provider Name => Suggested Limit).
     */
    public function getSuggestedRateLimits(): array
    {
        return Config::get('email_providers.providers', []); // Return empty array if config not found
    }

    /**
     * Gets the suggested rate limit for a specific email provider.
     *
     * @param string $providerName The name of the email provider.
     * @return string|null The suggested rate limit string or null if not found.
     */
    public function getRateLimitForProvider(string $providerName): ?string
    {
        $limits = $this->getSuggestedRateLimits();
        return $limits[$providerName] ?? null;
    }
}
