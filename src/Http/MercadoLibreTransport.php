<?php

declare(strict_types=1);

namespace Survos\MercadoLibre\Http;

use Survos\MercadoLibre\Auth\AccessTokenProviderInterface;
use Survos\MercadoLibre\Exception\MercadoLibreApiException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * One host, no environments.
 *
 * Mercado Libre has NO sandbox. Every call here hits production. Testing is done
 * with test users created through {@see \Survos\MercadoLibre\Api\TestUsersApi},
 * which are real accounts on the real API that simply do not move real money.
 */
final readonly class MercadoLibreTransport implements MercadoLibreTransportInterface
{
    public const string API_HOST = 'https://api.mercadolibre.com';

    public function __construct(
        private HttpClientInterface $httpClient,
        private ?AccessTokenProviderInterface $tokenProvider = null,
    ) {
    }

    public function request(string $method, string $path, array $query = [], ?array $body = null): array
    {
        if ($this->tokenProvider === null) {
            throw new \LogicException(sprintf(
                '%s %s needs a seller token, but this transport was built without a token provider. '
                . 'Use requestAnonymous() for public endpoints.',
                $method,
                $path,
            ));
        }

        return $this->send($method, $path, $query, $body, $this->tokenProvider->accessToken());
    }

    public function requestAnonymous(string $method, string $path, array $query = []): array
    {
        return $this->send($method, $path, $query, null, null);
    }

    /**
     * @param array<string, scalar|null> $query
     * @param array<string, mixed>|null  $body
     *
     * @return array<string, mixed>|list<mixed>
     */
    private function send(string $method, string $path, array $query, ?array $body, ?string $token): array
    {
        $options = ['headers' => ['Accept' => 'application/json']];

        if ($token !== null) {
            $options['headers']['Authorization'] = 'Bearer ' . $token;
        }
        if ($query !== []) {
            $options['query'] = array_filter($query, static fn (mixed $v): bool => $v !== null);
        }
        if ($body !== null) {
            $options['headers']['Content-Type'] = 'application/json';
            $options['body'] = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $response = $this->httpClient->request($method, self::API_HOST . $path, $options);
        $status = $response->getStatusCode();
        $raw = $response->getContent(throw: false);

        if ($raw === '') {
            if ($status >= 400) {
                throw MercadoLibreApiException::fromPayload($status, [], $raw);
            }

            return [];
        }

        $payload = json_decode($raw, true);

        if (!is_array($payload)) {
            throw new MercadoLibreApiException($status, null, [], $raw);
        }

        if ($status >= 400) {
            /** @var array<string, mixed> $payload */
            throw MercadoLibreApiException::fromPayload($status, $payload, $raw);
        }

        return $payload;
    }
}
