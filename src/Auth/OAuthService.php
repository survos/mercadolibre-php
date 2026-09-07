<?php

declare(strict_types=1);

namespace Survos\MercadoLibre\Auth;

use Survos\MercadoLibre\Exception\MercadoLibreApiException;
use Survos\MercadoLibre\MercadoLibreSite;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Mercado Libre's OAuth 2.0 authorization-code flow.
 *
 * There is no client_credentials grant worth using here: seller operations all
 * require a user token. Public reads (category trees, domain discovery) need no
 * token at all.
 */
final readonly class OAuthService
{
    public const string API_HOST = 'https://api.mercadolibre.com';

    public function __construct(
        private HttpClientInterface $httpClient,
        private MercadoLibreCredentials $credentials,
        private MercadoLibreSite $site = MercadoLibreSite::Mexico,
    ) {
    }

    /**
     * Where to send a seller to grant access.
     *
     * The host is per country -- see {@see MercadoLibreSite::authHost()}. A Mexican
     * seller sent to the Argentine host gets a login page that will never authorize
     * your application, with no error to explain it.
     */
    public function consentUrl(?string $state = null): string
    {
        if ($this->credentials->redirectUri === null) {
            throw new \LogicException(
                'consentUrl() needs the redirect URI, and it must match one registered on the '
                . 'application exactly -- Mercado Libre compares the full string.',
            );
        }

        $query = [
            'response_type' => 'code',
            'client_id' => $this->credentials->clientId,
            'redirect_uri' => $this->credentials->redirectUri,
        ];
        if ($state !== null) {
            $query['state'] = $state;
        }

        return $this->site->authHost() . '/authorization?' . http_build_query($query);
    }

    /** Exchange the `code` from your redirect. Single use, and short-lived. */
    public function exchangeCode(string $code): MercadoLibreToken
    {
        return MercadoLibreToken::fromResponse($this->token([
            'grant_type' => 'authorization_code',
            'client_id' => $this->credentials->clientId,
            'client_secret' => $this->credentials->clientSecret,
            'code' => $code,
            'redirect_uri' => (string) $this->credentials->redirectUri,
        ]));
    }

    /**
     * Spend the refresh token for a new pair.
     *
     * The refresh token passed in is dead the moment this returns. Persist the
     * replacement before using it -- see {@see RefreshingTokenProvider}.
     */
    public function refresh(MercadoLibreToken $token): MercadoLibreToken
    {
        if ($token->refreshToken === null) {
            throw new \LogicException('Cannot refresh a token that has no refresh token.');
        }

        return $token->refreshed($this->token([
            'grant_type' => 'refresh_token',
            'client_id' => $this->credentials->clientId,
            'client_secret' => $this->credentials->clientSecret,
            'refresh_token' => $token->refreshToken,
        ]));
    }

    /**
     * @param array<string, string> $body
     *
     * @return array<string, mixed>
     */
    private function token(array $body): array
    {
        $response = $this->httpClient->request('POST', self::API_HOST . '/oauth/token', [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => $body,
        ]);

        $status = $response->getStatusCode();
        $raw = $response->getContent(throw: false);
        $payload = json_decode($raw, true);
        $payload = is_array($payload) ? $payload : [];

        if ($status >= 400) {
            throw MercadoLibreApiException::fromPayload($status, $payload, $raw);
        }

        return $payload;
    }
}
