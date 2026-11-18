<?php

namespace Osaris\Discogs\Resources;

use Osaris\Discogs\Page;

class Marketplace extends Resource
{
    /** A live listing, visible to buyers. */
    public const STATUS_FOR_SALE = 'For Sale';

    /** A listing saved but not visible to buyers. */
    public const STATUS_DRAFT = 'Draft';

    /**
     * GET /users/{username}/inventory
     *
     * @param  array<string, mixed>  $query  status, sort (listed|price|item|artist|label|catno|audio|status|location),
     *                                       sort_order (asc|desc), page, per_page
     */
    public function inventory(?string $username = null, array $query = []): Page
    {
        return $this->client->paginate("users/{$this->user($username)}/inventory", 'listings', $query);
    }

    /**
     * GET /marketplace/listings/{listing_id}
     *
     * @return array<mixed>
     */
    public function listing(int $listingId, ?string $currency = null): array
    {
        return $this->client->get("marketplace/listings/{$listingId}", ['curr_abbr' => $currency]);
    }

    /**
     * POST /marketplace/listings
     *
     * Listings are created as drafts unless a status is given: pass
     * ['status' => Marketplace::STATUS_FOR_SALE] to list for sale straight away.
     *
     * @param  string  $condition  e.g. "Mint (M)", "Near Mint (NM or M-)", "Very Good Plus (VG+)".
     * @param  array<string, mixed>  $attributes  status ("For Sale"|"Draft"), sleeve_condition, comments, allow_offers,
     *                                            external_id, location, weight (grams or "auto"), format_quantity (number or "auto")
     * @return array<mixed> listing_id and resource_url
     */
    public function createListing(int $releaseId, string $condition, float $price, array $attributes = []): array
    {
        return $this->client->post('marketplace/listings', [
            'release_id' => $releaseId,
            'condition' => $condition,
            'price' => $price,
        ] + $attributes + ['status' => self::STATUS_DRAFT]);
    }

    /**
     * POST /marketplace/listings/{listing_id}
     *
     * Discogs expects release_id, condition, price and status on every edit,
     * not only the fields being changed.
     *
     * @param  array<string, mixed>  $attributes  release_id, condition, price, status, sleeve_condition, comments,
     *                                            allow_offers, external_id, location, weight, format_quantity
     */
    public function updateListing(int $listingId, array $attributes): bool
    {
        return $this->client->send('POST', "marketplace/listings/{$listingId}", data: $attributes)->successful();
    }

    /**
     * DELETE /marketplace/listings/{listing_id}
     */
    public function deleteListing(int $listingId): bool
    {
        return $this->client->delete("marketplace/listings/{$listingId}");
    }

    /**
     * GET /marketplace/orders
     *
     * @param  array<string, mixed>  $query  status, created_after, created_before (ISO 8601), archived (bool),
     *                                       sort (id|buyer|created|status|last_activity), sort_order, page, per_page
     */
    public function orders(array $query = []): Page
    {
        return $this->client->paginate('marketplace/orders', 'orders', $query);
    }

    /**
     * GET /marketplace/orders/{order_id}
     *
     * @param  string  $orderId  e.g. "1-1".
     * @return array<mixed>
     */
    public function order(string $orderId): array
    {
        return $this->client->get('marketplace/orders/'.rawurlencode($orderId));
    }

    /**
     * POST /marketplace/orders/{order_id}
     *
     * Status and shipping cannot be set in the same request: setting shipping
     * invoices the buyer and moves the order to "Invoice Sent".
     *
     * @param  array<string, mixed>  $attributes  status, shipping (number),
     *                                            tracking (['number' => ..., 'carrier' => ...])
     * @return array<mixed> includes next_status, the valid statuses to move to
     */
    public function updateOrder(string $orderId, array $attributes): array
    {
        return $this->client->post('marketplace/orders/'.rawurlencode($orderId), $attributes);
    }

    /**
     * GET /marketplace/orders/{order_id}/messages
     *
     * @param  array<string, mixed>  $query  page, per_page
     */
    public function orderMessages(string $orderId, array $query = []): Page
    {
        return $this->client->paginate('marketplace/orders/'.rawurlencode($orderId).'/messages', 'messages', $query);
    }

    /**
     * POST /marketplace/orders/{order_id}/messages
     *
     * At least one of message and status is needed.
     *
     * @return array<mixed>
     */
    public function addOrderMessage(string $orderId, ?string $message = null, ?string $status = null): array
    {
        return $this->client->post('marketplace/orders/'.rawurlencode($orderId).'/messages', [
            'message' => $message,
            'status' => $status,
        ]);
    }

    /**
     * GET /marketplace/fee/{price}[/{currency}]
     *
     * @return array<mixed>
     */
    public function fee(float $price, ?string $currency = null): array
    {
        return $this->client->get('marketplace/fee/'.$price.($currency !== null ? '/'.rawurlencode($currency) : ''));
    }

    /**
     * GET /marketplace/price_suggestions/{release_id}
     *
     * Needs a user with seller settings filled in.
     *
     * @return array<mixed>
     */
    public function priceSuggestions(int $releaseId): array
    {
        return $this->client->get("marketplace/price_suggestions/{$releaseId}");
    }

    /**
     * GET /marketplace/stats/{release_id}
     *
     * @return array<mixed>
     */
    public function stats(int $releaseId, ?string $currency = null): array
    {
        return $this->client->get("marketplace/stats/{$releaseId}", ['curr_abbr' => $currency]);
    }
}
