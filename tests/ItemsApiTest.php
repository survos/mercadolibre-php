<?php

declare(strict_types=1);

namespace Survos\MercadoLibre\Tests;

use PHPUnit\Framework\TestCase;
use Survos\MercadoLibre\Api\ItemsApi;
use Survos\MercadoLibre\Auth\StaticAccessTokenProvider;
use Survos\MercadoLibre\Exception\MercadoLibreApiException;
use Survos\MercadoLibre\Exception\MercadoLibreAuthenticationException;
use Survos\MercadoLibre\Http\MercadoLibreTransport;
use Survos\MercadoLibre\Model\ItemAttribute;
use Survos\MercadoLibre\Model\ItemCondition;
use Survos\MercadoLibre\Model\NewItem;
use Survos\MercadoLibre\Model\Picture;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ItemsApiTest extends TestCase
{
    private function postcardPack(): NewItem
    {
        return new NewItem(
            title: 'Lote 5 Postales Antiguas Animales',
            categoryId: 'MLM429001',
            price: '80.00',
            currencyId: 'MXN',
            condition: ItemCondition::Used,
            pictures: [new Picture('https://example.org/animals.jpg')],
            attributes: [
                new ItemAttribute('ORIGIN', valueId: '2114722'),
                new ItemAttribute('BRAND', valueName: 'Sin marca'),
            ],
            description: 'Cinco postales tematicas.',
            sellerCustomField: 'PC-ANIMALS-001',
        );
    }

    public function testPublishesInASingleCall(): void
    {
        $seen = ['method' => '', 'url' => '', 'body' => '', 'headers' => []];
        $client = new MockHttpClient(function (string $m, string $url, array $o) use (&$seen): MockResponse {
            $seen = ['method' => $m, 'url' => $url, 'body' => $o['body'], 'headers' => $o['headers']];

            return new MockResponse((string) json_encode([
                'id' => 'MLM1234567890',
                'permalink' => 'https://articulo.mercadolibre.com.mx/MLM-1234567890',
                'status' => 'active',
                'date_created' => '2026-09-07T13:00:00.000-04:00',
            ]));
        });

        $api = new ItemsApi(new MercadoLibreTransport($client, new StaticAccessTokenProvider('tok', 123)));
        $item = $api->publish($this->postcardPack());

        self::assertSame('POST', $seen['method']);
        self::assertSame('https://api.mercadolibre.com/items', $seen['url']);
        self::assertSame('MLM1234567890', $item->id);
        self::assertTrue($item->isActive());
        self::assertNotNull($item->createdAt);
    }

    public function testPriceIsSentAsAJsonNumberNotAString(): void
    {
        $seen = '';
        $client = new MockHttpClient(function (string $m, string $u, array $o) use (&$seen): MockResponse {
            $seen = (string) $o['body'];

            return new MockResponse('{"id":"MLM1"}');
        });

        (new ItemsApi(new MercadoLibreTransport($client, new StaticAccessTokenProvider('t'))))
            ->publish($this->postcardPack());

        $payload = json_decode($seen, true);
        self::assertIsArray($payload);
        self::assertIsNumeric($payload['price']);
        self::assertIsNotString($payload['price'], 'the API rejects a quoted price');
        self::assertEquals(80, $payload['price']);
        self::assertSame('MXN', $payload['currency_id']);
        self::assertSame('buy_it_now', $payload['buying_mode']);
        self::assertSame('gold_special', $payload['listing_type_id']);
        self::assertSame(['plain_text' => 'Cinco postales tematicas.'], $payload['description']);
        self::assertSame('PC-ANIMALS-001', $payload['seller_custom_field']);
    }

    public function testAttributeSendsValueIdWhenKnownAndValueNameOtherwise(): void
    {
        self::assertSame(
            ['id' => 'ORIGIN', 'value_id' => '2114722'],
            (new ItemAttribute('ORIGIN', valueName: 'España', valueId: '2114722'))->toArray(),
            'value_id wins -- a free-text name on a closed set fails catalog matching later',
        );
        self::assertSame(
            ['id' => 'BRAND', 'value_name' => 'Sin marca'],
            (new ItemAttribute('BRAND', valueName: 'Sin marca'))->toArray(),
        );
    }

    public function testSellerCallsWithoutATokenProviderFailLoudly(): void
    {
        $api = new ItemsApi(new MercadoLibreTransport(new MockHttpClient([])));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('requestAnonymous');

        $api->publish($this->postcardPack());
    }

    public function testMapsTheCauseArrayWhichIsWhereTheRealReasonLives(): void
    {
        $client = new MockHttpClient([new MockResponse(
            (string) json_encode([
                'message' => 'Validation error',
                'error' => 'validation_error',
                'status' => 400,
                'cause' => [
                    ['code' => 'item.attributes.missing', 'message' => 'The attribute YEAR is required'],
                ],
            ]),
            ['http_code' => 400],
        )]);

        try {
            (new ItemsApi(new MercadoLibreTransport($client, new StaticAccessTokenProvider('t'))))
                ->publish($this->postcardPack());
            self::fail('expected MercadoLibreApiException');
        } catch (MercadoLibreApiException $e) {
            self::assertSame(400, $e->statusCode);
            self::assertSame('validation_error', $e->errorCode);
            self::assertStringContainsString('The attribute YEAR is required', $e->getMessage());
        }
    }

    public function testAuthFailuresGetTheirOwnType(): void
    {
        $client = new MockHttpClient([new MockResponse('{"message":"invalid token"}', ['http_code' => 401])]);

        $this->expectException(MercadoLibreAuthenticationException::class);

        (new ItemsApi(new MercadoLibreTransport($client, new StaticAccessTokenProvider('t'))))->get('MLM1');
    }
}
