<?php

declare(strict_types=1);

namespace Survos\MercadoLibre\Exception;

/**
 * A non-2xx response.
 *
 * Mercado Libre's error envelope is `{message, error, status, cause: [...]}` --
 * nothing like eBay's `{errors: [...]}`. The useful detail is almost always in
 * `cause`, which names the offending attribute when a listing is rejected.
 */
class MercadoLibreApiException extends \RuntimeException implements MercadoLibreException
{
    /** @param list<string> $causes */
    public function __construct(
        public readonly int $statusCode,
        public readonly ?string $errorCode = null,
        public readonly array $causes = [],
        public readonly ?string $rawBody = null,
        string $message = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            self::summarize($statusCode, $errorCode, $message, $causes, $rawBody),
            $statusCode,
            $previous,
        );
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(int $statusCode, array $payload, ?string $rawBody = null): self
    {
        $causes = [];
        foreach ((array) ($payload['cause'] ?? []) as $cause) {
            if (is_string($cause)) {
                $causes[] = $cause;
            } elseif (is_array($cause)) {
                // cause entries are {code, message} or {department, cause_id, ...}
                $causes[] = trim(sprintf(
                    '%s %s',
                    (string) ($cause['code'] ?? $cause['cause_id'] ?? ''),
                    (string) ($cause['message'] ?? ''),
                ));
            }
        }

        $message = isset($payload['message']) ? (string) $payload['message'] : '';
        $error = isset($payload['error']) ? (string) $payload['error'] : null;

        return match (true) {
            $statusCode === 401, $statusCode === 403 => new MercadoLibreAuthenticationException(
                $statusCode, $error, $causes, $rawBody, $message,
            ),
            $statusCode === 429 => new MercadoLibreRateLimitException(
                $statusCode, $error, $causes, $rawBody, $message,
            ),
            default => new self($statusCode, $error, $causes, $rawBody, $message),
        };
    }

    /**
     * The `cause` entries are the useful part -- they name the offending attribute.
     * They are always included, never replaced by the generic top-level `message`.
     *
     * @param list<string> $causes
     */
    private static function summarize(
        int $status,
        ?string $error,
        string $message,
        array $causes,
        ?string $rawBody,
    ): string {
        $text = sprintf('Mercado Libre returned HTTP %d%s', $status, $error !== null ? ' (' . $error . ')' : '');

        if ($message !== '') {
            $text .= ': ' . $message;
        }

        if ($causes !== []) {
            return $text . "\n" . implode("\n", array_map(static fn (string $c): string => '  ' . $c, $causes));
        }

        if ($message !== '') {
            return $text;
        }

        return $rawBody !== null && $rawBody !== ''
            ? $text . '. Body: ' . mb_substr($rawBody, 0, 300)
            : $text . '.';
    }
}
