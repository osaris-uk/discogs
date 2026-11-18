<?php

namespace Osaris\Discogs\Resources;

use Osaris\Discogs\Page;

class Collection extends Resource
{
    /** The "All" folder. Read-only: releases cannot be added to it. */
    public const FOLDER_ALL = 0;

    /** The "Uncategorized" folder. */
    public const FOLDER_UNCATEGORIZED = 1;

    /**
     * GET /users/{username}/collection/folders
     *
     * @return array<mixed>
     */
    public function folders(?string $username = null): array
    {
        return $this->client->get("{$this->base($username)}/folders")['folders'] ?? [];
    }

    /**
     * POST /users/{username}/collection/folders
     *
     * @return array<mixed>
     */
    public function createFolder(string $name, ?string $username = null): array
    {
        return $this->client->post("{$this->base($username)}/folders", ['name' => $name]);
    }

    /**
     * GET /users/{username}/collection/folders/{folder_id}
     *
     * @return array<mixed>
     */
    public function folder(int $folderId, ?string $username = null): array
    {
        return $this->client->get("{$this->base($username)}/folders/{$folderId}");
    }

    /**
     * POST /users/{username}/collection/folders/{folder_id}
     *
     * @return array<mixed>
     */
    public function renameFolder(int $folderId, string $name, ?string $username = null): array
    {
        return $this->client->post("{$this->base($username)}/folders/{$folderId}", ['name' => $name]);
    }

    /**
     * DELETE /users/{username}/collection/folders/{folder_id} - the folder must be empty.
     */
    public function deleteFolder(int $folderId, ?string $username = null): bool
    {
        return $this->client->delete("{$this->base($username)}/folders/{$folderId}");
    }

    /**
     * GET /users/{username}/collection/folders/{folder_id}/releases
     *
     * @param  array<string, mixed>  $query  sort (label|artist|title|catno|format|rating|added|year),
     *                                       sort_order (asc|desc), page, per_page
     */
    public function releases(int $folderId = self::FOLDER_ALL, ?string $username = null, array $query = []): Page
    {
        return $this->client->paginate("{$this->base($username)}/folders/{$folderId}/releases", 'releases', $query);
    }

    /**
     * GET /users/{username}/collection/releases/{release_id} - the folders and
     * instances a release appears in.
     *
     * @param  array<string, mixed>  $query  page, per_page
     */
    public function instances(int $releaseId, ?string $username = null, array $query = []): Page
    {
        return $this->client->paginate("{$this->base($username)}/releases/{$releaseId}", 'releases', $query);
    }

    /**
     * POST /users/{username}/collection/folders/{folder_id}/releases/{release_id}
     *
     * @return array<mixed> instance_id and resource_url
     */
    public function addRelease(int $releaseId, int $folderId = self::FOLDER_UNCATEGORIZED, ?string $username = null): array
    {
        return $this->client->post("{$this->base($username)}/folders/{$folderId}/releases/{$releaseId}");
    }

    /**
     * POST /users/{username}/collection/folders/{folder_id}/releases/{release_id}/instances/{instance_id}
     *
     * Change an instance's rating and/or move it to another folder.
     */
    public function updateInstance(
        int $folderId,
        int $releaseId,
        int $instanceId,
        ?int $rating = null,
        ?int $moveToFolderId = null,
        ?string $username = null,
    ): bool {
        return $this->client->send('POST', $this->instance($folderId, $releaseId, $instanceId, $username), data: [
            'rating' => $rating,
            'folder_id' => $moveToFolderId,
        ])->successful();
    }

    /**
     * DELETE /users/{username}/collection/folders/{folder_id}/releases/{release_id}/instances/{instance_id}
     */
    public function removeInstance(int $folderId, int $releaseId, int $instanceId, ?string $username = null): bool
    {
        return $this->client->delete($this->instance($folderId, $releaseId, $instanceId, $username));
    }

    /**
     * GET /users/{username}/collection/fields - the custom notes fields.
     *
     * @return array<mixed>
     */
    public function fields(?string $username = null): array
    {
        return $this->client->get("{$this->base($username)}/fields")['fields'] ?? [];
    }

    /**
     * POST .../instances/{instance_id}/fields/{field_id}
     *
     * For dropdown fields the value must match one of the field's options.
     */
    public function setField(
        int $folderId,
        int $releaseId,
        int $instanceId,
        int $fieldId,
        string $value,
        ?string $username = null,
    ): bool {
        return $this->client->send(
            'POST',
            $this->instance($folderId, $releaseId, $instanceId, $username)."/fields/{$fieldId}",
            query: ['value' => $value],
            data: ['value' => $value],
        )->successful();
    }

    /**
     * GET /users/{username}/collection/value - minimum, median and maximum.
     *
     * @return array<mixed>
     */
    public function value(?string $username = null): array
    {
        return $this->client->get("{$this->base($username)}/value");
    }

    protected function base(?string $username): string
    {
        return "users/{$this->user($username)}/collection";
    }

    protected function instance(int $folderId, int $releaseId, int $instanceId, ?string $username): string
    {
        return "{$this->base($username)}/folders/{$folderId}/releases/{$releaseId}/instances/{$instanceId}";
    }
}
