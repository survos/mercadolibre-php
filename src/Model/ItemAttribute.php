<?php

declare(strict_types=1);

namespace Survos\MercadoLibre\Model;

/**
 * An attribute value on a listing.
 *
 * Send `value_id` when the attribute has a closed set and `value_name` otherwise.
 * Sending a free-text `value_name` for an attribute that has a value list is
 * accepted at publish time and then fails catalog matching later, which is a much
 * worse failure than a rejection would have been.
 */
final readonly class ItemAttribute
{
    public function __construct(
        public string $id,
        public ?string $valueName = null,
        public ?string $valueId = null,
    ) {
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        $data = ['id' => $this->id];

        if ($this->valueId !== null) {
            $data['value_id'] = $this->valueId;
        } elseif ($this->valueName !== null) {
            $data['value_name'] = $this->valueName;
        }

        return $data;
    }
}
