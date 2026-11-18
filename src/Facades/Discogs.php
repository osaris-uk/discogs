<?php

namespace Osaris\Discogs\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Osaris\Discogs\Discogs withCredentials(\Osaris\Discogs\Auth\Credentials $credentials, ?string $username = null)
 * @method static \Osaris\Discogs\Discogs withToken(string $token, ?string $username = null)
 * @method static \Osaris\Discogs\Discogs withOAuth(string $token, string $tokenSecret, ?string $username = null)
 * @method static \Osaris\Discogs\Discogs withConsumer(?string $key = null, ?string $secret = null)
 * @method static \Osaris\Discogs\OAuthFlow oauth()
 * @method static \Osaris\Discogs\Resources\Database database()
 * @method static \Osaris\Discogs\Resources\Marketplace marketplace()
 * @method static \Osaris\Discogs\Resources\Inventory inventory()
 * @method static \Osaris\Discogs\Resources\Users users()
 * @method static \Osaris\Discogs\Resources\Collection collection()
 * @method static \Osaris\Discogs\Resources\Wantlist wantlist()
 * @method static \Osaris\Discogs\Resources\Lists lists()
 * @method static array<mixed> identity()
 * @method static string username()
 * @method static \Osaris\Discogs\Client client()
 * @method static \Osaris\Discogs\RateLimit|null rateLimit()
 *
 * @see \Osaris\Discogs\Discogs
 */
class Discogs extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Osaris\Discogs\Discogs::class;
    }
}
