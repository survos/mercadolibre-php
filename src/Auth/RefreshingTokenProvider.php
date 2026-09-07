<?php

declare(strict_types=1);

namespace Survos\MercadoLibre\Auth;

use Survos\MercadoLibre\Exception\MercadoLibreAuthenticationException;

/**
 * Refreshes the access token when it nears expiry and hands the replacement to the
 * caller's store.
 *
 * PERSIST SYNCHRONOUSLY. Mercado Libre refresh tokens are single use: the moment
 * a refresh succeeds, the token you spent is dead and the only valid one is the
 * one that just came back. If the process dies between the API issuing it and your
 * store committing it, the account is permanently unauthorized and the seller must
 * re-consent. There is no recovery.
 *
 * That is why $persist is called BEFORE the new token is used, and why a persist
 * failure is allowed to propagate rather than being swallowed -- a token in memory
 * that never reached disk is worse than a visible error.
 */
final class RefreshingTokenProvider implements AccessTokenProviderInterface
{
    /** @var callable(): ?MercadoLibreToken */
    private $load;

    /** @var callable(MercadoLibreToken): void */
    private $persist;

    private ?MercadoLibreToken $cached = null;

    /**
     * @param callable(): ?MercadoLibreToken     $load
     * @param callable(MercadoLibreToken): void  $persist
     */
    public function __construct(
        private readonly OAuthService $oauth,
        callable $load,
        callable $persist,
        private readonly int $leewaySeconds = 60,
    ) {
        $this->load = $load;
        $this->persist = $persist;
    }

    public function accessToken(): string
    {
        return $this->current()->accessToken;
    }

    public function userId(): ?int
    {
        return $this->current()->userId;
    }

    private function current(): MercadoLibreToken
    {
        $token = $this->cached ??= ($this->load)();

        if ($token === null) {
            throw new MercadoLibreAuthenticationException(
                401,
                null,
                [],
                null,
                'No stored Mercado Libre token. Send the seller through OAuthService::consentUrl() first.',
            );
        }

        if (!$token->isExpired(leewaySeconds: $this->leewaySeconds)) {
            return $token;
        }

        if (!$token->canRefresh()) {
            throw new MercadoLibreAuthenticationException(
                401,
                null,
                [],
                null,
                'The Mercado Libre access token expired and there is no refresh token. Re-consent required.',
            );
        }

        $refreshed = $this->oauth->refresh($token);

        // Persist BEFORE use and BEFORE caching: the previous refresh token is
        // already dead, so losing this one loses the account.
        ($this->persist)($refreshed);
        $this->cached = $refreshed;

        return $refreshed;
    }
}
