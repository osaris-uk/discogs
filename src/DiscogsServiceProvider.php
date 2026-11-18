<?php

namespace Osaris\Discogs;

use Illuminate\Support\ServiceProvider;
use Osaris\Discogs\Auth\CredentialsFactory;

class DiscogsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/discogs.php', 'discogs');

        $this->app->singleton(Discogs::class, function ($app) {
            $config = (array) $app['config']->get('discogs', []);

            if (blank($config['user_agent'] ?? null)) {
                $config['user_agent'] = $this->defaultUserAgent(
                    (string) $app['config']->get('app.name', ''),
                    (string) $app['config']->get('app.url', ''),
                );
            }

            return new Discogs(CredentialsFactory::fromConfig($config), $config);
        });

        $this->app->alias(Discogs::class, 'discogs');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/discogs.php' => config_path('discogs.php'),
            ], 'discogs-config');
        }
    }

    protected function defaultUserAgent(string $name, string $url): string
    {
        $product = preg_replace('/[^A-Za-z0-9]+/', '', ucwords($name)) ?: 'OsarisDiscogs';

        return $product.'/1.0'.($url !== '' ? ' +'.$url : '');
    }
}
