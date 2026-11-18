# osaris-uk/discogs

[![tests](https://github.com/osaris-uk/discogs/actions/workflows/tests.yml/badge.svg)](https://github.com/osaris-uk/discogs/actions/workflows/tests.yml)

A Laravel client for the [Discogs API](https://www.discogs.com/developers) (v2), built on Laravel's HTTP client.

**Authenticated requests only.** Every request carries an `Authorization` header. If no credentials are available the client throws `MissingCredentialsException` and sends nothing; there is no anonymous fallback.

- PHP 8.2+, Laravel 12 or 13
- Personal token, consumer key/secret and full OAuth 1.0a
- Every documented endpoint: database, marketplace, inventory export/upload, users, collection, wantlist, lists
- Pagination helpers, rate limit handling, retried reads, typed exceptions

## Install

```bash
composer require osaris-uk/discogs
php artisan vendor:publish --tag=discogs-config   # optional
```

## Configure

```dotenv
# Identify your app. Defaults to "<AppName>/1.0 +<APP_URL>".
DISCOGS_USER_AGENT="MyApp/1.0 +https://myapp.example"

# One of the following:

# 1. Personal access token - acts as your own account
DISCOGS_TOKEN=

# 2. Consumer key/secret - authenticated app, no user (database + search)
DISCOGS_CONSUMER_KEY=
DISCOGS_CONSUMER_SECRET=

# 3. Add an OAuth access token to the consumer pair - acts as that user
DISCOGS_OAUTH_TOKEN=
DISCOGS_OAUTH_TOKEN_SECRET=

# Optional: saves an identity lookup for user-scoped calls
DISCOGS_USERNAME=
```

When more than one set is present the order is OAuth, then token, then consumer.

| Credentials | Acts as a user | Use for |
| --- | --- | --- |
| Consumer key/secret | No | Database reads, search, image URLs |
| Personal token | Yes, the token owner | Your own inventory, orders, collection, wantlist |
| OAuth 1.0a | Yes, whoever authorised | Other people's accounts (multi-tenant apps) |

## Usage

```php
use Osaris\Discogs\Facades\Discogs;

$release = Discogs::database()->release(249504, currency: 'GBP');

$results = Discogs::database()->search('Luna-C', ['type' => 'artist', 'per_page' => 25]);

foreach ($results as $result) {
    // ...
}
```

You can also inject `Osaris\Discogs\Discogs`. Responses are the decoded JSON as arrays.

### Pagination

Paginated endpoints return a `Page`:

```php
$page = Discogs::marketplace()->inventory(query: ['status' => 'For Sale', 'per_page' => 100]);

$page->items();        // this page's items
$page->total();        // items across all pages
$page->page();         // current page
$page->pages();        // page count
$page->hasMorePages();
$page->next();         // ?Page
$page->raw();          // full response

// Walk everything; further pages are fetched as you reach them
$page->lazy()->each(fn (array $listing) => /* ... */);
```

`per_page` tops out at 100. Each page is one request against the rate limit.

`users()->submissions()` is the exception: its items are grouped by type (`artists`, `labels`, `releases`), so read them with `$page->items()['releases']` rather than iterating the page.

### Per-user credentials

Each call returns a separate client, so the default one is untouched:

```php
Discogs::withToken($store->discogs_token)->marketplace()->orders();

Discogs::withOAuth($user->discogs_token, $user->discogs_token_secret, $user->discogs_username)
    ->collection()
    ->releases();
```

User-scoped methods take an optional `$username`. Left out, it falls back to `DISCOGS_USERNAME`, then to the authenticated user from `/oauth/identity` (looked up once per client).

### OAuth flow

```php
// 1. Start
$request = Discogs::oauth()->requestToken(route('discogs.callback'));
session(['discogs.request_secret' => $request['oauth_token_secret']]);

return redirect($request['authorize_url']);

// 2. Callback
$access = Discogs::oauth()->accessToken(
    $httpRequest->query('oauth_token'),
    session()->pull('discogs.request_secret'),
    $httpRequest->query('oauth_verifier'),
);

// 3. Store $access['oauth_token'] and $access['oauth_token_secret'] (encrypted).
//    They do not expire unless the user revokes access.
$username = Discogs::withOAuth($access['oauth_token'], $access['oauth_token_secret'])->identity()['username'];
```

## Endpoints

### `Discogs::database()`

| Method | Endpoint |
| --- | --- |
| `release($id, $currency = null)` | `GET /releases/{id}` |
| `releaseRating($id, $username = null)` | `GET /releases/{id}/rating/{username}` |
| `rateRelease($id, $rating, $username = null)` | `PUT /releases/{id}/rating/{username}` |
| `deleteReleaseRating($id, $username = null)` | `DELETE /releases/{id}/rating/{username}` |
| `communityRating($id)` | `GET /releases/{id}/rating` |
| `releaseStats($id)` | `GET /releases/{id}/stats` |
| `master($id)` | `GET /masters/{id}` |
| `masterVersions($id, $query = [])` | `GET /masters/{id}/versions` |
| `artist($id)` | `GET /artists/{id}` |
| `artistReleases($id, $query = [])` | `GET /artists/{id}/releases` |
| `label($id)` | `GET /labels/{id}` |
| `labelReleases($id, $query = [])` | `GET /labels/{id}/releases` |
| `search($q = null, $query = [])` | `GET /database/search` |

### `Discogs::marketplace()`

| Method | Endpoint |
| --- | --- |
| `inventory($username = null, $query = [])` | `GET /users/{username}/inventory` |
| `listing($id, $currency = null)` | `GET /marketplace/listings/{id}` |
| `createListing($releaseId, $condition, $price, $attributes = [])` | `POST /marketplace/listings` (as a draft unless `status` is given) |
| `updateListing($id, $attributes)` | `POST /marketplace/listings/{id}` |
| `deleteListing($id)` | `DELETE /marketplace/listings/{id}` |
| `orders($query = [])` | `GET /marketplace/orders` |
| `order($orderId)` | `GET /marketplace/orders/{id}` |
| `updateOrder($orderId, $attributes)` | `POST /marketplace/orders/{id}` |
| `orderMessages($orderId, $query = [])` | `GET /marketplace/orders/{id}/messages` |
| `addOrderMessage($orderId, $message = null, $status = null)` | `POST /marketplace/orders/{id}/messages` |
| `fee($price, $currency = null)` | `GET /marketplace/fee/{price}[/{currency}]` |
| `priceSuggestions($releaseId)` | `GET /marketplace/price_suggestions/{id}` |
| `stats($releaseId, $currency = null)` | `GET /marketplace/stats/{id}` |

### `Discogs::inventory()`

| Method | Endpoint |
| --- | --- |
| `requestExport()` | `POST /inventory/export` |
| `exports($query = [])` | `GET /inventory/export` |
| `export($id)` | `GET /inventory/export/{id}` |
| `downloadExport($id)` | `GET /inventory/export/{id}/download` (CSV string) |
| `uploadAdd($csv)` / `uploadChange($csv)` / `uploadDelete($csv)` | `POST /inventory/upload/{add,change,delete}` |
| `uploadFile($action, $path)` | As above, from a file on disk |
| `uploads($query = [])` | `GET /inventory/upload` |
| `upload($id)` | `GET /inventory/upload/{id}` |

### `Discogs::users()`

| Method | Endpoint |
| --- | --- |
| `identity()` | `GET /oauth/identity` |
| `profile($username = null)` | `GET /users/{username}` |
| `updateProfile($attributes, $username = null)` | `POST /users/{username}` |
| `submissions($username = null, $query = [])` | `GET /users/{username}/submissions` |
| `contributions($username = null, $query = [])` | `GET /users/{username}/contributions` |

### `Discogs::collection()`

| Method | Endpoint |
| --- | --- |
| `folders($username = null)` | `GET /users/{username}/collection/folders` |
| `createFolder($name, $username = null)` | `POST .../collection/folders` |
| `folder($folderId, $username = null)` | `GET .../folders/{folder}` |
| `renameFolder($folderId, $name, $username = null)` | `POST .../folders/{folder}` |
| `deleteFolder($folderId, $username = null)` | `DELETE .../folders/{folder}` |
| `releases($folderId = 0, $username = null, $query = [])` | `GET .../folders/{folder}/releases` |
| `instances($releaseId, $username = null, $query = [])` | `GET .../collection/releases/{release}` |
| `addRelease($releaseId, $folderId = 1, $username = null)` | `POST .../folders/{folder}/releases/{release}` |
| `updateInstance($folderId, $releaseId, $instanceId, $rating = null, $moveToFolderId = null, $username = null)` | `POST .../instances/{instance}` |
| `removeInstance($folderId, $releaseId, $instanceId, $username = null)` | `DELETE .../instances/{instance}` |
| `fields($username = null)` | `GET .../collection/fields` |
| `setField($folderId, $releaseId, $instanceId, $fieldId, $value, $username = null)` | `POST .../instances/{instance}/fields/{field}` |
| `value($username = null)` | `GET .../collection/value` |

### `Discogs::wantlist()`

| Method | Endpoint |
| --- | --- |
| `all($username = null, $query = [])` | `GET /users/{username}/wants` |
| `add($releaseId, $notes = null, $rating = null, $username = null)` | `PUT /users/{username}/wants/{release}` |
| `update($releaseId, $notes = null, $rating = null, $username = null)` | `POST /users/{username}/wants/{release}` |
| `remove($releaseId, $username = null)` | `DELETE /users/{username}/wants/{release}` |

### `Discogs::lists()`

| Method | Endpoint |
| --- | --- |
| `forUser($username = null, $query = [])` | `GET /users/{username}/lists` |
| `get($listId)` | `GET /lists/{id}` |

Anything not covered can go through the transport directly: `Discogs::client()->get('some/path', $query)`.

New listings are created as drafts. To list for sale straight away, pass the status:

```php
use Osaris\Discogs\Resources\Marketplace;

Discogs::marketplace()->createListing(249504, 'Mint (M)', 25.00, [
    'status' => Marketplace::STATUS_FOR_SALE,
]);
```

## Rate limits

Discogs limits authenticated requests per source IP over a moving 60 second window. By default a `429` throws `RateLimitException` straight away, which suits web requests: nothing blocks while a user waits.

In queued jobs and console commands you can have the client wait and retry instead. Set `DISCOGS_RATE_LIMIT_RETRIES` above 0 and on a `429` it waits (`Retry-After` if sent, otherwise `DISCOGS_RATE_LIMIT_WAIT` seconds, 60 by default) before trying again. Alternatively, catch `RateLimitException` and release the job back onto the queue. Set `DISCOGS_THROTTLE=true` to also pause before a request when the previous response reported nothing remaining.

```php
Discogs::rateLimit()?->remaining;   // from the last response: limit, used, remaining
```

## Retries

GET requests that fail with a connection error or a `5xx` response are retried twice, waiting 500ms and then 1s (`DISCOGS_RETRY_TIMES`, `DISCOGS_RETRY_SLEEP` in milliseconds). Writes are never retried, since a `POST` that timed out may still have created a listing or invoiced an order.

Waits use `Illuminate\Support\Sleep`, so `Sleep::fake()` works in tests.

## Errors

All exceptions extend `Osaris\Discogs\Exceptions\DiscogsException`, which exposes `->response` and `->status()`.

| Status | Exception |
| --- | --- |
| none | `MissingCredentialsException` (no credentials configured) |
| none | `ConnectionException` (timeout, DNS failure, refused connection) |
| 401 | `AuthenticationException` |
| 403 | `ForbiddenException` |
| 404 | `NotFoundException` |
| 409 | `ConflictException` (e.g. an export or upload already in progress) |
| 422 | `ValidationException` |
| 429 | `RateLimitException` |
| other 4xx/5xx | `DiscogsException` |

## Testing

```bash
composer test
composer analyse   # Larastan
```

In your own app, fake the HTTP client as usual:

```php
Http::fake(['api.discogs.com/*' => Http::response(['id' => 1])]);
```

## Licence

MIT
