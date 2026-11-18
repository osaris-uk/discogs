<?php

namespace Osaris\Discogs\Resources;

use Osaris\Discogs\Page;

class Wantlist extends Resource
{
    /**
     * GET /users/{username}/wants
     *
     * @param  array<string, mixed>  $query  sort, sort_order, page, per_page
     */
    public function all(?string $username = null, array $query = []): Page
    {
        return $this->client->paginate("users/{$this->user($username)}/wants", 'wants', $query);
    }

    /**
     * PUT /users/{username}/wants/{release_id}
     *
     * @param  int|null  $rating  0 to 5.
     * @return array<mixed>
     */
    public function add(int $releaseId, ?string $notes = null, ?int $rating = null, ?string $username = null): array
    {
        return $this->client->put("users/{$this->user($username)}/wants/{$releaseId}", [
            'notes' => $notes,
            'rating' => $rating,
        ]);
    }

    /**
     * POST /users/{username}/wants/{release_id}
     *
     * @return array<mixed>
     */
    public function update(int $releaseId, ?string $notes = null, ?int $rating = null, ?string $username = null): array
    {
        return $this->client->post("users/{$this->user($username)}/wants/{$releaseId}", [
            'notes' => $notes,
            'rating' => $rating,
        ]);
    }

    /**
     * DELETE /users/{username}/wants/{release_id}
     */
    public function remove(int $releaseId, ?string $username = null): bool
    {
        return $this->client->delete("users/{$this->user($username)}/wants/{$releaseId}");
    }
}
