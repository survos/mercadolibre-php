<?php

declare(strict_types=1);

namespace Survos\MercadoLibre\Auth;

/**
 * One Mercado Libre application.
 *
 * Unlike eBay there is no sandbox keyset -- there is no sandbox. These credentials
 * are production credentials, and testing happens with test users created against
 * the live API.
 */
final readonly class MercadoLibreCredentials
{
    public function __construct(
        public string $clientId,
        public string $clientSecret,
        /** Must match a redirect URI registered on the application, exactly. */
        public ?string $redirectUri = null,
    ) {
    }
}
