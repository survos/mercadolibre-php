<?php

declare(strict_types=1);

namespace Survos\MercadoLibre\Auth;

interface AccessTokenProviderInterface
{
    /** @throws \Survos\MercadoLibre\Exception\MercadoLibreAuthenticationException */
    public function accessToken(): string;

    /** Seller id, needed by every seller-scoped call. Only ever arrives with a token. */
    public function userId(): ?int;
}
