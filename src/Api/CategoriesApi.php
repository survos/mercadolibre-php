<?php

declare(strict_types=1);

namespace Survos\MercadoLibre\Api;

use Survos\MercadoLibre\Http\MercadoLibreTransportInterface;
use Survos\MercadoLibre\MercadoLibreSite;
use Survos\MercadoLibre\Model\CategoryAttribute;
use Survos\MercadoLibre\Model\CategoryPrediction;

/**
 * Category prediction and per-category attributes.
 *
 * Both endpoints are PUBLIC -- no token, no seller. A category can be resolved and
 * a listing validated before anyone has authorized anything, which makes this the
 * natural first thing to wire up.
 */
final readonly class CategoriesApi
{
    public function __construct(
        private MercadoLibreTransportInterface $transport,
        private MercadoLibreSite $site = MercadoLibreSite::Mexico,
    ) {
    }

    /**
     * Predict categories from free text -- typically a generated listing title.
     *
     * @return list<CategoryPrediction> best first; empty when nothing matches
     */
    public function predict(string $title, int $limit = 5): array
    {
        $response = $this->transport->requestAnonymous(
            'GET',
            sprintf('/sites/%s/domain_discovery/search', $this->site->value),
            ['q' => $title, 'limit' => $limit],
        );

        $predictions = [];
        foreach ($response as $entry) {
            if (is_array($entry)) {
                $predictions[] = CategoryPrediction::fromArray($entry);
            }
        }

        return $predictions;
    }

    /**
     * Every attribute the category declares, including the ones you cannot send.
     *
     * @return list<CategoryAttribute>
     */
    public function attributes(string $categoryId): array
    {
        $response = $this->transport->requestAnonymous('GET', sprintf('/categories/%s/attributes', rawurlencode($categoryId)));

        $attributes = [];
        foreach ($response as $entry) {
            if (is_array($entry)) {
                $attributes[] = CategoryAttribute::fromArray($entry);
            }
        }

        return $attributes;
    }

    /**
     * Only the attributes a seller can actually set.
     *
     * This is the one you almost always want. For MLM429001 (Mexican postcards) it
     * cuts 56 attributes to 19 -- the other 37 are tax, logistics and catalog
     * bookkeeping tagged `read_only` or `hidden`, and sending any of them is an
     * error rather than a no-op.
     *
     * @return list<CategoryAttribute>
     */
    public function writableAttributes(string $categoryId): array
    {
        return array_values(array_filter(
            $this->attributes($categoryId),
            static fn (CategoryAttribute $a): bool => $a->isWritable(),
        ));
    }

    /**
     * Writable attributes that must be supplied.
     *
     * @return list<CategoryAttribute>
     */
    public function requiredAttributes(string $categoryId): array
    {
        return array_values(array_filter(
            $this->writableAttributes($categoryId),
            static fn (CategoryAttribute $a): bool => $a->required,
        ));
    }
}
