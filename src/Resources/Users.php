<?php

namespace Osaris\Discogs\Resources;

use Osaris\Discogs\Page;

class Users extends Resource
{
    /**
     * GET /oauth/identity
     *
     * @return array<mixed> id, username, resource_url, consumer_name
     */
    public function identity(): array
    {
        return $this->client->get('oauth/identity');
    }

    /**
     * GET /users/{username}
     *
     * @return array<mixed>
     */
    public function profile(?string $username = null): array
    {
        return $this->client->get("users/{$this->user($username)}");
    }

    /**
     * POST /users/{username}
     *
     * @param  array<string, mixed>  $attributes  name, home_page, location, profile, curr_abbr
     * @return array<mixed>
     */
    public function updateProfile(array $attributes, ?string $username = null): array
    {
        return $this->client->post("users/{$this->user($username)}", $attributes);
    }

    /**
     * GET /users/{username}/submissions
     *
     * Unlike other pages, items are grouped by type:
     * ['artists' => [...], 'labels' => [...], 'releases' => [...]]. Iterating
     * the page, or lazy(), yields those three groups rather than individual
     * submissions, so read them with items()['releases'] and so on.
     *
     * @param  array<string, mixed>  $query  page, per_page
     */
    public function submissions(?string $username = null, array $query = []): Page
    {
        return $this->client->paginate("users/{$this->user($username)}/submissions", 'submissions', $query);
    }

    /**
     * GET /users/{username}/contributions
     *
     * @param  array<string, mixed>  $query  sort (label|artist|title|catno|format|rating|year|added),
     *                                       sort_order (asc|desc), page, per_page
     */
    public function contributions(?string $username = null, array $query = []): Page
    {
        return $this->client->paginate("users/{$this->user($username)}/contributions", 'contributions', $query);
    }
}
