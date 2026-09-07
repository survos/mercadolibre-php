<?php

declare(strict_types=1);

namespace Survos\MercadoLibre\Auth;

/**
 * A Mercado Libre OAuth token pair.
 *
 * THE THING TO GET RIGHT: Mercado Libre refresh tokens are **single use**. Every
 * refresh returns a NEW refresh token and invalidates the one you just spent. Only
 * the most recently issued token is accepted.
 *
 * This is the opposite of eBay, whose refresh token is stable for ~18 months and
 * whose refresh responses usually omit it entirely. Code written against eBay --
 * "keep the refresh token you already have, it does not change" -- passes every
 * eBay test and then permanently locks a Mercado Libre account out on its first
 * refresh, surfacing as an authorization error that points nowhere near storage.
 *
 * So {@see refreshed()} deliberately does NOT fall back to the previous refresh
 * token. If a response somehow carries none, the result has none, and the failure
 * is loud and immediate rather than a token that looks valid and is not.
 *
 * Access tokens last 6 hours; refresh tokens 6 months.
 */
final readonly class MercadoLibreToken
{
    /** @param list<string> $scopes */
    public function __construct(
        public string $accessToken,
        public \DateTimeImmutable $expiresAt,
        public ?string $refreshToken = null,
        public ?int $userId = null,
        public array $scopes = [],
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromResponse(array $payload, ?\DateTimeImmutable $now = null): self
    {
        $now ??= new \DateTimeImmutable();

        return new self(
            accessToken: (string) ($payload['access_token'] ?? ''),
            expiresAt: $now->modify(sprintf('+%d seconds', (int) ($payload['expires_in'] ?? 0))),
            refreshToken: isset($payload['refresh_token']) ? (string) $payload['refresh_token'] : null,
            // Every seller-scoped call needs this; it only ever arrives here.
            userId: isset($payload['user_id']) ? (int) $payload['user_id'] : null,
            scopes: isset($payload['scope']) && is_string($payload['scope'])
                ? array_values(array_filter(explode(' ', $payload['scope'])))
                : [],
        );
    }

    public function isExpired(?\DateTimeImmutable $now = null, int $leewaySeconds = 60): bool
    {
        $now ??= new \DateTimeImmutable();

        return $this->expiresAt <= $now->modify(sprintf('+%d seconds', $leewaySeconds));
    }

    public function canRefresh(): bool
    {
        return $this->refreshToken !== null;
    }

    /**
     * The successor token. The new refresh token REPLACES the old one -- no
     * fallback, because the old one is already dead on Mercado Libre's side.
     *
     * @param array<string, mixed> $payload
     */
    public function refreshed(array $payload, ?\DateTimeImmutable $now = null): self
    {
        $next = self::fromResponse($payload, $now);

        return new self(
            accessToken: $next->accessToken,
            expiresAt: $next->expiresAt,
            refreshToken: $next->refreshToken,
            userId: $next->userId ?? $this->userId,
            scopes: $next->scopes !== [] ? $next->scopes : $this->scopes,
        );
    }
}
