<?php

declare(strict_types=1);

namespace Survos\MercadoLibre\Model;

/**
 * An image, given as a source URL.
 *
 * Mercado Libre fetches it once and re-hosts it, so the URL only has to be
 * reachable at publish time -- unlike eBay, which keeps pointing at yours.
 */
final readonly class Picture
{
    public function __construct(public string $source)
    {
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return ['source' => $this->source];
    }
}
