<?php

declare(strict_types=1);

namespace Survos\MercadoLibre\Model;

/**
 * A category Mercado Libre predicts from free text.
 *
 * The `domain` is the interesting extra: it is the catalog concept behind the
 * category (e.g. MLM-COLLECTIBLE_POSTCARDS), and it is more stable than the
 * category id across tree reshuffles.
 */
final readonly class CategoryPrediction
{
    public function __construct(
        public string $categoryId,
        public string $categoryName,
        public ?string $domainId = null,
        public ?string $domainName = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            categoryId: (string) ($data['category_id'] ?? ''),
            categoryName: (string) ($data['category_name'] ?? ''),
            domainId: isset($data['domain_id']) ? (string) $data['domain_id'] : null,
            domainName: isset($data['domain_name']) ? (string) $data['domain_name'] : null,
        );
    }
}
