<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Redacts secrets and PII from log records before they hit disk.
 *
 * Test Gauntlet G-5 H-2. Two layers:
 *  - context keys whose NAME implies a secret (password, token, api_key,
 *    authorization, secret, …) have their VALUE replaced with [REDACTED];
 *  - message + remaining string values are scanned for secret-shaped TOKENS
 *    (sk_/pk_/wsk_/xai-/sk-ant- prefixes, Bearer headers) and those tokens
 *    are masked in place.
 *
 * Deliberately conservative: it redacts by key name and by token shape, not
 * by trying to parse arbitrary formats. Better to over-redact a log line than
 * to leak a live key.
 */
final class RedactionProcessor implements ProcessorInterface
{
    /** Context/extra keys whose value is always replaced wholesale. */
    private const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'current_password', 'new_password',
        'secret', 'token', 'api_key', 'apikey', 'access_token', 'refresh_token',
        'authorization', 'auth', 'bearer', 'webhook_secret', 'client_secret',
        'private_key', 'card_number', 'cvv', 'cvc', 'plainTextToken',
    ];

    /** Secret-shaped token patterns masked anywhere they appear in strings. */
    private const TOKEN_PATTERNS = [
        '/\b(sk|pk|wsk)_[A-Za-z0-9_]{8,}/',  // Revolut / Stripe-style keys (sk_live_…)
        '/\bxai-[A-Za-z0-9]{8,}/',            // xAI keys
        '/\bsk-ant-[A-Za-z0-9\-]{8,}/',       // Anthropic keys
        '/\bBearer\s+[A-Za-z0-9\-\._~\+\/]{12,}=*/i', // Authorization: Bearer …
        '/\b[0-9]+\|[A-Za-z0-9]{30,}/',       // Sanctum plain-text tokens (id|hash)
    ];

    private const MASK = '[REDACTED]';

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record
            ->with(message: $this->maskString($record->message))
            ->with(context: $this->redactArray($record->context))
            ->with(extra: $this->redactArray($record->extra));
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private function redactArray(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
                $out[$key] = self::MASK;

                continue;
            }
            $out[$key] = match (true) {
                is_array($value) => $this->redactArray($value),
                is_string($value) => $this->maskString($value),
                default => $value,
            };
        }

        return $out;
    }

    private function maskString(string $value): string
    {
        return preg_replace(self::TOKEN_PATTERNS, self::MASK, $value) ?? $value;
    }
}
