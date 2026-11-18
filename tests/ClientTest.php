<?php

namespace Osaris\Discogs\Tests;

use Illuminate\Http\Client\ConnectionException as HttpConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Osaris\Discogs\Exceptions\AuthenticationException;
use Osaris\Discogs\Exceptions\ConnectionException;
use Osaris\Discogs\Exceptions\DiscogsException;
use Osaris\Discogs\Exceptions\MissingCredentialsException;
use Osaris\Discogs\Exceptions\NotFoundException;
use Osaris\Discogs\Exceptions\RateLimitException;
use Osaris\Discogs\Facades\Discogs;
use Osaris\Discogs\Resources\Marketplace;

class ClientTest extends TestCase
{
    public function test_requests_carry_token_user_agent_and_accept_headers(): void
    {
        Http::fake(['api.discogs.com/*' => Http::response(['id' => 249504, 'title' => 'Never Gonna Give You Up'])]);

        $release = Discogs::database()->release(249504, 'GBP');

        $this->assertSame(249504, $release['id']);

        Http::assertSent(function (Request $request) {
            return $request->method() === 'GET'
                && $request->url() === 'https://api.discogs.com/releases/249504?curr_abbr=GBP'
                && $request->hasHeader('Authorization', 'Discogs token=test-token')
                && $request->hasHeader('User-Agent', 'TestApp/1.0 +https://example.test')
                && $request->hasHeader('Accept', 'application/vnd.discogs.v2.discogs+json');
        });
    }

    public function test_nothing_is_sent_without_credentials(): void
    {
        Http::fake();
        config(['discogs.token' => null]);
        $this->app->forgetInstance(\Osaris\Discogs\Discogs::class);
        Discogs::clearResolvedInstances();

        try {
            Discogs::database()->release(1);
            $this->fail('Expected MissingCredentialsException.');
        } catch (MissingCredentialsException) {
            Http::assertNothingSent();
        }
    }

