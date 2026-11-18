<?php

namespace Osaris\Discogs;

use ArrayIterator;
use Countable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use IteratorAggregate;
use Traversable;

/**
 * One page of a paginated Discogs response.
 *
 * @implements IteratorAggregate<int|string, mixed>
 * @implements Arrayable<string, mixed>
 */
class Page implements Arrayable, Countable, IteratorAggregate
{
    /**
     * @param  array<string, mixed>  $query
     * @param  array<mixed>  $raw
     */
    public function __construct(
        protected Client $client,
        protected string $uri,
        protected string $key,
        protected array $query,
        protected array $raw,
    ) {}

    /**
     * The items on this page.
     *
     * @return array<mixed>
     */
    public function items(): array
    {
        $items = $this->raw[$this->key] ?? [];

        return is_array($items) ? $items : [];
    }

    /**
     * @return Collection<int|string, mixed>
     */
    public function collect(): Collection
    {
        return new Collection($this->items());
    }

    public function page(): int
    {
        return (int) ($this->raw['pagination']['page'] ?? 1);
    }

    public function pages(): int
    {
        return (int) ($this->raw['pagination']['pages'] ?? 1);
    }

    public function perPage(): int
    {
        return (int) ($this->raw['pagination']['per_page'] ?? count($this->items()));
    }

    /**
     * Total number of items across all pages.
     */
    public function total(): int
    {
        return (int) ($this->raw['pagination']['items'] ?? count($this->items()));
    }

    public function hasMorePages(): bool
    {
        return $this->page() < $this->pages();
    }

    /**
     * Fetch the next page, or null on the last one.
     */
    public function next(): ?self
    {
        if (! $this->hasMorePages()) {
            return null;
        }

        return $this->client->paginate($this->uri, $this->key, ['page' => $this->page() + 1] + $this->query);
    }

    /**
     * Lazily iterate every item from this page onwards, fetching further
     * pages only as they are reached. Each page is one API request.
     *
     * @return LazyCollection<int, mixed>
     */
    public function lazy(): LazyCollection
    {
        return new LazyCollection(function () {
            $page = $this;

            while ($page) {
                foreach ($page->items() as $item) {
                    yield $item;
                }

                $page = $page->next();
            }
        });
    }

    /**
     * The full decoded response, including keys other than the items
     * (for example "filters" and "filter_facets" on master versions).
     *
     * @return array<mixed>
     */
    public function raw(): array
    {
        return $this->raw;
    }

    public function count(): int
    {
        return count($this->items());
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items());
    }

    public function toArray(): array
    {
        return $this->raw;
    }
}
