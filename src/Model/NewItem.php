<?php

declare(strict_types=1);

namespace Survos\MercadoLibre\Model;

/**
 * A listing to publish. One POST /items and it is live -- no inventory item, no
 * offer, no business policies, no merchant location.
 */
final readonly class NewItem
{
    /**
     * @param string             $price      decimal string, e.g. "80.00". Kept exact here and
     *                                       converted to a JSON number exactly once, in
     *                                       {@see toArray()}, because that is what the API wants
     * @param list<Picture>      $pictures
     * @param list<ItemAttribute> $attributes
     */
    public function __construct(
        public string $title,
        public string $categoryId,
        public string $price,
        public string $currencyId,
        public int $availableQuantity = 1,
        public ItemCondition $condition = ItemCondition::Used,
        public ListingType $listingType = ListingType::Classic,
        public BuyingMode $buyingMode = BuyingMode::BuyItNow,
        public array $pictures = [],
        public array $attributes = [],
        public ?string $description = null,
        public ?string $sellerCustomField = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = [
            'title' => $this->title,
            'category_id' => $this->categoryId,
            // The API rejects a quoted price; this is the one lossy step and it is
            // deliberately in one visible place.
            'price' => (float) $this->price,
            'currency_id' => $this->currencyId,
            'available_quantity' => $this->availableQuantity,
            'buying_mode' => $this->buyingMode->value,
            'listing_type_id' => $this->listingType->value,
            'condition' => $this->condition->value,
        ];

        if ($this->pictures !== []) {
            $data['pictures'] = array_map(static fn (Picture $p): array => $p->toArray(), $this->pictures);
        }
        if ($this->attributes !== []) {
            $data['attributes'] = array_map(static fn (ItemAttribute $a): array => $a->toArray(), $this->attributes);
        }
        if ($this->description !== null) {
            $data['description'] = ['plain_text' => $this->description];
        }
        if ($this->sellerCustomField !== null) {
            // The closest thing Mercado Libre has to a SKU on a plain listing.
            $data['seller_custom_field'] = $this->sellerCustomField;
        }

        return $data;
    }
}
