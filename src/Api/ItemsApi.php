<?php

declare(strict_types=1);

namespace Survos\MercadoLibre\Api;

use Survos\MercadoLibre\Http\MercadoLibreTransportInterface;
use Survos\MercadoLibre\Model\NewItem;
use Survos\MercadoLibre\Model\PublishedItem;

/**
 * Creating and managing listings.
 *
 * Publishing is a single call. Compare eBay: an inventory item, then an offer, then
 * a publish, against business policies and a merchant location that must already
 * exist.
 */
final readonly class ItemsApi
{
    public function __construct(private MercadoLibreTransportInterface $transport)
    {
    }

    /** Create and publish in one call. The listing is live when this returns. */
    public function publish(NewItem $item): PublishedItem
    {
        /** @var array<string, mixed> $response */
        $response = $this->transport->request('POST', '/items', body: $item->toArray());

        return PublishedItem::fromArray($response);
    }

    public function get(string $itemId): PublishedItem
    {
        /** @var array<string, mixed> $response */
        $response = $this->transport->request('GET', '/items/' . rawurlencode($itemId));

        return PublishedItem::fromArray($response);
    }

    /**
     * Partial update. Some fields (category_id, currency_id) cannot be changed once
     * the listing has bids or sales.
     *
     * @param array<string, mixed> $changes
     */
    public function update(string $itemId, array $changes): PublishedItem
    {
        /** @var array<string, mixed> $response */
        $response = $this->transport->request('PUT', '/items/' . rawurlencode($itemId), body: $changes);

        return PublishedItem::fromArray($response);
    }

    /**
     * Take a listing down.
     *
     * Two steps by design: Mercado Libre requires `paused` or `closed` before a
     * listing can be deleted, and `closed` is terminal -- a closed listing cannot be
     * reopened, only relisted as a new item.
     */
    public function close(string $itemId): PublishedItem
    {
        return $this->update($itemId, ['status' => 'closed']);
    }

    public function pause(string $itemId): PublishedItem
    {
        return $this->update($itemId, ['status' => 'paused']);
    }
}
