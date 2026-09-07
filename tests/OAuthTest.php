<?php

declare(strict_types=1);

namespace Survos\MercadoLibre\Tests;

use PHPUnit\Framework\TestCase;
use Survos\MercadoLibre\Auth\MercadoLibreCredentials;
use Survos\MercadoLibre\Auth\MercadoLibreToken;
use Survos\MercadoLibre\Auth\OAuthService;
use Survos\MercadoLibre\Auth\RefreshingTokenProvider;
use Survos\MercadoLibre\Exception\MercadoLibreAuthenticationException;
use Survos\MercadoLibre\MercadoLibreSite;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class OAuthTest extends TestCase
{
    private function credentials(): MercadoLibreCredentials
    {
        return new MercadoLibreCredentials('app-id', 'app-secret', 'https://chijal.org/meli/callback');
    }

    public function testConsentUrlUsesThePerCountryAuthHost(): void
    {
        $mx = new OAuthService(new MockHttpClient(), $this->credentials(), MercadoLibreSite::Mexico);
        $br = new OAuthService(new MockHttpClient(), $this->credentials(), MercadoLibreSite::Brazil);

        self::assertStringStartsWith('https://auth.mercadolibre.com.mx/authorization?', $mx->consentUrl());
        // Brazil is both a different TLD and a different spelling.
        self::assertStringStartsWith('https://auth.mercadolivre.com.br/authorization?', $br->consentUrl());
    }

    public function testSiteCarriesCurrencyAndLocale(): void
    {
        self::assertSame('MXN', MercadoLibreSite::Mexico->currency());
        self::assertSame('es-MX', MercadoLibreSite::Mexico->locale());
        self::assertSame('BRL', MercadoLibreSite::Brazil->currency());
        self::assertSame('pt-BR', MercadoLibreSite::Brazil->locale());
    }

    public function testExchangeCodeCapturesTheUserId(): void
    {
        $client = new MockHttpClient([new MockResponse((string) json_encode([
            'access_token' => 'APP_USR-abc',
            'expires_in' => 21600,
            'refresh_token' => 'TG-refresh-1',
            'user_id' => 987654321,
            'scope' => 'offline_access read write',
        ]))]);

        $token = (new OAuthService($client, $this->credentials()))->exchangeCode('TG-code');

        self::assertSame('APP_USR-abc', $token->accessToken);
        self::assertSame(987654321, $token->userId, 'every seller-scoped call needs this');
        self::assertFalse($token->isExpired());
    }

    public function testRefreshReplacesTheRefreshTokenAndNeverFallsBack(): void
    {
        // Mercado Libre issues a NEW refresh token every time and kills the old one.
        $client = new MockHttpClient([new MockResponse((string) json_encode([
            'access_token' => 'APP_USR-second',
            'expires_in' => 21600,
            'refresh_token' => 'TG-refresh-2',
            'user_id' => 987654321,
        ]))]);

        $old = new MercadoLibreToken(
            'APP_USR-first',
            new \DateTimeImmutable('-1 hour'),
            'TG-refresh-1',
            987654321,
        );

        $new = (new OAuthService($client, $this->credentials()))->refresh($old);

        self::assertSame('TG-refresh-2', $new->refreshToken);
        self::assertSame('TG-refresh-1', $old->refreshToken, 'the original is untouched');
        self::assertNotSame($old->refreshToken, $new->refreshToken);
    }

    public function testRefreshWithoutANewTokenDoesNotResurrectTheDeadOne(): void
    {
        // Defensive: if a response ever omits refresh_token, carrying the spent one
        // forward would look valid and be dead. Fail loudly instead.
        $client = new MockHttpClient([new MockResponse((string) json_encode([
            'access_token' => 'APP_USR-second',
            'expires_in' => 21600,
        ]))]);

        $old = new MercadoLibreToken('a', new \DateTimeImmutable('-1 hour'), 'TG-refresh-1');
        $new = (new OAuthService($client, $this->credentials()))->refresh($old);

        self::assertNull($new->refreshToken);
        self::assertFalse($new->canRefresh());
    }

    public function testProviderPersistsTheRotatedTokenBeforeUsingIt(): void
    {
        $client = new MockHttpClient([new MockResponse((string) json_encode([
            'access_token' => 'APP_USR-fresh',
            'expires_in' => 21600,
            'refresh_token' => 'TG-refresh-2',
        ]))]);

        $store = new MercadoLibreToken('stale', new \DateTimeImmutable('-1 minute'), 'TG-refresh-1');
        $persisted = null;

        $provider = new RefreshingTokenProvider(
            new OAuthService($client, $this->credentials()),
            static fn (): MercadoLibreToken => $store,
            function (MercadoLibreToken $t) use (&$persisted): void { $persisted = $t; },
        );

        self::assertSame('APP_USR-fresh', $provider->accessToken());
        self::assertNotNull($persisted);
        self::assertSame('TG-refresh-2', $persisted->refreshToken, 'losing this locks the account out permanently');
    }

    public function testMissingTokenPointsAtConsent(): void
    {
        $provider = new RefreshingTokenProvider(
            new OAuthService(new MockHttpClient([]), $this->credentials()),
            static fn (): ?MercadoLibreToken => null,
            static function (MercadoLibreToken $t): void {},
        );

        $this->expectException(MercadoLibreAuthenticationException::class);
        $this->expectExceptionMessage('consentUrl');

        $provider->accessToken();
    }

    public function testConsentUrlWithoutARedirectUriExplainsWhy(): void
    {
        $service = new OAuthService(new MockHttpClient(), new MercadoLibreCredentials('id', 'secret'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('registered on the application exactly');

        $service->consentUrl();
    }
}
