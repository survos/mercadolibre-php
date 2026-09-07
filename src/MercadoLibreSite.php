<?php

declare(strict_types=1);

namespace Survos\MercadoLibre;

/**
 * A Mercado Libre country site.
 *
 * The site is not cosmetic. It selects the category tree, the currency, and --
 * critically -- the host a seller must be sent to for consent. Sending a Mexican
 * seller to the Argentine auth host produces a login page that will never
 * authorize your application.
 */
enum MercadoLibreSite: string
{
    case Argentina = 'MLA';
    case Brazil = 'MLB';
    case Chile = 'MLC';
    case Colombia = 'MCO';
    case Mexico = 'MLM';
    case Peru = 'MPE';
    case Uruguay = 'MLU';

    /**
     * Where a seller grants consent. Per-country by design: the auth host is
     * localized, and Brazil is additionally spelled "mercadolivre".
     */
    public function authHost(): string
    {
        return match ($this) {
            self::Argentina => 'https://auth.mercadolibre.com.ar',
            self::Brazil => 'https://auth.mercadolivre.com.br',
            self::Chile => 'https://auth.mercadolibre.cl',
            self::Colombia => 'https://auth.mercadolibre.com.co',
            self::Mexico => 'https://auth.mercadolibre.com.mx',
            self::Peru => 'https://auth.mercadolibre.com.pe',
            self::Uruguay => 'https://auth.mercadolibre.com.uy',
        };
    }

    /** ISO 4217 code this site prices in. */
    public function currency(): string
    {
        return match ($this) {
            self::Argentina => 'ARS',
            self::Brazil => 'BRL',
            self::Chile => 'CLP',
            self::Colombia => 'COP',
            self::Mexico => 'MXN',
            self::Peru => 'PEN',
            self::Uruguay => 'UYU',
        };
    }

    /** BCP 47 tag for listing content on this site. */
    public function locale(): string
    {
        return match ($this) {
            self::Argentina => 'es-AR',
            self::Brazil => 'pt-BR',
            self::Chile => 'es-CL',
            self::Colombia => 'es-CO',
            self::Mexico => 'es-MX',
            self::Peru => 'es-PE',
            self::Uruguay => 'es-UY',
        };
    }
}
