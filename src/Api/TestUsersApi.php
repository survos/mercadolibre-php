<?php

declare(strict_types=1);

namespace Survos\MercadoLibre\Api;

use Survos\MercadoLibre\Http\MercadoLibreTransportInterface;
use Survos\MercadoLibre\MercadoLibreSite;

/**
 * Test users -- Mercado Libre's substitute for a sandbox.
 *
 * There is no sandbox. These are REAL accounts on the REAL API; they simply do not
 * move real money or accrue real reputation. Consequences worth knowing before you
 * call this:
 *
 *  - Ten per application, ever. They cannot be deleted, by you or by Mercado Libre.
 *  - Test listings must be titled "Test item - Do not offer" (in the site's
 *    language) so they are identifiable.
 *  - Everything else behaves normally, including publishing, which is the point.
 *
 * Because the quota is small and permanent, this is not something to call from a
 * test suite. Create the users once, by hand, and store the credentials.
 */
final readonly class TestUsersApi
{
    public function __construct(private MercadoLibreTransportInterface $transport)
    {
    }

    /**
     * @return array<string, mixed> id, nickname, password, site_id, email
     */
    public function create(MercadoLibreSite $site): array
    {
        /** @var array<string, mixed> $response */
        $response = $this->transport->request('POST', '/users/test_user', body: ['site_id' => $site->value]);

        return $response;
    }
}
