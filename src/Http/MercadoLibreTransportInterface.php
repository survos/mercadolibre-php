<?php

declare(strict_types=1);

namespace Survos\MercadoLibre\Http;

interface MercadoLibreTransportInterface
{
    /**
     * @param array<string, scalar|null> $query
     * @param array<string, mixed>|null  $body
     *
     * @return array<string, mixed>|list<mixed> decoded JSON; [] for an empty body
     */
    public function request(string $method, string $path, array $query = [], ?array $body = null): array;

    /**
     * Some endpoints are public (category trees, domain discovery) and some are
     * not. Anonymous calls skip the token entirely, so a caller can resolve a
     * category before any seller has authorized anything.
     *
     * @param array<string, scalar|null> $query
     *
     * @return array<string, mixed>|list<mixed>
     */
    public function requestAnonymous(string $method, string $path, array $query = []): array;
}
