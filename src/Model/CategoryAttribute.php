<?php

declare(strict_types=1);

namespace Survos\MercadoLibre\Model;

/**
 * One attribute a category declares.
 *
 * Unlike eBay -- whose OpenAPI contracts declare no required fields at all, leaving
 * requiredness to prose -- Mercado Libre reports it in the payload, under `tags`.
 * That makes a category's real contract machine-readable, which is what lets a
 * listing be validated before it is sent.
 *
 * The trap is `read_only` and `hidden`. For the Mexican postcards category
 * (MLM429001), 37 of 56 attributes carry one of those tags: tax fields, logistics
 * internals, catalog bookkeeping. Sending them is an error, not a no-op, so any
 * code that maps "all attributes" onto a listing payload fails immediately.
 * {@see \Survos\MercadoLibre\Api\CategoriesApi::writableAttributes()} filters them.
 */
final readonly class CategoryAttribute
{
    /**
     * @param list<AllowedValue>   $allowedValues empty means free text
     * @param array<string, mixed> $tags          raw tags, for anything not modelled here
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $valueType = 'string',
        public bool $required = false,
        public bool $conditionalRequired = false,
        public bool $readOnly = false,
        public bool $hidden = false,
        public bool $multivalued = false,
        public array $allowedValues = [],
        public ?string $groupName = null,
        public array $tags = [],
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $tags = (array) ($data['tags'] ?? []);
        $values = [];
        foreach ((array) ($data['values'] ?? []) as $value) {
            if (is_array($value)) {
                $values[] = AllowedValue::fromArray($value);
            }
        }

        return new self(
            id: (string) ($data['id'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            valueType: (string) ($data['value_type'] ?? 'string'),
            required: (bool) ($tags['required'] ?? false),
            conditionalRequired: (bool) ($tags['conditional_required'] ?? false),
            readOnly: (bool) ($tags['read_only'] ?? false),
            hidden: (bool) ($tags['hidden'] ?? false),
            multivalued: (bool) ($tags['multivalued'] ?? false),
            allowedValues: $values,
            groupName: isset($data['attribute_group_name']) ? (string) $data['attribute_group_name'] : null,
            tags: $tags,
        );
    }

    /** Can a seller actually send this? */
    public function isWritable(): bool
    {
        return !$this->readOnly && !$this->hidden;
    }

    public function isClosedSet(): bool
    {
        return $this->allowedValues !== [];
    }

    /** Resolve a human value to its id, so the listing uses value_id where possible. */
    public function valueIdFor(string $name): ?string
    {
        foreach ($this->allowedValues as $value) {
            if (strcasecmp($value->name, $name) === 0) {
                return $value->id;
            }
        }

        return null;
    }
}
