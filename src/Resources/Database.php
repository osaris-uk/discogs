<?php

namespace Osaris\Discogs\Resources;

use Osaris\Discogs\Page;

class Database extends Resource
{
    /**
     * GET /releases/{release_id}
     *
     * @return array<mixed>
     */
    public function release(int $releaseId, ?string $currency = null): array
    {
        return $this->client->get("releases/{$releaseId}", ['curr_abbr' => $currency]);
    }

    /**
     * GET /releases/{release_id}/rating/{username}
     *
     * @return array<mixed>
     */
    public function releaseRating(int $releaseId, ?string $username = null): array
    {
        return $this->client->get("releases/{$releaseId}/rating/{$this->user($username)}");
    }

    /**
     * PUT /releases/{release_id}/rating/{username}
     *
     * @param  int  $rating  1 to 5.
     * @return array<mixed>
     */
    public function rateRelease(int $releaseId, int $rating, ?string $username = null): array
    {
        return $this->client->put("releases/{$releaseId}/rating/{$this->user($username)}", ['rating' => $rating]);
    }

    /**
     * DELETE /releases/{release_id}/rating/{username}
     */
    public function deleteReleaseRating(int $releaseId, ?string $username = null): bool
    {
        return $this->client->delete("releases/{$releaseId}/rating/{$this->user($username)}");
    }

    /**
     * GET /releases/{release_id}/rating
     *
     * @return array<mixed>
     */
    public function communityRating(int $releaseId): array
    {
        return $this->client->get("releases/{$releaseId}/rating");
    }

    /**
     * GET /releases/{release_id}/stats
     *
     * @return array<mixed>
     */
    public function releaseStats(int $releaseId): array
    {
        return $this->client->get("releases/{$releaseId}/stats");
    }

    /**
     * GET /masters/{master_id}
     *
     * @return array<mixed>
     */
    public function master(int $masterId): array
    {
        return $this->client->get("masters/{$masterId}");
    }

    /**
     * GET /masters/{master_id}/versions
     *
     * @param  array<string, mixed>  $query  page, per_page, format, label, released, country,
     *                                       sort (released|title|format|label|catno|country), sort_order (asc|desc)
     */
    public function masterVersions(int $masterId, array $query = []): Page
    {
        return $this->client->paginate("masters/{$masterId}/versions", 'versions', $query);
    }

    /**
     * GET /artists/{artist_id}
     *
     * @return array<mixed>
     */
    public function artist(int $artistId): array
    {
        return $this->client->get("artists/{$artistId}");
    }

    /**
     * GET /artists/{artist_id}/releases
     *
     * @param  array<string, mixed>  $query  page, per_page, sort (year|title|format), sort_order (asc|desc)
     */
    public function artistReleases(int $artistId, array $query = []): Page
    {
        return $this->client->paginate("artists/{$artistId}/releases", 'releases', $query);
    }

    /**
     * GET /labels/{label_id}
     *
     * @return array<mixed>
     */
    public function label(int $labelId): array
    {
        return $this->client->get("labels/{$labelId}");
    }

    /**
     * GET /labels/{label_id}/releases
     *
     * @param  array<string, mixed>  $query  page, per_page
     */
    public function labelReleases(int $labelId, array $query = []): Page
    {
        return $this->client->paginate("labels/{$labelId}/releases", 'releases', $query);
    }

    /**
     * GET /database/search
     *
     * @param  array<string, mixed>  $query  type (release|master|artist|label), title, release_title, credit,
     *                                       artist, anv, label, genre, style, country, year, format, catno,
     *                                       barcode, track, submitter, contributor,
     *                                       sort (score|title|have|want|hot|date_added|date_changed|year),
     *                                       sort_order (asc|desc), page, per_page
     */
    public function search(?string $q = null, array $query = []): Page
    {
        return $this->client->paginate('database/search', 'results', ['q' => $q] + $query);
    }
}
