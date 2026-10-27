<?php declare(strict_types=1);

namespace Svea\Checkout\Model\Client;

/**
 * Removes personal data and secrets from Svea request and response data before it is logged.
 *
 * Logs are downloadable from the admin and often shipped elsewhere, so they must not contain
 * customer details, recurring tokens or the quote hash in the callback URLs.
 */
class LogRedactor
{
    public const REDACTED = '[redacted]';

    /**
     * Keys whose values are replaced entirely, matched case-insensitively against Svea's field names
     * (EmailAddress, PhoneNumber, NationalId, BillingAddress, ShippingAddress, Customer, PresetValues,
     * RecurringToken, ...).
     */
    private const SENSITIVE_KEY_PATTERN =
        '/address|email|phone|nationalid|dateofbirth|fullname|firstname|lastname|^customer$|presetvalues|token/i';

    /**
     * URL path segments that carry a secret: /hash/{quote hash}, /token/{recurring token} in callback
     * URLs and /tokens/{recurring token} in token API endpoints.
     */
    private const SECRET_PATH_PATTERN = '#(/(?:hash|token|tokens)/)[^/"?&\s\\\\]+#i';

    /**
     * @param array|string|null $data JSON string, plain text or array
     * @return string
     */
    public function redact($data): string
    {
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            if (!is_array($decoded)) {
                return $this->redactUrls($data);
            }
            $data = $decoded;
        }

        if (!is_array($data)) {
            return $this->redactUrls((string)json_encode($data));
        }

        return $this->redactUrls((string)json_encode($this->redactArray($data), JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param string $text
     * @return string
     */
    public function redactUrls(string $text): string
    {
        return (string)preg_replace(self::SECRET_PATH_PATTERN, '$1' . self::REDACTED, $text);
    }

    /**
     * @param array $data
     * @return array
     */
    private function redactArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match(self::SENSITIVE_KEY_PATTERN, $key)) {
                $data[$key] = self::REDACTED;
            } elseif (is_array($value)) {
                $data[$key] = $this->redactArray($value);
            }
        }

        return $data;
    }
}