    public function test_consumer_key_and_secret_are_used_when_no_token_is_set(): void
    {
        Http::fake(['*' => Http::response(['results' => [], 'pagination' => ['page' => 1, 'pages' => 1]])]);
        config(['discogs.token' => null, 'discogs.consumer_key' => 'key', 'discogs.consumer_secret' => 'secret']);
        $this->app->forgetInstance(\Osaris\Discogs\Discogs::class);
        Discogs::clearResolvedInstances();

        Discogs::database()->search('luna-c', ['type' => 'artist']);

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Discogs key=key, secret=secret')
            && $request['q'] === 'luna-c'
            && $request['type'] === 'artist');
    }

    public function test_with_oauth_signs_requests_as_the_user(): void
    {
        Http::fake(['*' => Http::response(['username' => 'someone'])]);
        config(['discogs.consumer_key' => 'key', 'discogs.consumer_secret' => 'secret']);
        $this->app->forgetInstance(\Osaris\Discogs\Discogs::class);
        Discogs::clearResolvedInstances();

        Discogs::withOAuth('user-token', 'user-secret')->identity();

        Http::assertSent(function (Request $request) {
            $header = $request->header('Authorization')[0] ?? '';

            return str_starts_with($header, 'OAuth ')
                && str_contains($header, 'oauth_consumer_key="key"')
                && str_contains($header, 'oauth_token="user-token"')
                && str_contains($header, 'oauth_signature="secret&user-secret"')
                && str_contains($header, 'oauth_signature_method="PLAINTEXT"');
        });
    }

    public function test_errors_map_to_exceptions(): void
    {
        Http::fake([
            '*/releases/1' => Http::response(['message' => 'Release not found.'], 404),
            '*/oauth/identity' => Http::response(['message' => 'You must authenticate to access this resource.'], 401),
        ]);

        try {
            Discogs::database()->release(1);
            $this->fail('Expected NotFoundException.');
        } catch (NotFoundException $e) {
            $this->assertSame('Release not found.', $e->getMessage());
            $this->assertSame(404, $e->status());
        }

        $this->expectException(AuthenticationException::class);
        Discogs::identity();
    }

    public function test_rate_limited_requests_wait_and_retry(): void
    {
        Sleep::fake();

        Http::fake(['*' => Http::sequence()
            ->push(['message' => 'You are making requests too quickly.'], 429)
            ->push(['id' => 1], 200, [
                'X-Discogs-Ratelimit' => '60',
                'X-Discogs-Ratelimit-Used' => '1',
                'X-Discogs-Ratelimit-Remaining' => '59',
            ]),
        ]);

        $this->assertSame(1, Discogs::database()->release(1)['id']);

        Http::assertSentCount(2);
        Sleep::assertSleptTimes(1);
        $this->assertSame(59, Discogs::rateLimit()->remaining);
    }

    public function test_rate_limit_exception_is_thrown_once_retries_run_out(): void
    {
        Sleep::fake();
        Http::fake(['*' => Http::response(['message' => 'Too many requests.'], 429)]);

        try {
            Discogs::database()->release(1);
            $this->fail('Expected RateLimitException.');
        } catch (RateLimitException) {
            Http::assertSentCount(3);
        }
    }

    public function test_pages_can_be_walked_lazily(): void
    {
        Http::fake(function (Request $request) {
            $page = (int) ($request['page'] ?? 1);

            return Http::response([
                'pagination' => ['page' => $page, 'pages' => 2, 'per_page' => 2, 'items' => 3],
                'releases' => $page === 1 ? [['id' => 1], ['id' => 2]] : [['id' => 3]],
            ]);
        });

        $page = Discogs::database()->labelReleases(1, ['per_page' => 2]);

        $this->assertSame(3, $page->total());
        $this->assertTrue($page->hasMorePages());
        $this->assertCount(2, $page);
        $this->assertSame([1, 2, 3], $page->lazy()->pluck('id')->all());

        Http::assertSentCount(2);
    }

    public function test_username_defaults_to_the_authenticated_user(): void
    {
        Http::fake([
            '*/oauth/identity' => Http::response(['id' => 1, 'username' => 'john']),
            '*/users/john/wants*' => Http::response(['pagination' => ['page' => 1, 'pages' => 1], 'wants' => [['id' => 5]]]),
            '*/users/john/wants/5' => Http::response(['id' => 5]),
        ]);

        $this->assertSame(5, Discogs::wantlist()->all()->items()[0]['id']);

        Discogs::wantlist()->add(5, notes: 'Original pressing', rating: 4);

        Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
            && $request->url() === 'https://api.discogs.com/users/john/wants/5'
            && $request['notes'] === 'Original pressing'
            && $request['rating'] === 4);

        // identity is looked up once and remembered
        Http::assertSentCount(3);
    }

    public function test_listings_are_created_as_drafts_with_a_json_body(): void
    {
        Http::fake(['*' => Http::response(['listing_id' => 41578241], 201)]);

        $listing = Discogs::marketplace()->createListing(249504, 'Mint (M)', 10.50, ['sleeve_condition' => 'Near Mint (NM or M-)']);

        $this->assertSame(41578241, $listing['listing_id']);

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://api.discogs.com/marketplace/listings'
            && $request->isJson()
            && $request['release_id'] === 249504
            && $request['price'] === 10.5
            && $request['status'] === 'Draft');
    }

    public function test_listings_can_be_put_up_for_sale_directly(): void
    {
        Http::fake(['*' => Http::response(['listing_id' => 1], 201)]);

        Discogs::marketplace()->createListing(249504, 'Mint (M)', 10, ['status' => Marketplace::STATUS_FOR_SALE]);

        Http::assertSent(fn (Request $request) => $request['status'] === 'For Sale');
    }

    public function test_inventory_csv_is_uploaded_as_multipart(): void
    {
        Http::fake(['*' => Http::response(['id' => 1], 201)]);

        $csv = "release_id,price,media_condition\n249504,10.00,Mint (M)\n";

        Discogs::inventory()->uploadAdd($csv, 'stock.csv');

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://api.discogs.com/inventory/upload/add'
            && $request->isMultipart()
            && $request->hasFile('upload', $csv, 'stock.csv'));
    }

    public function test_connection_errors_are_wrapped(): void
    {
        Sleep::fake();
        Http::fake(fn () => throw new HttpConnectionException('cURL error 28: Operation timed out'));

        try {
            Discogs::database()->release(1);
            $this->fail('Expected ConnectionException.');
        } catch (ConnectionException $e) {
            $this->assertInstanceOf(DiscogsException::class, $e);
            $this->assertInstanceOf(HttpConnectionException::class, $e->getPrevious());
            $this->assertNull($e->status());
        }

        // The initial attempt plus two retries.
        Sleep::assertSleptTimes(2);
    }

    public function test_failed_reads_are_retried(): void
    {
        Sleep::fake();
        Http::fake(['*' => Http::sequence()
            ->push('<html>Bad Gateway</html>', 502)
            ->push(['id' => 1]),
        ]);

        $this->assertSame(1, Discogs::database()->release(1)['id']);

        Http::assertSentCount(2);
        Sleep::assertSlept(fn ($duration) => (int) $duration->totalMilliseconds === 500);
    }

    public function test_failed_writes_are_not_retried(): void
    {
        Sleep::fake();
        Http::fake(['*' => Http::response('<html>Bad Gateway</html>', 502)]);

        try {
            Discogs::marketplace()->createListing(1, 'Mint (M)', 10);
            $this->fail('Expected DiscogsException.');
        } catch (DiscogsException $e) {
            $this->assertSame(502, $e->status());
        }

        Http::assertSentCount(1);
        Sleep::assertNeverSlept();
    }

    public function test_rate_limits_throw_immediately_by_default(): void
    {
        Sleep::fake();
        $shipped = require __DIR__.'/../config/discogs.php';
        config(['discogs.rate_limit' => $shipped['rate_limit']]);
        $this->app->forgetInstance(\Osaris\Discogs\Discogs::class);
        Discogs::clearResolvedInstances();

        Http::fake(['*' => Http::response(['message' => 'Too many requests.'], 429)]);

        try {
            Discogs::database()->release(1);
            $this->fail('Expected RateLimitException.');
        } catch (RateLimitException) {
            Http::assertSentCount(1);
            Sleep::assertNeverSlept();
        }
    }

    public function test_throttle_pauses_when_no_requests_remain(): void
    {
        Sleep::fake();
        config(['discogs.rate_limit.throttle' => true, 'discogs.rate_limit.wait' => 30]);
        $this->app->forgetInstance(\Osaris\Discogs\Discogs::class);
        Discogs::clearResolvedInstances();

        Http::fake(['*' => Http::response(['id' => 1], 200, [
            'X-Discogs-Ratelimit' => '60',
            'X-Discogs-Ratelimit-Used' => '60',
            'X-Discogs-Ratelimit-Remaining' => '0',
        ])]);

        Discogs::database()->release(1);
        Sleep::assertNeverSlept();

        Discogs::database()->release(2);
        Sleep::assertSequence([Sleep::for(30)->seconds()]);
    }

    public function test_a_missing_username_throws_instead_of_building_a_bad_url(): void
    {
        Http::fake(['*/oauth/identity' => Http::response(['id' => 1])]);

        try {
            Discogs::wantlist()->all();
            $this->fail('Expected DiscogsException.');
        } catch (DiscogsException $e) {
            $this->assertStringContainsString('username', $e->getMessage());
        }

        Http::assertSentCount(1);
    }

    public function test_oauth_flow_exchanges_tokens(): void
    {
        config(['discogs.consumer_key' => 'key', 'discogs.consumer_secret' => 'secret']);
        $this->app->forgetInstance(\Osaris\Discogs\Discogs::class);
        Discogs::clearResolvedInstances();

        Http::fake([
            '*/oauth/request_token' => Http::response('oauth_token=req&oauth_token_secret=reqsecret&oauth_callback_confirmed=true'),
            '*/oauth/access_token' => Http::response('oauth_token=acc&oauth_token_secret=accsecret'),
        ]);

        $request = Discogs::oauth()->requestToken('https://example.test/discogs/callback');

        $this->assertSame('req', $request['oauth_token']);
        $this->assertSame('https://www.discogs.com/oauth/authorize?oauth_token=req', $request['authorize_url']);
        $this->assertTrue($request['oauth_callback_confirmed']);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'request_token')
            && $r->method() === 'GET'
            && str_contains($r->header('Authorization')[0], 'oauth_callback="https%3A%2F%2Fexample.test%2Fdiscogs%2Fcallback"')
            && str_contains($r->header('Authorization')[0], 'oauth_signature="secret&"'));

        $access = Discogs::oauth()->accessToken('req', 'reqsecret', 'verifier');

        $this->assertSame(['oauth_token' => 'acc', 'oauth_token_secret' => 'accsecret'], $access);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'access_token')
            && $r->method() === 'POST'
            && str_contains($r->header('Authorization')[0], 'oauth_signature="secret&reqsecret"')
            && str_contains($r->header('Authorization')[0], 'oauth_verifier="verifier"'));
    }
}
