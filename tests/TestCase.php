<?php

namespace Osaris\Discogs\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Osaris\Discogs\DiscogsServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [DiscogsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.name', 'Test App');
        $app['config']->set('app.url', 'https://example.test');
        $app['config']->set('discogs.token', 'test-token');
        $app['config']->set('discogs.consumer_key', null);
        $app['config']->set('discogs.consumer_secret', null);
        $app['config']->set('discogs.oauth_token', null);
        $app['config']->set('discogs.oauth_token_secret', null);
        $app['config']->set('discogs.username', null);
        $app['config']->set('discogs.rate_limit', ['retries' => 2, 'wait' => 60, 'throttle' => false]);
        $app['config']->set('discogs.retry', ['times' => 2, 'sleep' => 500]);
    }
}
