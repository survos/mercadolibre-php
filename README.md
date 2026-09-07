# survos/mercadolibre-php

A Mercado Libre API client for PHP, focused on listing items.

Framework-agnostic. It depends on `symfony/http-client-contracts`, which is an
**interface** package — bring any implementation you like. The same code runs in a
Symfony application, a WordPress plugin, or a plain PHP script.

```bash
composer require survos/mercadolibre-php symfony/http-client
```

## Why hand-written, when `survos/ebay-php` is generated

Mercado Libre publishes no OpenAPI contract, so there is nothing to generate from.
It also does not need one: where eBay takes an inventory item, an offer and a
publish — against business policies and a merchant location that must already exist
— Mercado Libre is a single `POST /items` and the listing is live.

The PHP ecosystem is not an option either. The official `mercadolibre/php-sdk` was
**archived in 2021**. `dsc/mercado-livre` is the best community package (79 stars,
~40k downloads) but requires `php: >=5.6` and last released in 2023.
`zdearo/meli-php` is modern PHP 8.3 but Laravel-only. There is no Symfony bundle at
all — every wrapper on Packagist is Laravel.

## Two things that will bite you

### 1. Refresh tokens are single use

Every refresh returns a **new** refresh token and kills the one you spent. Only the
most recently issued token is accepted.

This is the opposite of eBay, whose refresh token is stable for ~18 months and whose
refresh responses usually omit it. Code written against eBay — *"keep the refresh
token you already have, it doesn't change"* — passes every eBay test and then
permanently locks a Mercado Libre account out on its first refresh, surfacing as an
authorization error that points nowhere near storage.

`MercadoLibreToken::refreshed()` deliberately does **not** fall back to the previous
refresh token, and `RefreshingTokenProvider` persists the replacement *before* using
it. If your process dies between the API issuing a token and your store committing
it, the seller must re-consent. There is no recovery.

### 2. There is no sandbox

None. Every call hits production. Testing uses **test users** — real accounts on the
real API that simply don't move real money:

- ten per application, ever; they cannot be deleted, by you or by Mercado Libre
- test listings must be titled "Test item - Do not offer"
- everything else behaves normally, including publishing

Because the quota is small and permanent, `TestUsersApi::create()` is not something
to call from a test suite. Create them once, by hand, and store the credentials.

## Categories are public

Both category endpoints need no token, so a category can be resolved and a listing
validated before any seller has authorized anything.

```php
use Survos\MercadoLibre\Api\CategoriesApi;
use Survos\MercadoLibre\Http\MercadoLibreTransport;
use Survos\MercadoLibre\MercadoLibreSite;

$categories = new CategoriesApi(
    new MercadoLibreTransport($httpClient),   // no token provider needed
    MercadoLibreSite::Mexico,
);

$categories->predict('postal antigua')[0]->categoryId;   // "MLM429001" (Postales)
```

Unlike eBay — whose OpenAPI contracts declare **no** required fields at all, leaving
requiredness to prose — Mercado Libre reports it in the payload under `tags`. That
makes a category's real contract machine-readable.

The trap is `read_only` and `hidden`. For MLM429001 (Mexican collectible postcards),
**37 of 56 attributes** carry one of those tags — tax fields, logistics internals,
catalog bookkeeping — and sending one is an error, not a no-op:

```php
$categories->attributes('MLM429001');          // 56
$categories->writableAttributes('MLM429001');  // 19
$categories->requiredAttributes('MLM429001');  // 5: ORIGIN, YEAR, PATTERN_NAME, BRAND, MODEL
```

## Publishing

```php
use Survos\MercadoLibre\Api\ItemsApi;
use Survos\MercadoLibre\Model\{ItemAttribute, ItemCondition, NewItem, Picture};

$item = new ItemsApi(new MercadoLibreTransport($httpClient, $tokenProvider))->publish(new NewItem(
    title:       'Lote 5 Postales Antiguas Animales',
    categoryId:  'MLM429001',
    price:       '80.00',            // decimal string; sent as a JSON number
    currencyId:  'MXN',
    condition:   ItemCondition::Used,
    pictures:    [new Picture('https://example.org/animals.jpg')],
    attributes:  [new ItemAttribute('ORIGIN', valueId: '2114722')],
    description: 'Cinco postales tematicas.',
));

echo $item->permalink;
```

Send `value_id` rather than `value_name` whenever the attribute has a closed set —
use `CategoryAttribute::valueIdFor()`. A free-text name on a closed-set attribute is
accepted at publish time and then fails catalog matching later, which is a worse
failure than a rejection would have been.

Prices are held as decimal strings and converted to a JSON number in exactly one
visible place, because the API rejects a quoted price and floats lose cents.

Images are passed as source URLs that Mercado Libre fetches and re-hosts, so the URL
only needs to be reachable at publish time — unlike eBay, which keeps pointing at
yours.

## Tests

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
```

`tests/fixtures/categories_MLM429001_attributes.json` was captured from the live API
on 2026-09-07, so the read-only/hidden ratio in the tests is real.
