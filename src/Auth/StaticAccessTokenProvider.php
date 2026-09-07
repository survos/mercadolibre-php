<?php

declare(strict_types=1);

namespace Survos\MercadoLibre\Auth;

/** A fixed token. Fine for a script or a test; Mercado Libre tokens last 6 hours. */
final readonly class StaticAccessTokenProvider implements AccessTokenProviderInterface
{
    public function __construct(
        private string $token,
        private ?int $userId = null,
    ) {
    }

    public function accessToken(): string
    {
        return $this->token;
    }

    public function userId(): ?int
    {
        return $this->userId;
    }
}
