<?php

declare(strict_types=1);

namespace Survos\MercadoLibre\Model;

final readonly class PublishedItem
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public string $id,
        public ?string $permalink = null,
        public ?string $status = null,
        public ?\DateTimeImmutable $createdAt = null,
        public array $raw = [],
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $created = null;
        if (isset($data['date_created']) && is_string($data['date_created'])) {
            $created = new \DateTimeImmutable($data['date_created']);
        }

        return new self(
            id: (string) ($data['id'] ?? ''),
            permalink: isset($data['permalink']) ? (string) $data['permalink'] : null,
            status: isset($data['status']) ? (string) $data['status'] : null,
            createdAt: $created,
            raw: $data,
        );
    }

    /** Live and visible, as opposed to `paused`, `under_review` or `closed`. */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
