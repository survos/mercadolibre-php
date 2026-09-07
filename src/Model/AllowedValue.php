<?php

declare(strict_types=1);

namespace Survos\MercadoLibre\Model;

/**
 * One entry from an attribute's closed value set.
 *
 * Both halves matter: Mercado Libre prefers `value_id` when the attribute has a
 * value list, and matching by name is how a listing ends up with a free-text value
 * that fails catalog validation later.
 */
final readonly class AllowedValue
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self((string) ($data['id'] ?? ''), (string) ($data['name'] ?? ''));
    }
}
