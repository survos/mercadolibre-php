<?php

declare(strict_types=1);

namespace Survos\MercadoLibre\Tests;

use PHPUnit\Framework\TestCase;
use Survos\MercadoLibre\Api\CategoriesApi;
use Survos\MercadoLibre\Http\MercadoLibreTransport;
use Survos\MercadoLibre\MercadoLibreSite;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Runs against a fixture captured from the live API on 2026-09-07:
 * GET /categories/MLM429001/attributes -- "Postales", the Mexican collectible
 * postcards category. Real data, so the read-only/hidden ratio is real too.
 */
final class CategoriesApiTest extends TestCase
{
    private function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/fixtures/' . $name);
    }

    private function api(string $body): CategoriesApi
    {
        return new CategoriesApi(
            new MercadoLibreTransport(new MockHttpClient([new MockResponse($body)])),
            MercadoLibreSite::Mexico,
        );
    }

    public function testPredictsACategoryFromATitle(): void
    {
        // The real response for q="postal antigua" on MLM.
        $api = $this->api((string) json_encode([[
            'domain_id' => 'MLM-COLLECTIBLE_POSTCARDS',
            'domain_name' => 'Tarjetas postales coleccionables',
            'category_id' => 'MLM429001',
            'category_name' => 'Postales',
        ]]));

        $predictions = $api->predict('postal antigua');

        self::assertCount(1, $predictions);
        self::assertSame('MLM429001', $predictions[0]->categoryId);
        self::assertSame('Postales', $predictions[0]->categoryName);
        self::assertSame('MLM-COLLECTIBLE_POSTCARDS', $predictions[0]->domainId);
    }

    public function testPredictionUsesThePublicEndpointWithNoToken(): void
    {
        $seen = ['url' => '', 'headers' => []];
        $client = new MockHttpClient(function (string $m, string $url, array $o) use (&$seen): MockResponse {
            $seen = ['url' => $url, 'headers' => $o['headers']];

            return new MockResponse('[]');
        });

        // No token provider at all -- this must still work.
        (new CategoriesApi(new MercadoLibreTransport($client), MercadoLibreSite::Mexico))->predict('x');

        self::assertStringContainsString('/sites/MLM/domain_discovery/search', $seen['url']);
        foreach ($seen['headers'] as $header) {
            self::assertStringNotContainsStringIgnoringCase('authorization', $header);
        }
    }

    public function testParsesRequirednessFromTags(): void
    {
        $attributes = $this->api($this->fixture('categories_MLM429001_attributes.json'))
            ->attributes('MLM429001');

        self::assertCount(56, $attributes);

        $byId = [];
        foreach ($attributes as $a) {
            $byId[$a->id] = $a;
        }

        self::assertTrue($byId['ORIGIN']->required);
        self::assertTrue($byId['ORIGIN']->isClosedSet());
        self::assertSame('number', $byId['YEAR']->valueType);
        self::assertFalse($byId['YEAR']->isClosedSet());
    }

    public function testFiltersOutTheAttributesYouCannotSend(): void
    {
        // 37 of 56 are read_only or hidden. Sending one is an error, not a no-op.
        $all = $this->api($this->fixture('categories_MLM429001_attributes.json'))->attributes('MLM429001');
        $writable = array_filter($all, static fn ($a): bool => $a->isWritable());

        self::assertCount(56, $all);
        self::assertCount(19, $writable);
    }

    public function testRequiredAttributesAreTheShortList(): void
    {
        $required = $this->api($this->fixture('categories_MLM429001_attributes.json'))
            ->requiredAttributes('MLM429001');

        self::assertSame(
            ['ORIGIN', 'YEAR', 'PATTERN_NAME', 'BRAND', 'MODEL'],
            array_map(static fn ($a): string => $a->id, $required),
        );
    }

    public function testResolvesAValueNameToItsIdCaseInsensitively(): void
    {
        $attributes = $this->api($this->fixture('categories_MLM429001_attributes.json'))->attributes('MLM429001');
        $origin = null;
        foreach ($attributes as $a) {
            if ($a->id === 'ORIGIN') {
                $origin = $a;
            }
        }

        self::assertNotNull($origin);
        self::assertSame('2114722', $origin->valueIdFor('españa'));
        self::assertNull($origin->valueIdFor('Chiapas'));
    }
}
