<?php

namespace Osaris\Discogs\Resources;

use InvalidArgumentException;
use Osaris\Discogs\Page;

/**
 * Bulk inventory export and CSV upload for the authenticated seller.
 */
class Inventory extends Resource
{
    /**
     * POST /inventory/export - queue a CSV export of the inventory.
     * Throws ConflictException (409) if one is already in progress.
     */
    public function requestExport(): bool
    {
        return $this->client->send('POST', 'inventory/export')->successful();
    }

    /**
     * GET /inventory/export
     *
     * @param  array<string, mixed>  $query  page, per_page
     */
    public function exports(array $query = []): Page
    {
        return $this->client->paginate('inventory/export', 'items', $query);
    }

    /**
     * GET /inventory/export/{id}
     *
     * @return array<mixed>
     */
    public function export(int $exportId): array
    {
        return $this->client->get("inventory/export/{$exportId}");
    }

    /**
     * GET /inventory/export/{id}/download - the CSV contents.
     */
    public function downloadExport(int $exportId): string
    {
        return $this->client->download("inventory/export/{$exportId}/download");
    }

    /**
     * POST /inventory/upload/add - create listings from a CSV.
     *
     * Header row: release_id, price, media_condition (required), plus optional
     * sleeve_condition, comments, accept_offer (Y|N), location, external_id,
     * weight, format_quantity. Listings go live as "For Sale" immediately.
     *
     * @return array<mixed>
     */
    public function uploadAdd(string $csv, string $filename = 'inventory.csv'): array
    {
        return $this->client->upload('inventory/upload/add', 'upload', $csv, $filename);
    }

    /**
     * POST /inventory/upload/change - edit listings from a CSV.
     *
     * Header row: release_id (required) and at least one of price,
     * media_condition, sleeve_condition, comments, accept_offer, external_id,
     * location, weight, format_quantity.
     *
     * @return array<mixed>
     */
    public function uploadChange(string $csv, string $filename = 'inventory.csv'): array
    {
        return $this->client->upload('inventory/upload/change', 'upload', $csv, $filename);
    }

    /**
     * POST /inventory/upload/delete - delete listings named in a CSV.
     *
     * Header row: listing_id.
     *
     * @return array<mixed>
     */
    public function uploadDelete(string $csv, string $filename = 'inventory.csv'): array
    {
        return $this->client->upload('inventory/upload/delete', 'upload', $csv, $filename);
    }

    /**
     * Upload a CSV from disk. $action is "add", "change" or "delete".
     *
     * @return array<mixed>
     */
    public function uploadFile(string $action, string $path): array
    {
        if (! in_array($action, ['add', 'change', 'delete'], true)) {
            throw new InvalidArgumentException('Upload action must be add, change or delete.');
        }

        if (! is_readable($path)) {
            throw new InvalidArgumentException("Cannot read inventory CSV at [{$path}].");
        }

        return $this->client->upload("inventory/upload/{$action}", 'upload', (string) file_get_contents($path), basename($path));
    }

    /**
     * GET /inventory/upload
     *
     * @param  array<string, mixed>  $query  page, per_page
     */
    public function uploads(array $query = []): Page
    {
        return $this->client->paginate('inventory/upload', 'items', $query);
    }

    /**
     * GET /inventory/upload/{id}
     *
     * @return array<mixed>
     */
    public function upload(int $uploadId): array
    {
        return $this->client->get("inventory/upload/{$uploadId}");
    }
}
