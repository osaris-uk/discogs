<?php

namespace Osaris\Discogs\Resources;

use Osaris\Discogs\Page;

class Lists extends Resource
{
    /**
     * GET /users/{username}/lists
     *
     * @param  array<string, mixed>  $query  page, per_page
     */
    public function forUser(?string $username = null, array $query = []): Page
    {
        return $this->client->paginate("users/{$this->user($username)}/lists", 'lists', $query);
    }

    /**
     * GET /lists/{list_id}
     *
     * @return array<mixed>
     */
    public function get(int|string $listId): array
    {
        return $this->client->get('lists/'.rawurlencode((string) $listId));
    }
}
